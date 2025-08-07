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
          {{ $t('admin_produits_view') }}
        </li>
      </ol>
    </nav>

    <!-- Loading State -->
    <div v-if="isLoading" class="text-center py-4">
      <div class="spinner-border" role="status">
        <span class="visually-hidden">{{ $t('common_loading') }}...</span>
      </div>
    </div>

    <!-- Header -->
    <div v-else-if="produit" class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h1 class="h3 mb-0">{{ produit.name }}</h1>
        <div class="text-muted">
          <span class="badge" :class="produit.status === 'active' ? 'bg-success' : 'bg-secondary'">
            {{ $t(produit.status === 'active' ? 'common_active' : 'common_inactive') }}
          </span>
        </div>
      </div>
      <div class="d-flex gap-2">
        <router-link :to="`/admin/produits/${produit.id}/edit`" class="btn btn-primary">
          <i class="fas fa-edit me-2"></i>
          {{ $t('admin_produits_edit') }}
        </router-link>
        <button 
          @click="showDeleteModal = true" 
          class="btn btn-danger"
        >
          <i class="fas fa-trash me-2"></i>
          {{ $t('admin_produits_delete') }}
        </button>
        <router-link to="/admin/produits" class="btn btn-secondary">
          <i class="fas fa-arrow-left me-2"></i>
          {{ $t('common_back') }}
        </router-link>
      </div>
    </div>

    <!-- Tabs -->
    <div v-if="produit" class="row">
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
                <div class="row">
                  <!-- Basic Information -->
                  <div class="col-md-6">
                    <h5 class="mb-3">{{ $t('common_basic_info') }}</h5>
                    
                    <div class="mb-3">
                      <label class="form-label fw-bold">{{ $t('admin_produits_name') }}:</label>
                      <p class="mb-0">{{ produit.name }}</p>
                    </div>

                    <div class="mb-3">
                      <label class="form-label fw-bold">{{ $t('admin_produits_slug') }}:</label>
                      <p class="mb-0">
                        <code>{{ produit.slug }}</code>
                      </p>
                    </div>

                    <div class="mb-3">
                      <label class="form-label fw-bold">{{ $t('admin_produits_boutique') }}:</label>
                      <p class="mb-0">
                        <router-link 
                          :to="`/admin/boutiques/${produit.boutique?.id}`"
                          class="text-decoration-none"
                        >
                          {{ produit.boutique?.nom }}
                        </router-link>
                      </p>
                    </div>

                    <div class="mb-3">
                      <label class="form-label fw-bold">{{ $t('admin_produits_category') }}:</label>
                      <p class="mb-0">
                        <router-link 
                          :to="`/admin/categories/${produit.category?.id}`"
                          class="text-decoration-none"
                        >
                          {{ produit.category?.nom }}
                        </router-link>
                      </p>
                    </div>

                    <div class="mb-3" v-if="produit.description">
                      <label class="form-label fw-bold">{{ $t('admin_produits_description') }}:</label>
                      <p class="mb-0">{{ produit.description }}</p>
                    </div>
                  </div>

                  <!-- Pricing & Stock -->
                  <div class="col-md-6">
                    <h5 class="mb-3">{{ $t('common_pricing_stock') }}</h5>
                    
                    <div class="mb-3" v-if="produit.price_purchase">
                      <label class="form-label fw-bold">{{ $t('admin_produits_price_purchase') }}:</label>
                      <p class="mb-0">
                        <span class="text-success fw-bold">{{ formatPrice(produit.price_purchase) }}</span>
                      </p>
                    </div>

                    <div class="mb-3">
                      <label class="form-label fw-bold">{{ $t('admin_produits_price') }}:</label>
                      <p class="mb-0">
                        <span class="text-primary fw-bold fs-5">{{ formatPrice(produit.price) }}</span>
                      </p>
                    </div>

                    <div class="mb-3" v-if="produit.quantity_min">
                      <label class="form-label fw-bold">{{ $t('admin_produits_quantity_min') }}:</label>
                      <p class="mb-0">{{ produit.quantity_min }}</p>
                    </div>

                    <div class="mb-3">
                      <label class="form-label fw-bold">{{ $t('admin_produits_status') }}:</label>
                      <p class="mb-0">
                        <span class="badge" :class="produit.status === 'active' ? 'bg-success' : 'bg-secondary'">
                          {{ $t(produit.status === 'active' ? 'common_active' : 'common_inactive') }}
                        </span>
                      </p>
                    </div>

                    <div class="mb-3" v-if="produit.notes">
                      <label class="form-label fw-bold">{{ $t('admin_produits_notes') }}:</label>
                      <p class="mb-0 text-muted">{{ produit.notes }}</p>
                    </div>
                  </div>

                  <!-- Metadata -->
                  <div class="col-12">
                    <hr>
                    <h5 class="mb-3">{{ $t('common_metadata') }}</h5>
                    <div class="row">
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label fw-bold">{{ $t('admin_produits_created_at') }}:</label>
                          <p class="mb-0">{{ formatDate(produit.created_at) }}</p>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label fw-bold">{{ $t('admin_produits_updated_at') }}:</label>
                          <p class="mb-0">{{ formatDate(produit.updated_at) }}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Images Tab -->
          <div class="tab-pane fade" id="images" role="tabpanel" aria-labelledby="images-tab">
            <div class="card border-top-0">
              <div class="card-body">
                <div v-if="produit.images && produit.images.length > 0" class="row">
                  <div 
                    v-for="image in produit.images" 
                    :key="image.id"
                    class="col-md-3 col-sm-6 mb-4"
                  >
                    <div class="card">
                      <img 
                        :src="image.image_url" 
                        :alt="`Product image ${image.order}`"
                        class="card-img-top"
                        style="height: 200px; object-fit: cover;"
                      >
                      <div class="card-body p-2">
                        <small class="text-muted">
                          {{ $t('admin_produits_image_order') }}: {{ image.order }}
                        </small>
                      </div>
                    </div>
                  </div>
                </div>
                <div v-else class="text-center py-4">
                  <i class="fas fa-images fa-3x text-muted mb-3"></i>
                  <p class="text-muted">{{ $t('admin_produits_no_images') }}</p>
                  <router-link 
                    :to="`/admin/produits/${produit.id}/edit`" 
                    class="btn btn-primary"
                  >
                    <i class="fas fa-plus me-2"></i>
                    {{ $t('admin_produits_upload_image') }}
                  </router-link>
                </div>
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

    <!-- Delete Modal -->
    <div 
      v-if="showDeleteModal && produit" 
      class="modal fade show d-block" 
      tabindex="-1" 
      style="background-color: rgba(0,0,0,0.5);"
    >
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ $t('admin_produits_delete_title') }}</h5>
            <button 
              @click="showDeleteModal = false" 
              type="button" 
              class="btn-close"
            ></button>
          </div>
          <div class="modal-body">
            <p>{{ $t('admin_produits_delete_confirm', { name: produit.name }) }}</p>
          </div>
          <div class="modal-footer">
            <button 
              @click="showDeleteModal = false" 
              type="button" 
              class="btn btn-secondary"
            >
              {{ $t('common_cancel') }}
            </button>
            <button 
              @click="handleDelete" 
              type="button" 
              class="btn btn-danger"
              :disabled="isDeleting"
            >
              <span v-if="isDeleting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
              <i v-else class="fas fa-trash me-2"></i>
              {{ $t('admin_produits_delete') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useProduitStore } from '@/stores/admin/produits'

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

const isLoading = ref(true)
const isDeleting = ref(false)
const showDeleteModal = ref(false)

const produit = computed(() => produitStore.currentProduit)

const formatPrice = (price: number | string) => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR'
  }).format(Number(price))
}

const formatDate = (date: string) => {
  return new Intl.DateTimeFormat('fr-FR', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(new Date(date))
}

const handleDelete = async () => {
  try {
    isDeleting.value = true
    await produitStore.deleteProduit(Number(route.params.id))
    router.push('/admin/produits')
  } catch (error) {
    console.error('Error deleting produit:', error)
  } finally {
    isDeleting.value = false
    showDeleteModal.value = false
  }
}

onMounted(async () => {
  try {
    await produitStore.fetchProduit(Number(route.params.id))
  } catch (error) {
    console.error('Error loading produit:', error)
  } finally {
    isLoading.value = false
  }
})
</script>
