# 🚨 URGENT: Vue.js + Laravel Product Variants & Images Issue

## 📋 Context
Working on a **Vue.js 3 + Laravel** affiliate platform with **Vuetify 3** components. Critical issue with product variants (colors/sizes) not displaying in catalog cards and images not loading properly.

## 🔥 Core Problem
**Product variants (colors & sizes) are completely missing from catalog cards** despite:
- Variants existing in the database
- API returning variant data
- Store mapping functions processing variants
- Component props receiving data

## 🎯 Specific Issues

### 1. **Variants Not Displaying**
- **Color chips**: Should show color swatches but cards appear empty
- **Size chips**: Should show size options (S, M, L, XL) but nothing renders
- **Variant selection**: Cannot select colors/sizes from catalog cards
- **Stock management**: Variant-specific stock not respected

### 2. **Image Loading Problems**
- **Main images**: Product images not loading/displaying properly
- **Variant images**: Color selection should update image but doesn't work
- **Fallback images**: Placeholder images not showing when main image fails

### 3. **Direct Ordering Blocked**
- **Cannot order from cards**: Must use product drawer instead of direct card ordering
- **Variant requirement**: Products with variants cannot be added to cart without selection
- **Quantity limits**: Max quantity not based on selected variant stock

## 🛠️ Technical Stack
- **Frontend**: Vue.js 3 + Composition API + TypeScript
- **UI Framework**: Vuetify 3
- **State Management**: Pinia stores
- **Backend**: Laravel API
- **Build Tool**: Vite

## 📁 Key Files to Focus On

### Primary Components:
```
resources/ts/components/affiliate/catalogue/CatalogueCard.vue
resources/ts/stores/affiliate/catalogue.ts
resources/ts/pages/affiliate/catalogue/index.vue
```

### Data Flow:
```
API Response → Store Mapping → Normalized Product → Card Component → UI Display
```

## 🔍 Current Implementation Analysis

### Store Mapping Function:
```typescript
// In catalogue.ts - mapProductToNormalized()
const mapProductToNormalized = (product: CatalogueProduct): NormalizedProduct => {
  // Processes variants into:
  // - colors: Array<{ name, swatch, image_url }>
  // - sizes: Array<string>
  // - variants: { sizes: [], colors: [] }
}
```

### Card Component Structure:
```vue
<!-- CatalogueCard.vue -->
<template>
  <!-- Product Image -->
  <VImg :src="currentImage" />
  
  <!-- Color Variants (NOT SHOWING) -->
  <VChip v-for="color in colorSwatches" />
  
  <!-- Size Variants (NOT SHOWING) -->
  <VChip v-for="size in sizeChips" />
  
  <!-- Add to Cart -->
  <VBtn @click="handleAddToCart" />
</template>
```

## 📊 Expected Data Structure

### API Response (Raw):
```json
{
  "data": [
    {
      "id": "uuid",
      "titre": "Product Name", 
      "images": [{"url": "image1.jpg"}],
      "variantes": [
        {
          "id": "variant-id",
          "attribut_principal": "couleur",
          "valeur": "Rouge",
          "stock": 10,
          "color": "#FF0000",
          "image_url": "red-variant.jpg"
        },
        {
          "id": "variant-id-2", 
          "attribut_principal": "taille",
          "valeur": "M",
          "stock": 5
        }
      ]
    }
  ]
}
```

### Normalized Product (Expected):
```typescript
interface NormalizedProduct {
  id: string
  titre: string
  mainImage: string
  colors: Array<{ name: string; swatch?: string; image_url?: string }>
  sizes: Array<string>
  variants: {
    sizes: Array<{ id: string; value: string; stock: number }>
    colors: Array<{ id: string; value: string; color?: string; stock: number }>
  }
}
```

## 🎯 Requirements

### 1. **Fix Variant Display**
- [ ] Color chips must show with proper swatches
- [ ] Size chips must display available sizes
- [ ] Only show variants with stock > 0
- [ ] Handle combined variants (e.g., "Red - Medium")

### 2. **Fix Image Loading**
- [ ] Product main images must load and display
- [ ] Variant-specific images must update on color selection
- [ ] Proper fallback when images fail to load
- [ ] Loading states and error handling

### 3. **Enable Direct Card Ordering**
- [ ] Allow variant selection directly from cards
- [ ] Show max quantity based on selected variant
- [ ] Add to cart with selected variant data
- [ ] Visual feedback for selection status

### 4. **Variant Selection Logic**
- [ ] Independent color and size selection
- [ ] Image updates when color changes
- [ ] Stock validation per variant
- [ ] Clear selection indicators

## 🔧 Implementation Requirements

### Component Features:
```vue
<!-- Expected functionality -->
<CatalogueCard :product="normalizedProduct">
  <!-- Color Selection -->
  <VChip 
    v-for="color in availableColors"
    :color="selectedColor === color.id ? 'primary' : 'default'"
    @click="selectColor(color.id)"
  >
    <VIcon :style="{ color: color.swatch }" />
    {{ color.name }}
  </VChip>
  
  <!-- Size Selection -->
  <VChip
    v-for="size in availableSizes" 
    :color="selectedSize === size.id ? 'primary' : 'default'"
    @click="selectSize(size.id)"
  >
    {{ size.value }}
  </VChip>
  
  <!-- Quantity with variant-specific max -->
  <div class="quantity-selector">
    <VBtn @click="decreaseQty" :disabled="qty <= 1" />
    <span>{{ qty }}</span>
    <VBtn @click="increaseQty" :disabled="qty >= maxVariantStock" />
  </div>
  
  <!-- Add to cart (enabled only when variants selected) -->
  <VBtn 
    @click="addToCart"
    :disabled="!canAddToCart"
  >
    Add to Cart
  </VBtn>
</CatalogueCard>
```

### Computed Properties Needed:
```typescript
const availableColors = computed(() => /* variants with stock > 0 */)
const availableSizes = computed(() => /* variants with stock > 0 */)
const selectedVariant = computed(() => /* resolve by color+size selection */)
const maxVariantStock = computed(() => /* stock of selected variant */)
const canAddToCart = computed(() => /* validation logic */)
```

## 🚀 Success Criteria

### Visual Requirements:
- [ ] **Color chips** display with color swatches/icons
- [ ] **Size chips** show available sizes (S, M, L, XL, etc.)
- [ ] **Product images** load immediately without stuck spinners
- [ ] **Variant images** update when colors are selected
- [ ] **Selection feedback** - selected variants highlighted
- [ ] **Stock indicators** - show max quantity per variant

### Functional Requirements:
- [ ] **Click color chip** → image updates + color selected
- [ ] **Click size chip** → size selected + stock updated
- [ ] **Quantity controls** → respect variant-specific stock limits
- [ ] **Add to cart** → works with selected variant data
- [ ] **No variants** → still allows direct add to cart
- [ ] **Mixed requirements** → validate both color and size when needed

## 💡 Additional Context

### Current Debug Setup:
- Console logging added to store mapping function
- Component debugging for variant computation
- Image loading state tracking

### Known Working Reference:
- `CatalogueCard__old.vue` has working variant logic
- Can reference for data structure patterns
- Contains working color/size selection implementation

## 🎯 **URGENT PRIORITY**
This is blocking core affiliate platform functionality. Users cannot:
- See product variants in catalog
- Select colors/sizes from cards  
- Add variant products to cart
- See product images properly

Please provide a complete, working solution that restores full variant functionality to the catalog cards with proper image handling and direct ordering capabilities.
