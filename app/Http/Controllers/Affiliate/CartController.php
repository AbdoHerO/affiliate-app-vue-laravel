<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use App\Models\ProduitVariante;
use App\Models\AffiliateCartItem;
use App\Models\Client;
use App\Models\Adresse;
use App\Models\Commande;
use App\Models\CommandeArticle;
use App\Models\ShippingCity;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    /**
     * Add item to cart
     */
    public function addItem(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'produit_id' => 'required|uuid|exists:produits,id',
                'variante_id' => 'nullable|uuid|exists:produit_variantes,id',
                'qty' => 'required|integer|min:1|max:100',
                'sell_price' => 'nullable|numeric|min:0',
                'type_command' => 'nullable|string|in:order_sample,exchange'
            ]);

            $userId = Auth::id();

            // Verify product is active
            $product = Produit::where('id', $validated['produit_id'])
                ->where('actif', true)
                ->first();

            if (!$product) {
                return response()->json([
                    'message' => 'Produit non disponible',
                    'success' => false
                ], 404);
            }

            // Set default type_command if not provided
            $typeCommand = $validated['type_command'] ?? 'order_sample';

            // Validate sell_price based on command type
            if (isset($validated['sell_price'])) {
                if ($typeCommand === 'order_sample') {
                    // For order_sample, minimum price is prix_achat + 50 (delivery estimation)
                    $minimumPrice = $product->prix_achat + 50;
                    if ($validated['sell_price'] < $minimumPrice) {
                        return response()->json([
                            'message' => 'Le prix de vente minimum pour une commande est de ' . $minimumPrice . ' MAD (Prix d\'achat + 50 MAD livraison)',
                            'success' => false
                        ], 422);
                    }
                } else {
                    // For exchange, sell_price should not be set (will be ignored)
                    if ($validated['sell_price'] < $product->prix_achat) {
                        return response()->json([
                            'message' => 'Le prix de vente ne peut pas être inférieur au prix d\'achat (' . $product->prix_achat . ' MAD)',
                            'success' => false
                        ], 422);
                    }
                }
            }

            // Check global quantity (existing in cart + new quantity) against minimum requirement
            $existingCartItem = AffiliateCartItem::where('user_id', $userId)
                ->where('produit_id', $validated['produit_id'])
                ->where('variante_id', $validated['variante_id'] ?? null)
                ->first();

            $currentQtyInCart = $existingCartItem ? $existingCartItem->qty : 0;
            $totalQtyAfterAdd = $currentQtyInCart + $validated['qty'];

            if ($product->quantite_min && $totalQtyAfterAdd < $product->quantite_min) {
                return response()->json([
                    'message' => 'Quantité minimale requise: ' . $product->quantite_min . ' unité' . ($product->quantite_min > 1 ? 's' : '') .
                                 '. Vous avez actuellement ' . $currentQtyInCart . ' dans le panier.',
                    'minimum_quantity' => $product->quantite_min,
                    'current_quantity' => $currentQtyInCart,
                    'requested_quantity' => $validated['qty'],
                    'total_after_add' => $totalQtyAfterAdd,
                    'success' => false
                ], 422);
            }

            // If variant specified, verify it exists and has stock
            if (isset($validated['variante_id']) && $validated['variante_id']) {
                $variant = ProduitVariante::with('stocks')
                    ->where('id', $validated['variante_id'])
                    ->where('produit_id', $validated['produit_id'])
                    ->where('actif', true)
                    ->first();

                if (!$variant) {
                    return response()->json([
                        'message' => 'Variante non disponible',
                        'success' => false
                    ], 404);
                }

                // Get available stock from stocks relationship
                $availableStock = $variant->stock; // Uses the accessor we just added

                if ($availableStock < $validated['qty']) {
                    return response()->json([
                        'message' => 'Stock insuffisant pour cette variante',
                        'available_stock' => $availableStock,
                        'success' => false
                    ], 400);
                }
            } else {
                // Check total product stock if no variant specified
                $variants = ProduitVariante::with('stocks')
                    ->where('produit_id', $validated['produit_id'])
                    ->where('actif', true)
                    ->get();

                $totalStock = $variants->sum(function($variant) {
                    return $variant->stock; // Uses the accessor
                });

                if ($totalStock < $validated['qty']) {
                    return response()->json([
                        'message' => 'Stock insuffisant',
                        'available_stock' => $totalStock,
                        'success' => false
                    ], 400);
                }
            }

            // DEBUG: Log user and cart operation
            Log::info('🛒 ADD ITEM - User ID: ' . $userId);
            Log::info('🛒 ADD ITEM - Product: ' . $validated['produit_id'] . ', Variant: ' . ($validated['variante_id'] ?? 'none') . ', Qty: ' . $validated['qty']);

            // Find existing cart item or create new one
            $cartItem = AffiliateCartItem::where('user_id', $userId)
                ->where('produit_id', $validated['produit_id'])
                ->where('variante_id', $validated['variante_id'] ?? null)
                ->first();

            // Determine sell price (use provided or default to product's prix_vente)
            $sellPrice = $validated['sell_price'] ?? $product->prix_vente;

            if ($cartItem) {
                // Update existing item quantity, sell price, and command type
                $cartItem->qty += $validated['qty'];
                $cartItem->sell_price = $sellPrice;
                $cartItem->type_command = $typeCommand;
                $cartItem->added_at = now();
                $cartItem->save();

                Log::info('✅ UPDATED EXISTING CART ITEM - New qty: ' . $cartItem->qty . ', Sell price: ' . $sellPrice . ', Type: ' . $typeCommand);
            } else {
                // Create new cart item using direct assignment to avoid mass assignment issues
                $cartItem = new AffiliateCartItem();
                $cartItem->user_id = $userId;
                $cartItem->produit_id = $validated['produit_id'];
                $cartItem->variante_id = $validated['variante_id'] ?? null;
                $cartItem->qty = $validated['qty'];
                $cartItem->sell_price = $sellPrice;
                $cartItem->type_command = $typeCommand;
                $cartItem->added_at = now();
                $cartItem->save();

                Log::info('✅ CREATED NEW CART ITEM - ID: ' . $cartItem->id . ', Sell price: ' . $sellPrice . ', Type: ' . $typeCommand);
            }

            return response()->json([
                'message' => 'Produit ajouté au panier',
                'cart_item' => [
                    'produit_id' => $cartItem->produit_id,
                    'variante_id' => $cartItem->variante_id,
                    'qty' => $cartItem->qty,
                    'added_at' => $cartItem->added_at->toISOString()
                ],
                'success' => true
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Données invalides',
                'errors' => $e->errors(),
                'success' => false
            ], 422);
        } catch (\Exception $e) {
            Log::error('❌ ADD ITEM ERROR: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de l\'ajout au panier',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    /**
     * Get cart summary
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();

            // DEBUG: Log user for summary operation
            Log::info('📋 SUMMARY - User ID: ' . $userId);

            // Get cart items from database (without relationships for now)
            $cartItems = AffiliateCartItem::where('user_id', $userId)->get();
            
            Log::info('📋 SUMMARY - Found ' . $cartItems->count() . ' cart items in database');
            
            if ($cartItems->isEmpty()) {
                return response()->json([
                    'items_count' => 0,
                    'total_amount' => 0,
                    'estimated_commission' => 0,
                    'items' => []
                ]);
            }

            $itemsCount = 0;
            $totalAmount = 0;
            $estimatedCommission = 0;
            $items = [];

            foreach ($cartItems as $cartItem) {
                // Manually fetch product to avoid relationship issues
                $product = Produit::with('images')
                    ->where('id', $cartItem->produit_id)
                    ->where('actif', true)
                    ->first();
                
                // Skip if product no longer exists or is inactive
                if (!$product) {
                    continue;
                }

                $variant = null;
                if ($cartItem->variante_id) {
                    $variant = ProduitVariante::where('id', $cartItem->variante_id)
                        ->where('actif', true)
                        ->first();
                    
                    // Skip if variant is specified but no longer exists or is inactive
                    if (!$variant) {
                        continue;
                    }
                }

                // Use sell_price from cart item or fallback to product prix_vente
                $sellPrice = $cartItem->sell_price ?? $product->prix_vente;
                $itemTotal = $sellPrice * $cartItem->qty;

                // Calculate commission based on command type
                $commandType = $cartItem->type_command ?? 'order_sample';

                if ($commandType === 'exchange') {
                    // For exchange orders, commission is always 0
                    $itemCommission = 0;
                } else {
                    // For order_sample, calculate commission: (sell_price - cost_price) * quantity
                    // If prix_affilie is set, use it; otherwise calculate from margin
                    if ($product->prix_affilie > 0) {
                        $itemCommission = $product->prix_affilie * $cartItem->qty;
                    } else {
                        // Calculate commission as margin: (sell_price - cost_price) * qty
                        $margin = max(0, $sellPrice - $product->prix_achat);
                        $itemCommission = $margin * $cartItem->qty;
                    }
                }

                $itemsCount += $cartItem->qty;
                $totalAmount += $itemTotal;
                $estimatedCommission += $itemCommission;

                $items[] = [
                    'key' => $cartItem->produit_id . '_' . ($cartItem->variante_id ?? 'default'),
                    'produit_id' => $cartItem->produit_id,
                    'variante_id' => $cartItem->variante_id,
                    'qty' => $cartItem->qty,
                    'sell_price' => $sellPrice,
                    'type_command' => $cartItem->type_command ?? 'order_sample',
                    'item_commission' => $itemCommission,
                    'product' => [
                        'id' => $product->id,
                        'titre' => $product->titre,
                        'prix_vente' => (float) $product->prix_vente,
                        'prix_achat' => (float) $product->prix_achat,
                        'prix_affilie' => (float) $product->prix_affilie,
                        'image' => $product->images->first()?->url,
                    ],
                    'variant' => $variant ? [
                        'id' => $variant->id,
                        'attribut_principal' => $variant->attribut_principal,
                        'valeur' => $variant->valeur,
                        'stock' => $variant->stock,
                    ] : null,
                    'item_total' => $itemTotal,
                    'stock_available' => $variant ? $variant->stock : $product->stock_total,
                ];
            }

            Log::info('✅ SUMMARY - Returning: ' . $itemsCount . ' items, Total: ' . $totalAmount);

            return response()->json([
                'items_count' => $itemsCount,
                'total_amount' => $totalAmount,
                'estimated_commission' => $estimatedCommission,
                'items' => $items
            ]);

        } catch (\Exception $e) {
            Log::error('❌ SUMMARY ERROR: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors du chargement du panier',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    /**
     * Remove item from cart
     */
    public function removeItem(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'item_key' => 'required|string'
            ]);

            $userId = Auth::id();

            // Parse item key to get produit_id and variante_id
            $parts = explode('_', $validated['item_key']);
            $produitId = $parts[0];
            $varianteId = ($parts[1] ?? 'default') === 'default' ? null : $parts[1];

            // Find the cart item first to check minimum quantity constraints
            $cartItem = AffiliateCartItem::with('produit')
                ->where('user_id', $userId)
                ->where('produit_id', $produitId)
                ->where('variante_id', $varianteId)
                ->first();

            if (!$cartItem) {
                return response()->json([
                    'message' => 'Produit non trouvé dans le panier',
                    'success' => false
                ], 404);
            }

            // Check if removing this item would violate minimum quantity requirement
            $product = $cartItem->produit;
            if ($product->quantite_min && $product->quantite_min > 1) {
                // Check if there are other cart items for the same product
                $totalQtyInCart = AffiliateCartItem::where('user_id', $userId)
                    ->where('produit_id', $produitId)
                    ->sum('qty');

                $qtyAfterRemoval = $totalQtyInCart - $cartItem->qty;

                if ($qtyAfterRemoval > 0 && $qtyAfterRemoval < $product->quantite_min) {
                    return response()->json([
                        'message' => 'Impossible de retirer ce produit. Quantité minimale requise: ' . $product->quantite_min . ' unité' . ($product->quantite_min > 1 ? 's' : '') .
                                     '. Après suppression, il resterait ' . $qtyAfterRemoval . ' unité' . ($qtyAfterRemoval > 1 ? 's' : '') . ' dans le panier.',
                        'minimum_quantity' => $product->quantite_min,
                        'current_total_quantity' => $totalQtyInCart,
                        'quantity_after_removal' => $qtyAfterRemoval,
                        'success' => false
                    ], 422);
                }
            }

            // Proceed with deletion
            $deleted = $cartItem->delete();

            if ($deleted) {
                return response()->json([
                    'message' => 'Produit retiré du panier',
                    'success' => true
                ]);
            }

            return response()->json([
                'message' => 'Erreur lors de la suppression',
                'success' => false
            ], 500);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Données invalides',
                'errors' => $e->errors(),
                'success' => false
            ], 422);
        } catch (\Exception $e) {
            Log::error('❌ REMOVE ITEM ERROR: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    /**
     * Clear entire cart
     */
    public function clear(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            // Delete all cart items for this user
            AffiliateCartItem::where('user_id', $userId)->delete();
            
            return response()->json([
                'message' => 'Panier vidé',
                'success' => true
            ]);

        } catch (\Exception $e) {
            Log::error('❌ CLEAR CART ERROR: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors du vidage du panier',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    /**
     * Update cart item quantity or variant
     */
    public function updateItem(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'item_key' => 'required|string',
                'qty' => 'nullable|integer|min:1|max:100',
                'variante_id' => 'nullable|uuid|exists:produit_variantes,id'
            ]);

            $userId = Auth::id();
            
            // Parse item key to get produit_id and variante_id
            $parts = explode('_', $validated['item_key']);
            $produitId = $parts[0];
            $varianteId = ($parts[1] ?? 'default') === 'default' ? null : $parts[1];

            // Find the cart item
            $cartItem = AffiliateCartItem::where('user_id', $userId)
                ->where('produit_id', $produitId)
                ->where('variante_id', $varianteId)
                ->first();

            if (!$cartItem) {
                return response()->json([
                    'message' => 'Produit non trouvé dans le panier',
                    'success' => false
                ], 404);
            }

            // Update quantity if provided
            if (isset($validated['qty'])) {
                // Check minimum quantity requirement with global cart quantity
                $product = Produit::find($cartItem->produit_id);
                if ($product && $product->quantite_min) {
                    // Calculate total quantity for this product across all cart items (excluding current item)
                    $otherCartItemsQty = AffiliateCartItem::where('user_id', $userId)
                        ->where('produit_id', $cartItem->produit_id)
                        ->where('id', '!=', $cartItem->id)
                        ->sum('qty');

                    $totalQtyAfterUpdate = $otherCartItemsQty + $validated['qty'];

                    if ($totalQtyAfterUpdate < $product->quantite_min) {
                        return response()->json([
                            'message' => 'Quantité minimale requise: ' . $product->quantite_min . ' unité' . ($product->quantite_min > 1 ? 's' : '') .
                                         '. Total après modification: ' . $totalQtyAfterUpdate . ' unité' . ($totalQtyAfterUpdate > 1 ? 's' : '') . '.',
                            'minimum_quantity' => $product->quantite_min,
                            'current_quantity' => $cartItem->qty,
                            'requested_quantity' => $validated['qty'],
                            'other_items_quantity' => $otherCartItemsQty,
                            'total_after_update' => $totalQtyAfterUpdate,
                            'success' => false
                        ], 422);
                    }
                }

                $cartItem->qty = $validated['qty'];
            }

            // Update variant if provided
            if (isset($validated['variante_id'])) {
                $cartItem->variante_id = $validated['variante_id'];
            }

            $cartItem->save();

            return response()->json([
                'message' => 'Panier mis à jour',
                'success' => true
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Données invalides',
                'errors' => $e->errors(),
                'success' => false
            ], 422);
        } catch (\Exception $e) {
            Log::error('❌ UPDATE ITEM ERROR: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    /**
     * Checkout - Create order from cart
     */
    public function checkout(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'receiver_name' => 'required|string|min:3|max:255',
                'receiver_phone' => 'required|string|min:10|max:20',
                'city_id' => 'required|string|exists:shipping_cities,city_id',
                'address_line' => 'required|string|min:10|max:500',
                'note' => 'nullable|string|max:1000',
                'delivery_fee' => 'nullable|numeric|min:0',
                'adjusted_commission' => 'nullable|numeric'
            ]);

            $user = Auth::user();
            
            $cartItems = AffiliateCartItem::with(['produit', 'variante'])
                ->where('user_id', $user->id)
                ->get();

            if ($cartItems->isEmpty()) {
                return response()->json([
                    'message' => 'Le panier est vide',
                    'success' => false
                ], 400);
            }

            $affiliate = $user->profilAffilie;

            if (!$affiliate) {
                return response()->json([
                    'message' => 'Profil affilié non trouvé',
                    'success' => false
                ], 400);
            }

            // Get shipping city
            $shippingCity = ShippingCity::where('city_id', $validated['city_id'])->first();
            if (!$shippingCity) {
                return response()->json([
                    'message' => 'Ville de livraison non trouvée',
                    'success' => false
                ], 400);
            }

            DB::beginTransaction();

            try {
                // Create or find client
                $client = Client::firstOrCreate([
                    'telephone' => $validated['receiver_phone']
                ], [
                    'nom_complet' => $validated['receiver_name'],
                    'email' => null // Optional for COD orders
                ]);

                // Create delivery address
                $adresse = Adresse::create([
                    'client_id' => $client->id,
                    'adresse' => $validated['address_line'],
                    'ville' => $shippingCity->name,
                    'code_postal' => null,
                    'pays' => 'MA',
                    'type' => 'livraison'
                ]);

                // Calculate totals and get boutique from first product
                $totalHT = 0;
                $totalTTC = 0;
                $orderItems = [];
                $boutique_id = null;

                // Get delivery fee from request
                $deliveryFee = $validated['delivery_fee'] ?? 0;

                foreach ($cartItems as $cartItem) {
                    $product = $cartItem->produit;
                    if (!$product) continue;

                    // Get boutique_id from the first product
                    if ($boutique_id === null) {
                        $boutique_id = $product->boutique_id;
                    }

                    // Use sell_price from cart item or fallback to product prix_vente
                    $sellPrice = $cartItem->sell_price ?? $product->prix_vente;
                    $unitPrice = $product->prix_vente; // Keep original for reference
                    $commandType = $cartItem->type_command ?? 'order_sample';

                    // For exchange orders, client doesn't pay for the product (only delivery)
                    // For order_sample, client pays the full price
                    if ($commandType === 'exchange') {
                        $lineTotal = 0; // Exchange items are free for client
                    } else {
                        $lineTotal = $sellPrice * $cartItem->qty; // Normal pricing
                    }

                    $totalHT += $lineTotal;
                    $totalTTC += $lineTotal; // No tax for now

                    $orderItems[] = [
                        'produit_id' => $product->id,
                        'variante_id' => $cartItem->variante_id,
                        'quantite' => $cartItem->qty,
                        'prix_unitaire' => $unitPrice,
                        'sell_price' => $sellPrice, // Original sell price (for reference)
                        'client_paid_price' => $commandType === 'exchange' ? 0 : $sellPrice, // What client actually pays
                        'prix_achat' => $product->prix_achat,
                        'type_command' => $commandType,
                        'total' => $lineTotal // What client pays for this line
                    ];
                }

                // Ensure we have a boutique_id
                if (!$boutique_id) {
                    return response()->json([
                        'message' => 'Aucun produit valide trouvé dans le panier',
                        'success' => false
                    ], 400);
                }

                // Add delivery fee to totals
                $totalHT += $deliveryFee;
                $totalTTC += $deliveryFee;

                // Determine order type based on cart items
                $hasOrderSample = false;
                $hasExchange = false;
                foreach ($cartItems as $cartItem) {
                    $commandType = $cartItem->type_command ?? 'order_sample';
                    if ($commandType === 'order_sample') {
                        $hasOrderSample = true;
                    } elseif ($commandType === 'exchange') {
                        $hasExchange = true;
                    }
                }

                // Set order type: if mixed, prioritize order_sample; if only exchange, set exchange
                $orderType = $hasOrderSample ? 'order_sample' : ($hasExchange ? 'exchange' : 'order_sample');

                // Create order
                $commande = Commande::create([
                    'boutique_id' => $boutique_id,
                    'user_id' => $user->id,
                    'affilie_id' => $affiliate->id,
                    'client_id' => $client->id,
                    'adresse_id' => $adresse->id,
                    'statut' => 'en_attente', // Pre-order status
                    'confirmation_cc' => 'non_contacte',
                    'mode_paiement' => 'cod',
                    'type_command' => $orderType,
                    'total_ht' => $totalHT,
                    'total_ttc' => $totalTTC,
                    'devise' => 'MAD',
                    'notes' => $validated['note']
                ]);

                // Add order items with dynamic pricing and correct commission calculation
                foreach ($orderItems as $item) {
                    $sellPrice = $item['sell_price'] ?? $item['prix_unitaire'];
                    $commandType = $item['type_command'] ?? 'order_sample';

                    // Calculate commission based on command type
                    if ($commandType === 'exchange') {
                        $commissionAmount = 0; // No commission for exchange orders
                    } else {
                        $commissionAmount = max(0, ($sellPrice - $item['prix_achat']) * $item['quantite']);
                    }

                    CommandeArticle::create([
                        'commande_id' => $commande->id,
                        'produit_id' => $item['produit_id'],
                        'variante_id' => $item['variante_id'],
                        'quantite' => $item['quantite'],
                        'prix_unitaire' => $item['prix_unitaire'],
                        'sell_price' => $sellPrice,
                        'commission_amount' => $commissionAmount,
                        'commission_rule_code' => 'default',
                        'type_command' => $commandType,
                        'remise' => 0, // No discount for now
                        'total_ligne' => $item['total']
                    ]);
                }

                // Attach client final data using OrderService
                $orderService = app(OrderService::class);
                $clientFinalData = [
                    'nom_complet' => $validated['receiver_name'],
                    'telephone' => $validated['receiver_phone'],
                    'email' => $client->email,
                    'adresse' => $validated['address_line'],
                    'ville' => $shippingCity->name,
                    'ville_id' => $validated['city_id'],
                    'code_postal' => null,
                    'pays' => 'MA'
                ];

                $result = $orderService->attachClientFinal($commande, $clientFinalData);
                if (!$result['success']) {
                    throw new \Exception('Failed to attach client final data: ' . $result['message']);
                }

                DB::commit();

                // Clear cart after successful order creation
                AffiliateCartItem::where('user_id', $user->id)->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Commande créée avec succès',
                    'data' => [
                        'commande' => [
                            'id' => $commande->id,
                            'total_ttc' => $commande->total_ttc,
                            'statut' => $commande->statut
                        ]
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Données invalides',
                'errors' => $e->errors(),
                'success' => false
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création de la commande',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }
}
