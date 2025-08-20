<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use App\Models\ProduitVariante;
use App\Models\Client;
use App\Models\Adresse;
use App\Models\Commande;
use App\Models\CommandeArticle;
use App\Models\ShippingCity;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
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

            // Get or create cart session
            $cart = Session::get('affiliate_cart', []);
            
            // Create cart item key
            $itemKey = $validated['produit_id'] . '_' . ($validated['variante_id'] ?? 'default');
            
            // Add or update item in cart
            if (isset($cart[$itemKey])) {
                $cart[$itemKey]['qty'] += $validated['qty'];
            } else {
                $cart[$itemKey] = [
                    'produit_id' => $validated['produit_id'],
                    'variante_id' => $validated['variante_id'],
                    'qty' => $validated['qty'],
                    'added_at' => now()->toISOString()
                ];
            }

            // Save cart to session
            Session::put('affiliate_cart', $cart);

            return response()->json([
                'message' => 'Produit ajouté au panier',
                'cart_item' => $cart[$itemKey],
                'success' => true
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Données invalides',
                'errors' => $e->errors(),
                'success' => false
            ], 422);
        } catch (\Exception $e) {
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
            $cart = Session::get('affiliate_cart', []);
            
            if (empty($cart)) {
                return response()->json([
                    'items_count' => 0,
                    'total_amount' => 0,
                    'items' => []
                ]);
            }

            $itemsCount = 0;
            $totalAmount = 0;
            $estimatedCommission = 0;
            $items = [];

            foreach ($cart as $itemKey => $cartItem) {
                // Get product details
                $product = Produit::with(['images', 'variantes'])
                    ->where('id', $cartItem['produit_id'])
                    ->where('actif', true)
                    ->first();

                if (!$product) {
                    continue; // Skip if product no longer exists or is inactive
                }

                $variant = null;
                if ($cartItem['variante_id']) {
                    $variant = $product->variantes->where('id', $cartItem['variante_id'])->first();
                    if (!$variant || !$variant->actif) {
                        continue; // Skip if variant no longer exists or is inactive
                    }
                }

                $itemTotal = $product->prix_vente * $cartItem['qty'];
                $itemCommission = $product->prix_affilie * $cartItem['qty'];
                $itemsCount += $cartItem['qty'];
                $totalAmount += $itemTotal;
                $estimatedCommission += $itemCommission;

                $items[] = [
                    'key' => $itemKey,
                    'produit_id' => $cartItem['produit_id'],
                    'variante_id' => $cartItem['variante_id'],
                    'qty' => $cartItem['qty'],
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

            return response()->json([
                'items_count' => $itemsCount,
                'total_amount' => $totalAmount,
                'estimated_commission' => $estimatedCommission,
                'items' => $items
            ]);

        } catch (\Exception $e) {
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

            $cart = Session::get('affiliate_cart', []);
            
            if (isset($cart[$validated['item_key']])) {
                unset($cart[$validated['item_key']]);
                Session::put('affiliate_cart', $cart);
                
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
            Session::forget('affiliate_cart');
            
            return response()->json([
                'message' => 'Panier vidé',
                'success' => true
            ]);

        } catch (\Exception $e) {
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

            $cart = Session::get('affiliate_cart', []);

            if (!isset($cart[$validated['item_key']])) {
                return response()->json([
                    'message' => 'Produit non trouvé dans le panier',
                    'success' => false
                ], 404);
            }

            // Update quantity if provided
            if (isset($validated['qty'])) {
                $cart[$validated['item_key']]['qty'] = $validated['qty'];
            }

            // Update variant if provided
            if (isset($validated['variante_id'])) {
                $cart[$validated['item_key']]['variante_id'] = $validated['variante_id'];
            }

            Session::put('affiliate_cart', $cart);

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

            $cart = Session::get('affiliate_cart', []);

            if (empty($cart)) {
                return response()->json([
                    'message' => 'Le panier est vide',
                    'success' => false
                ], 400);
            }

            $user = Auth::user();
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

                // Calculate totals
                $totalHT = 0;
                $totalTTC = 0;
                $orderItems = [];

                foreach ($cart as $itemKey => $cartItem) {
                    $product = Produit::find($cartItem['produit_id']);
                    if (!$product) continue;

                    $variant = null;
                    if ($cartItem['variante_id']) {
                        $variant = ProduitVariante::find($cartItem['variante_id']);
                    }

                    $unitPrice = $product->prix_vente;
                    $lineTotal = $unitPrice * $cartItem['qty'];

                    $totalHT += $lineTotal;
                    $totalTTC += $lineTotal; // No tax for now

                    $orderItems[] = [
                        'produit_id' => $product->id,
                        'variante_id' => $cartItem['variante_id'],
                        'quantite' => $cartItem['qty'],
                        'prix_unitaire' => $unitPrice,
                        'total' => $lineTotal
                    ];
                }

                // Create order
                $commande = Commande::create([
                    'boutique_id' => $affiliate->boutique_id,
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
                        'total' => $item['total']
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
                Session::forget('affiliate_cart');

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
