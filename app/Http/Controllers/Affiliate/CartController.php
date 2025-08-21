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
                'qty' => 'required|integer|min:1|max:100'
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

            // If variant specified, verify it exists and has stock
            if ($validated['variante_id']) {
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
                ->where('variante_id', $validated['variante_id'])
                ->first();

            if ($cartItem) {
                // Update existing item quantity
                $cartItem->qty += $validated['qty'];
                $cartItem->added_at = now();
                $cartItem->save();
                
                Log::info('✅ UPDATED EXISTING CART ITEM - New qty: ' . $cartItem->qty);
            } else {
                // Create new cart item using direct assignment to avoid mass assignment issues
                $cartItem = new AffiliateCartItem();
                $cartItem->user_id = $userId;
                $cartItem->produit_id = $validated['produit_id'];
                $cartItem->variante_id = $validated['variante_id'];
                $cartItem->qty = $validated['qty'];
                $cartItem->added_at = now();
                $cartItem->save();
                
                Log::info('✅ CREATED NEW CART ITEM - ID: ' . $cartItem->id);
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

                $itemTotal = $product->prix_vente * $cartItem->qty;
                $itemCommission = $product->prix_affilie * $cartItem->qty;
                $itemsCount += $cartItem->qty;
                $totalAmount += $itemTotal;
                $estimatedCommission += $itemCommission;

                $items[] = [
                    'key' => $cartItem->produit_id . '_' . ($cartItem->variante_id ?? 'default'),
                    'produit_id' => $cartItem->produit_id,
                    'variante_id' => $cartItem->variante_id,
                    'qty' => $cartItem->qty,
                    'product' => [
                        'id' => $product->id,
                        'titre' => $product->titre,
                        'prix_vente' => (float) $product->prix_vente,
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
            
            // Find and delete the cart item
            $deleted = AffiliateCartItem::where('user_id', $userId)
                ->where('produit_id', $produitId)
                ->where('variante_id', $varianteId)
                ->delete();
            
            if ($deleted) {
                return response()->json([
                    'message' => 'Produit retiré du panier',
                    'success' => true
                ]);
            }

            return response()->json([
                'message' => 'Produit non trouvé dans le panier',
                'success' => false
            ], 404);

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
                'note' => 'nullable|string|max:1000'
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

                foreach ($cartItems as $cartItem) {
                    $product = $cartItem->produit;
                    if (!$product) continue;

                    // Get boutique_id from the first product
                    if ($boutique_id === null) {
                        $boutique_id = $product->boutique_id;
                    }

                    $unitPrice = $product->prix_vente;
                    $lineTotal = $unitPrice * $cartItem->qty;

                    $totalHT += $lineTotal;
                    $totalTTC += $lineTotal; // No tax for now

                    $orderItems[] = [
                        'produit_id' => $product->id,
                        'variante_id' => $cartItem->variante_id,
                        'quantite' => $cartItem->qty,
                        'prix_unitaire' => $unitPrice,
                        'total' => $lineTotal
                    ];
                }

                // Ensure we have a boutique_id
                if (!$boutique_id) {
                    return response()->json([
                        'message' => 'Aucun produit valide trouvé dans le panier',
                        'success' => false
                    ], 400);
                }

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
                    'total_ht' => $totalHT,
                    'total_ttc' => $totalTTC,
                    'devise' => 'MAD',
                    'notes' => $validated['note']
                ]);

                // Add order items
                foreach ($orderItems as $item) {
                    CommandeArticle::create([
                        'commande_id' => $commande->id,
                        'produit_id' => $item['produit_id'],
                        'variante_id' => $item['variante_id'],
                        'quantite' => $item['quantite'],
                        'prix_unitaire' => $item['prix_unitaire'],
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
