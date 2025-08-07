<template>
  <div class="container-fluid">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">
          <router-link to="/admin" class="text-decoration-none">
            {{ $t('breadcrumb_home') }}
          </router-link>
        </li>
        <li class="breadcrumb-item">
          <router-link to="/admin/produits" class="text-decoration-none">
            {{ $t('admin_produits_title') }}
          </router-link>
        </li>
        <li class="breadcrumb-item active" aria-current="page">
          {{ $t('admin_produits_edit') }}
        </li>
      </ol>
    </nav>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="h3 mb-0">{{ $t('admin_produits_edit') }}</h1>
      <div class="d-flex gap-2">
        <router-link :to="`/admin/produits/${route.params.id}`" class="btn btn-outline-primary">
          <i class="fas fa-eye me-2"></i>
          {{ $t('admin_produits_view') }}
        </router-link>
        <router-link to="/admin/produits" class="btn btn-secondary">
          <i class="fas fa-arrow-left me-2"></i>
          {{ $t('common_back') }}
        </router-link>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoadingProduit" class="text-center py-4">
      <div class="spinner-border" role="status">
        <span class="visually-hidden">{{ $t('common_loading') }}...</span>
      </div>
    </div>

    <!-- Tabs -->
    <div v-else-if="produit" class="row">
      <div class="col-12">
        <ul class="nav nav-tabs" id="produitTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button 
              class="nav-link active" 
              id="details-tab" 
              data-bs-toggle="tab" 
              data-bs-target="#details" 
              type="button" 
              role="tab" 
              aria-controls="details" 
              aria-selected="true"
            >
              <i class="fas fa-info-circle me-2"></i>
              {{ $t('admin_produits_details') }}
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button 
              class="nav-link" 
              id="images-tab" 
              data-bs-toggle="tab" 
              data-bs-target="#images" 
              type="button" 
              role="tab" 
              aria-controls="images" 
              aria-selected="false"
            >
              <i class="fas fa-images me-2"></i>
              {{ $t('admin_produits_images') }}
              <span class="badge bg-secondary ms-2">{{ produit.images?.length || 0 }}</span>
            </button>
          </li>
        </ul>

        <div class="tab-content" id="produitTabsContent">
          <!-- Details Tab -->
          <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
            <div class="card border-top-0">
              <div class="card-body">
                <form @submit.prevent="handleSubmit" class="needs-validation" novalidate>
                  <div class="row">
                    <!-- Boutique -->
                    <div class="col-md-6 mb-3">
                      <label for="boutique_id" class="form-label">
                        {{ $t('admin_produits_boutique') }} <span class="text-danger">*</span>
                      </label>
                      <select 
                        id="boutique_id" 
                        v-model="form.boutique_id"
                        class="form-select"
                        :class="{ 'is-invalid': errors.boutique_id }"
                        required
                      >
                        <option value="">{{ $t('common_select') }}...</option>
                        <option 
                          v-for="boutique in boutiques" 
                          :key="boutique.id" 
                          :value="boutique.id"
                        >
                          {{ boutique.nom }}
                        </option>
                      </select>
                      <div v-if="errors.boutique_id" class="invalid-feedback">
                        {{ errors.boutique_id[0] }}
                      </div>
                    </div>

                    <!-- Category -->
                    <div class="col-md-6 mb-3">
                      <label for="category_id" class="form-label">
                        {{ $t('admin_produits_category') }} <span class="text-danger">*</span>
                      </label>
                      <select 
                        id="category_id" 
                        v-model="form.category_id"
                        class="form-select"
                        :class="{ 'is-invalid': errors.category_id }"
                        required
                      >
                        <option value="">{{ $t('common_select') }}...</option>
                        <option 
                          v-for="category in categories" 
                          :key="category.id" 
                          :value="category.id"
                        >
                          {{ category.nom }}
                        </option>
                      </select>
                      <div v-if="errors.category_id" class="invalid-feedback">
                        {{ errors.category_id[0] }}
                      </div>
                    </div>

                    <!-- Name -->
                    <div class="col-md-6 mb-3">
                      <label for="name" class="form-label">
                        {{ $t('admin_produits_name') }} <span class="text-danger">*</span>
                      </label>
                      <input 
                        id="name" 
                        v-model="form.name"
                        type="text" 
                        class="form-control"
                        :class="{ 'is-invalid': errors.name }"
                        :placeholder="$t('admin_produits_name')"
                        required
                      >
                      <div v-if="errors.name" class="invalid-feedback">
                        {{ errors.name[0] }}
                      </div>
                    </div>

                    <!-- Slug -->
                    <div class="col-md-6 mb-3">
                      <label for="slug" class="form-label">
                        {{ $t('admin_produits_slug') }}
                      </label>
                      <input 
                        id="slug" 
                        v-model="form.slug"
                        type="text" 
                        class="form-control"
                        :class="{ 'is-invalid': errors.slug }"
                        :placeholder="$t('admin_produits_slug')"
                      >
                      <div v-if="errors.slug" class="invalid-feedback">
                        {{ errors.slug[0] }}
                      </div>
                      <div class="form-text">{{ $t('common_slug_auto_generate') }}</div>
                    </div>

                    <!-- Description -->
                    <div class="col-12 mb-3">
                      <label for="description" class="form-label">
                        {{ $t('admin_produits_description') }}
                      </label>
                      <textarea 
                        id="description" 
                        v-model="form.description"
                        class="form-control"
                        :class="{ 'is-invalid': errors.description }"
                        :placeholder="$t('admin_produits_description')"
                        rows="3"
                      ></textarea>
                      <div v-if="errors.description" class="invalid-feedback">
                        {{ errors.description[0] }}
                      </div>
                    </div>

                    <!-- Purchase Price -->
                    <div class="col-md-6 mb-3">
                      <label for="price_purchase" class="form-label">
                        {{ $t('admin_produits_price_purchase') }}
                      </label>
                      <div class="input-group">
                        <input 
                          id="price_purchase" 
                          v-model="form.price_purchase"
                          type="number" 
                          step="0.01"
                          min="0"
                          class="form-control"
                          :class="{ 'is-invalid': errors.price_purchase }"
                          :placeholder="$t('admin_produits_price_purchase')"
                        >
                        <span class="input-group-text">€</span>
                        <div v-if="errors.price_purchase" class="invalid-feedback">
                          {{ errors.price_purchase[0] }}
                        </div>
                      </div>
                    </div>

                    <!-- Sale Price -->
                    <div class="col-md-6 mb-3">
                      <label for="price" class="form-label">
                        {{ $t('admin_produits_price') }} <span class="text-danger">*</span>
                      </label>
                      <div class="input-group">
                        <input 
                          id="price" 
                          v-model="form.price"
                          type="number" 
                          step="0.01"
                          min="0"
                          class="form-control"
                          :class="{ 'is-invalid': errors.price }"
                          :placeholder="$t('admin_produits_price')"
                          required
                        >
                        <span class="input-group-text">€</span>
                        <div v-if="errors.price" class="invalid-feedback">
                          {{ errors.price[0] }}
                        </div>
                      </div>
                    </div>

                    <!-- Status -->
                    <div class="col-md-6 mb-3">
                      <label for="status" class="form-label">
                        {{ $t('admin_produits_status') }} <span class="text-danger">*</span>
                      </label>
                      <select 
                        id="status" 
                        v-model="form.status"
                        class="form-select"
                        :class="{ 'is-invalid': errors.status }"
                        required
                      >
                        <option value="active">{{ $t('common_active') }}</option>
                        <option value="inactive">{{ $t('common_inactive') }}</option>
                      </select>
                      <div v-if="errors.status" class="invalid-feedback">
                        {{ errors.status[0] }}
                      </div>
                    </div>

                    <!-- Minimum Quantity -->
                    <div class="col-md-6 mb-3">
                      <label for="quantity_min" class="form-label">
                        {{ $t('admin_produits_quantity_min') }}
                      </label>
                      <input 
                        id="quantity_min" 
                        v-model="form.quantity_min"
                        type="number" 
                        min="0"
                        class="form-control"
                        :class="{ 'is-invalid': errors.quantity_min }"
                        :placeholder="$t('admin_produits_quantity_min')"
                      >
                      <div v-if="errors.quantity_min" class="invalid-feedback">
                        {{ errors.quantity_min[0] }}
                      </div>
                    </div>

                    <!-- Admin Notes -->
                    <div class="col-12 mb-3">
                      <label for="notes" class="form-label">
                        {{ $t('admin_produits_notes') }}
                      </label>
                      <textarea 
                        id="notes" 
                        v-model="form.notes"
                        class="form-control"
                        :class="{ 'is-invalid': errors.notes }"
                        :placeholder="$t('admin_produits_notes')"
                        rows="3"
                      ></textarea>
                      <div v-if="errors.notes" class="invalid-feedback">
                        {{ errors.notes[0] }}
                      </div>
                    </div>
                  </div>

                  <!-- Submit Buttons -->
                  <div class="d-flex justify-content-end gap-2">
                    <router-link to="/admin/produits" class="btn btn-secondary">
                      {{ $t('common_cancel') }}
                    </router-link>
                    <button 
                      type="submit" 
                      class="btn btn-primary"
                      :disabled="isLoading"
                    >
                      <span v-if="isLoading" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                      <i v-else class="fas fa-save me-2"></i>
                      {{ $t('admin_produits_update') }}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- Images Tab -->
          <div class="tab-pane fade" id="images" role="tabpanel" aria-labelledby="images-tab">
            <div class="card border-top-0">
              <div class="card-body">
                <ProductImageGallery 
                  :produit-id="produit.id"
                  :images="produit.images || []"
                  @images-updated="handleImagesUpdated"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else class="alert alert-danger">
      {{ $t('common_error_loading') }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useProduitStore } from '@/stores/admin/produits'
