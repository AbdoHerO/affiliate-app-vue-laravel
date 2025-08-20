<?php

namespace App\Services;

use App\Models\Produit;
use App\Models\ProduitVariante;
use App\Models\VariantAttribut;
use App\Models\VariantValeur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VariantCombinationService
{
    /**
     * Generate Size × Color combination variants for a product
     *
     * @param string $productId
     * @return array
     */
    public function generateSizeColorCombinations(string $productId): array
    {
        return DB::transaction(function () use ($productId) {
            $product = Produit::findOrFail($productId);
            
            // Get existing variants grouped by type
            $existingVariants = $product->variantes()
                ->with(['attribut', 'valeur'])
                ->get();
            
            $sizeVariants = [];
            $colorVariants = [];
            $combinationVariants = [];
            
            foreach ($existingVariants as $variant) {
                $attributeName = strtolower($variant->nom);
                
                if (in_array($attributeName, ['taille', 'size'])) {
                    $sizeVariants[] = $variant;
                } elseif (in_array($attributeName, ['couleur', 'color'])) {
                    $colorVariants[] = $variant;
                } elseif (strpos($variant->valeur, ' - ') !== false) {
                    // This is already a combination variant
                    $combinationVariants[] = $variant;
                }
            }
            
            // If we already have combination variants, return them
            if (!empty($combinationVariants)) {
                return [
                    'combinations' => $combinationVariants,
                    'message' => 'Using existing combination variants',
                    'created' => 0
                ];
            }
            
            // If we don't have both sizes and colors, can't create combinations
            if (empty($sizeVariants) || empty($colorVariants)) {
                return [
                    'combinations' => [],
                    'message' => 'Need both size and color variants to create combinations',
                    'created' => 0
                ];
            }
            
            // Create Size × Color combinations
            $createdCombinations = [];
            $skipped = 0;
            
            foreach ($colorVariants as $color) {
                foreach ($sizeVariants as $size) {
                    $combinationValue = $color->valeur . ' - ' . $size->valeur;
                    
                    // Check if this combination already exists
                    $existing = $product->variantes()
                        ->where('nom', 'couleur_taille')
                        ->where('valeur', $combinationValue)
                        ->first();
                    
                    if ($existing) {
                        $skipped++;
                        continue;
                    }
                    
                    // Create the combination variant
                    $combination = $product->variantes()->create([
                        'nom' => 'couleur_taille',
                        'valeur' => $combinationValue,
                        'image_url' => $color->image_url, // Use color image
                        'prix_vente_variante' => $size->prix_vente_variante ?? $color->prix_vente_variante,
                        'actif' => true,
                        // Note: We don't set attribut_id/valeur_id for combinations
                        // as they represent multiple attributes
                    ]);
                    
                    $createdCombinations[] = $combination;
                }
            }
            
            Log::info('Size × Color combinations generated', [
                'product_id' => $productId,
                'size_variants' => count($sizeVariants),
                'color_variants' => count($colorVariants),
                'combinations_created' => count($createdCombinations),
                'combinations_skipped' => $skipped,
            ]);
            
            return [
                'combinations' => $createdCombinations,
                'message' => count($createdCombinations) . ' combinations created, ' . $skipped . ' skipped',
                'created' => count($createdCombinations)
            ];
        });
    }
    
    /**
     * Get Size × Color matrix for a product
     *
     * @param string $productId
     * @param string $warehouseId
     * @return array
     */
    public function getSizeColorMatrix(string $productId, string $warehouseId): array
    {
        $product = Produit::findOrFail($productId);
        
        // Get all variants
        $variants = $product->variantes()
            ->with(['stocks' => function ($query) use ($warehouseId) {
                $query->where('entrepot_id', $warehouseId);
            }])
            ->get();
        
        // Separate individual variants and combinations
        $sizeVariants = [];
        $colorVariants = [];
        $combinationVariants = [];
        
        foreach ($variants as $variant) {
            $attributeName = strtolower($variant->nom);
            
            if (in_array($attributeName, ['taille', 'size'])) {
                $sizeVariants[] = $variant;
            } elseif (in_array($attributeName, ['couleur', 'color'])) {
                $colorVariants[] = $variant;
            } elseif ($attributeName === 'couleur_taille' && strpos($variant->valeur, ' - ') !== false) {
                $combinationVariants[] = $variant;
            }
        }
        
        $matrix = [];
        
        if (!empty($combinationVariants)) {
            // Use existing combination variants
            foreach ($combinationVariants as $combination) {
                [$color, $size] = explode(' - ', $combination->valeur, 2);
                $stock = $combination->stocks->first();
                
                $matrix[] = [
                    'variante_id' => $combination->id,
                    'color' => $color,
                    'size' => $size,
                    'color_image' => $combination->image_url,
                    'qty' => $stock ? $stock->qte_disponible : 0,
                    'reserved' => $stock ? $stock->qte_reservee : 0,
                    'available' => $stock ? max(0, $stock->qte_disponible - $stock->qte_reservee) : 0,
                    'is_combination' => true,
                ];
            }
        } elseif (!empty($sizeVariants) && !empty($colorVariants)) {
            // Create virtual matrix from individual variants
            // This will be used to show what combinations COULD be created
            foreach ($colorVariants as $color) {
                foreach ($sizeVariants as $size) {
                    $matrix[] = [
                        'variante_id' => null, // No actual variant yet
                        'color' => $color->valeur,
                        'size' => $size->valeur,
                        'color_image' => $color->image_url,
                        'qty' => 0,
                        'reserved' => 0,
                        'available' => 0,
                        'is_virtual' => true, // Flag to indicate this needs to be created
                    ];
                }
            }
        } elseif (!empty($sizeVariants)) {
            // Size only
            foreach ($sizeVariants as $size) {
                $stock = $size->stocks->first();
                $matrix[] = [
                    'variante_id' => $size->id,
                    'color' => null,
                    'size' => $size->valeur,
                    'color_image' => null,
                    'qty' => $stock ? $stock->qte_disponible : 0,
                    'reserved' => $stock ? $stock->qte_reservee : 0,
                    'available' => $stock ? max(0, $stock->qte_disponible - $stock->qte_reservee) : 0,
                    'is_size_only' => true,
                ];
            }
        } elseif (!empty($colorVariants)) {
            // Color only
            foreach ($colorVariants as $color) {
                $stock = $color->stocks->first();
                $matrix[] = [
                    'variante_id' => $color->id,
                    'color' => $color->valeur,
                    'size' => null,
                    'color_image' => $color->image_url,
                    'qty' => $stock ? $stock->qte_disponible : 0,
                    'reserved' => $stock ? $stock->qte_reservee : 0,
                    'available' => $stock ? max(0, $stock->qte_disponible - $stock->qte_reservee) : 0,
                    'is_color_only' => true,
                ];
            }
        }
        
        return [
            'matrix' => $matrix,
            'has_combinations' => !empty($combinationVariants),
            'has_virtual' => !empty($matrix) && isset($matrix[0]['is_virtual']),
            'size_count' => count($sizeVariants),
            'color_count' => count($colorVariants),
            'combination_count' => count($combinationVariants),
        ];
    }
}
