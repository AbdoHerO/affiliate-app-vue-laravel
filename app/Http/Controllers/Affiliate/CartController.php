<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use App\Models\ProduitVariante;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
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
                $variant = ProduitVariante::where('id', $validated['variante_id'])
                    ->where('produit_id', $validated['produit_id'])
                    ->where('actif', true)
                    ->first();

                if (!$variant) {
                    return response()->json([
                        'message' => 'Variante non disponible',
                        'success' => false
                    ], 404);
                }

                if ($variant->stock < $validated['qty']) {
                    return response()->json([
                        'message' => 'Stock insuffisant pour cette variante',
                        'available_stock' => $variant->stock,
                        'success' => false
                    ], 400);
                }
            } else {
                // Check total product stock if no variant specified
                $totalStock = ProduitVariante::where('produit_id', $validated['produit_id'])
                    ->where('actif', true)
                    ->sum('stock');

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
                $itemsCount += $cartItem['qty'];
                $totalAmount += $itemTotal;

                $items[] = [
                    'key' => $itemKey,
                    'produit_id' => $cartItem['produit_id'],
                    'variante_id' => $cartItem['variante_id'],
                    'qty' => $cartItem['qty'],
                    'product' => [
                        'id' => $product->id,
                        'titre' => $product->titre,
                        'prix_vente' => (float) $product->prix_vente,
                        'image' => $product->images->first()?->url,
                    ],
                    'variant' => $variant ? [
                        'id' => $variant->id,
                        'attribut_principal' => $variant->attribut_principal,
                        'valeur' => $variant->valeur,
                        'stock' => $variant->stock,
                    ] : null,
                    'item_total' => $itemTotal,
                ];
            }

            return response()->json([
                'items_count' => $itemsCount,
                'total_amount' => $totalAmount,
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
}