import { useBoutiqueStore, type Boutique } from '@/stores/admin/boutiques'
import { useCategoriesStore, type Category } from '@/stores/admin/categories'
import ProductImageGallery from '@/components/admin/ProductImageGallery.vue'
import type { UpdateProduitForm } from '@/types/admin/produits'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const produitStore = useProduitStore()
const boutiqueStore = useBoutiqueStore()
const categoryStore = useCategoriesStore()

const isLoading = ref(false)
const isLoadingProduit = ref(true)
const errors = ref<Record<string, string[]>>({})

const form = reactive<UpdateProduitForm>({
  boutique_id: '',
  category_id: '',
  name: '',
  slug: '',
  description: '',
  price_purchase: null,
  price: '',
  status: 'active',
  quantity_min: null,
  notes: ''
})

const boutiques = ref<Boutique[]>([])
const categories = ref<Category[]>([])

const produit = computed(() => produitStore.currentProduit)

const handleSubmit = async () => {
  try {
    isLoading.value = true
    errors.value = {}

    await produitStore.updateProduit(Number(route.params.id), form)
    
    router.push('/admin/produits')
  } catch (error: any) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors
    }
    console.error('Error updating produit:', error)
  } finally {
    isLoading.value = false
  }
}

const handleImagesUpdated = () => {
  // Refresh produit data to update images list
  produitStore.fetchProduit(Number(route.params.id))
}

onMounted(async () => {
  try {
    isLoadingProduit.value = true
    
    // Load produit details
    await produitStore.fetchProduit(Number(route.params.id))
    
    // Load boutiques and categories for dropdowns
    await Promise.all([
      boutiqueStore.fetchBoutiques(),
      categoryStore.fetchCategories()
    ])
    
    boutiques.value = boutiqueStore.boutiques
    categories.value = categoryStore.categories
    
    // Populate form with produit data
    if (produit.value) {
      Object.assign(form, {
        boutique_id: produit.value.boutique_id,
        category_id: produit.value.category_id,
        name: produit.value.name,
        slug: produit.value.slug,
        description: produit.value.description,
        price_purchase: produit.value.price_purchase,
        price: produit.value.price,
        status: produit.value.status,
        quantity_min: produit.value.quantity_min,
        notes: produit.value.notes
      })
    }
  } catch (error) {
    console.error('Error loading produit:', error)
  } finally {
    isLoadingProduit.value = false
  }
})
</script>
