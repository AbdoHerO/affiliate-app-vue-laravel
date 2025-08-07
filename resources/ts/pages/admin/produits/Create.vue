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
          {{ $t('admin_produits_create') }}
        </li>
      </ol>
    </nav>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="h3 mb-0">{{ $t('admin_produits_create') }}</h1>
      <router-link to="/admin/produits" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>
        {{ $t('common_back') }}
      </router-link>
    </div>

    <!-- Form Card -->
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">{{ $t('admin_produits_details') }}</h5>
      </div>
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
              {{ $t('admin_produits_create') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useProduitStore } from '@/stores/admin/produits'
import { useBoutiqueStore, type Boutique } from '@/stores/admin/boutiques'
import { useCategoriesStore, type Category } from '@/stores/admin/categories'
import type { CreateProduitForm } from '@/types/admin/produits'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const router = useRouter()
const { t } = useI18n()
const produitStore = useProduitStore()
const boutiqueStore = useBoutiqueStore()
const categoryStore = useCategoriesStore()

const isLoading = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = reactive<CreateProduitForm>({
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

const handleSubmit = async () => {
  try {
    isLoading.value = true
    errors.value = {}

    await produitStore.createProduit(form)
    
    router.push('/admin/produits')
  } catch (error: any) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors
    }
    console.error('Error creating produit:', error)
  } finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  try {
    // Load boutiques and categories for dropdowns
    await boutiqueStore.fetchBoutiques()
    await categoryStore.fetchCategories()
    
    boutiques.value = boutiqueStore.boutiques
    categories.value = categoryStore.categories
  } catch (error) {
    console.error('Error loading data:', error)
  }
})
</script>
