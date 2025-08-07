<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs
      :items="[
        { title: $t('breadcrumb_home'), to: '/' },
        { title: $t('admin_produits_title'), to: '/admin/produits' },
        { title: produit?.titre || $t('admin_produits_view') },
      ]"
      class="pa-0 mb-4"
    />

    <!-- Loading State -->
    <VRow v-if="isLoading" justify="center">
      <VCol cols="12" class="text-center">
        <VProgressCircular indeterminate size="64" />
        <div class="mt-4">{{ $t('admin_common_loading') }}...</div>
      </VCol>
    </VRow>

    <!-- Main Content -->
    <VRow v-else-if="produit">
      <!-- Actions Header -->
      <VCol cols="12" class="mb-4">
        <div class="d-flex justify-space-between align-items-center">
          <div>
            <h1 class="text-h4">{{ produit.titre }}</h1>
            <VChip
              :color="produit.actif ? 'success' : 'default'"
              variant="flat"
              size="small"
              class="mt-2"
              >
                {{ produit.actif ? $t('admin_common_active') : $t('admin_common_inactive') }}
              </VChip>
            </div>
            <div class="d-flex ga-2">
              <VBtn
                :to="`/admin/produits/${produit.id}/edit`"
                color="primary"
                variant="flat"
                prepend-icon="mdi-pencil"
              >
                {{ $t('admin_common_edit') }}
              </VBtn>
              <VBtn
                color="error"
                variant="outlined"
                prepend-icon="mdi-delete"
                @click="showDeleteDialog = true"
              >
                {{ $t('admin_common_delete') }}
              </VBtn>
              <VBtn
                to="/admin/produits"
                variant="outlined"
                prepend-icon="mdi-arrow-left"
              >
                {{ $t('admin_common_back') }}
              </VBtn>
            </div>
          </div>
        </VCol>
      </VRow>

      <!-- Tabs -->
      <VRow>
        <VCol cols="12">
          <VTabs v-model="activeTab">
            <VTab value="details">
              <VIcon start>mdi-information</VIcon>
              {{ $t('admin_produits_details') }}
            </VTab>
            <VTab value="images">
              <VIcon start>mdi-image-multiple</VIcon>
              {{ $t('admin_produits_images') }}
              <VChip class="ml-2" size="x-small">{{ produit.images?.length || 0 }}</VChip>
            </VTab>
          </VTabs>

          <VTabsWindow v-model="activeTab">
            <!-- Details Tab -->
            <VTabsWindowItem value="details">
              <VCard>
                <VCardTitle>
                  {{ $t('admin_produits_details') }}
                </VCardTitle>
                
                <VDivider />
                
                <VCardText>
                  <VRow>
                    <!-- Basic Information -->
                    <VCol cols="12" md="6">
                      <h5 class="mb-3">{{ $t('admin_common_basic_info') }}</h5>
                      
                      <div class="mb-4">
                        <VTextField
                          :label="$t('admin_produits_titre')"
                          :model-value="produit.titre"
                          readonly
                          variant="outlined"
                        />
                      </div>

                      <div class="mb-4">
                        <VTextField
                          :label="$t('admin_produits_slug')"
                          :model-value="produit.slug"
                          readonly
                          variant="outlined"
                        />
                      </div>

                      <div class="mb-4">
                        <VTextField
                          :label="$t('admin_produits_boutique')"
                          :model-value="produit.boutique?.nom"
                          readonly
                          variant="outlined"
                        />
                      </div>

                      <div class="mb-4" v-if="produit.categorie">
                        <VTextField
                          :label="$t('admin_produits_categorie')"
                          :model-value="produit.categorie?.nom"
                          readonly
                          variant="outlined"
                        />
                      </div>

                      <div v-if="produit.description">
                        <VTextarea
                          :label="$t('admin_produits_description')"
                          :model-value="produit.description"
                          readonly
                          variant="outlined"
                          rows="4"
                        />
                      </div>
                    </VCol>

                    <!-- Pricing -->
                    <VCol cols="12" md="6">
                      <h5 class="mb-3">{{ $t('admin_produits_pricing') }}</h5>
                      
                      <div class="mb-4">
                        <VTextField
                          :label="$t('admin_produits_prix_achat')"
                          :model-value="formatPrice(produit.prix_achat)"
                          readonly
                          variant="outlined"
                        />
                      </div>

                      <div class="mb-4">
                        <VTextField
                          :label="$t('admin_produits_prix_vente')"
                          :model-value="formatPrice(produit.prix_vente)"
                          readonly
                          variant="outlined"
                          class="text-primary"
                        />
                      </div>

                      <div class="mb-4" v-if="produit.prix_affilie">
                        <VTextField
                          :label="$t('admin_produits_prix_affilie')"
                          :model-value="formatPrice(produit.prix_affilie)"
                          readonly
                          variant="outlined"
                        />
                      </div>

                      <div>
                        <VSwitch
                          :label="$t('admin_produits_actif')"
                          :model-value="produit.actif"
                          readonly
                          color="primary"
                        />
                      </div>
                    </VCol>

                    <!-- Metadata -->
                    <VCol cols="12">
                      <VDivider class="my-4" />
                      <h5 class="mb-3">{{ $t('admin_common_metadata') }}</h5>
                      <VRow>
                        <VCol cols="12" md="6">
                          <VTextField
                            :label="$t('admin_common_created_at')"
                            :model-value="formatDate(produit.created_at)"
                            readonly
                            variant="outlined"
                          />
                        </VCol>
                        <VCol cols="12" md="6">
                          <VTextField
                            :label="$t('admin_common_updated_at')"
                            :model-value="formatDate(produit.updated_at)"
                            readonly
                            variant="outlined"
                          />
                        </VCol>
                      </VRow>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VTabsWindowItem>

            <!-- Images Tab -->
            <VTabsWindowItem value="images">
              <VCard>
                <VCardTitle>
                  {{ $t('admin_produits_images') }}
                </VCardTitle>
                
                <VDivider />
                
                <VCardText>
                  <VRow v-if="produit.images && produit.images.length > 0">
                    <VCol 
                      v-for="image in produit.images" 
                      :key="image.id"
                      cols="12" md="3" sm="6"
                    >
                      <VCard>
                        <VImg
                          :src="image.url" 
                          :alt="`Product image ${image.ordre}`"
                          height="200"
                          cover
                        />
                        <VCardText class="pa-2">
                          <small class="text-medium-emphasis">
                            {{ $t('admin_produits_image_order') }}: {{ image.ordre }}
                          </small>
                        </VCardText>
                      </VCard>
                    </VCol>
                  </VRow>
                  <div v-else class="text-center py-8">
                    <VIcon size="64" color="grey-lighten-1" class="mb-4">mdi-image-multiple</VIcon>
                    <p class="text-medium-emphasis mb-4">{{ $t('admin_produits_no_images') }}</p>
                    <VBtn
                      :to="`/admin/produits/${produit.id}/edit`"
                      color="primary"
                      prepend-icon="mdi-plus"
                    >
                      {{ $t('admin_produits_upload_image') }}
                    </VBtn>
                  </div>
                </VCardText>
              </VCard>
            </VTabsWindowItem>
          </VTabsWindow>
        </VCol>
      </VRow>

    <!-- Error State -->
    <VRow v-else-if="!isLoading">
      <VCol cols="12">
        <VAlert type="error">
          {{ $t('admin_common_error_loading') }}
        </VAlert>
      </VCol>
    </VRow>

    <!-- Delete Dialog -->
    <VDialog v-model="showDeleteDialog" max-width="500px">
      <VCard>
        <VCardTitle>
          {{ $t('admin_produits_delete_title') }}
        </VCardTitle>
        
        <VCardText>
          {{ $t('admin_produits_delete_confirm', { name: produit?.titre }) }}
        </VCardText>
        
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="showDeleteDialog = false"
          >
            {{ $t('admin_common_cancel') }}
          </VBtn>
          <VBtn
            color="error"
            :loading="isDeleting"
            @click="handleDelete"
          >
            {{ $t('admin_common_delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useProduitsStore } from '@/stores/admin/produits'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const produitsStore = useProduitsStore()

const isLoading = ref(true)
const isDeleting = ref(false)
const showDeleteDialog = ref(false)
const activeTab = ref('details')

const produit = computed(() => produitsStore.currentProduit)

const formatPrice = (price: number | string) => {
  return new Intl.NumberFormat('fr-MA', {
    style: 'currency',
    currency: 'MAD'
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
    await produitsStore.deleteProduit(route.params.id as string)
    await router.push('/admin/produits')
  } catch (error) {
    console.error('Error deleting produit:', error)
  } finally {
    isDeleting.value = false
    showDeleteDialog.value = false
  }
}

onMounted(async () => {
  try {
    await produitsStore.fetchProduit(route.params.id as string)
  } catch (error) {
    console.error('Error loading produit:', error)
  } finally {
    isLoading.value = false
  }
})
</script>
