<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useProduitsStore, type ProduitFormData } from '@/stores/admin/produits'
import { useBoutiquesStore } from '@/stores/admin/boutiques'
import { useCategoriesStore } from '@/stores/admin/categories'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
    layout: 'default',
  },
})

// Composables
const router = useRouter()
const { t } = useI18n()
const produitsStore = useProduitsStore()
const boutiquesStore = useBoutiquesStore()
const categoriesStore = useCategoriesStore()

// Store state
const { items: boutiques } = storeToRefs(boutiquesStore)
const { categories } = storeToRefs(categoriesStore)

// Form state
const formRef = ref()
const isSubmitting = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref<ProduitFormData>({
  boutique_id: '',
  categorie_id: '',
  titre: '',
  description: '',
  prix_achat: null,
  prix_vente: null,
  prix_affilie: null,
  quantite_min: null,
  notes_admin: '',
  actif: true
})

// Computed
const breadcrumbs = computed(() => [
  { title: t('title_admin_dashboard'), to: '/admin' },
  { title: t('admin_produits_title'), to: '/admin/produits' },
  { title: t('admin_produits_create'), active: true }
])

// Methods
const loadFilterOptions = async () => {
  try {
    await Promise.all([
      boutiquesStore.fetchBoutiques(),
      categoriesStore.fetchCategories()
    ])
  } catch (err) {
    console.error('Error loading filter options:', err)
  }
}

const goBack = () => {
  router.push({ name: 'admin-produits-index' })
}

const submit = async () => {
  if (!formRef.value) return

  const { valid } = await formRef.value.validate()
  if (!valid) return

  isSubmitting.value = true
  errors.value = {}

  try {
    await produitsStore.createProduit(form.value)
    router.push({ name: 'admin-produits-index' })
  } catch (err: any) {
    if (err.errors) {
      errors.value = err.errors
    } else {
      console.error('Error creating product:', err)
    }
  } finally {
    isSubmitting.value = false
  }
}

// Lifecycle
onMounted(async () => {
  await loadFilterOptions()
})
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <Breadcrumbs :items="breadcrumbs" />

    <!-- Page Header -->
    <VRow class="mb-6">
      <VCol cols="12">
        <div class="d-flex align-center justify-space-between">
          <div>
            <h1 class="text-h4 font-weight-bold mb-2">
              {{ $t('admin_produits_create') }}
            </h1>
            <p class="text-body-1 text-medium-emphasis">
              {{ $t('admin_produits_create_subtitle') }}
            </p>
          </div>
          <VBtn
            variant="outlined"
            prepend-icon="tabler-arrow-left"
            @click="goBack"
          >
            {{ $t('common.back') }}
          </VBtn>
        </div>
      </VCol>
    </VRow>

    <!-- Enhanced Form Layout -->
    <VRow>
      <VCol cols="12" lg="8">
        <!-- Basic Information -->
        <VCard class="mb-6">
          <VCardTitle>
            <VIcon icon="tabler-info-circle" class="me-2" />
            {{ $t('admin_produits_basic_info') }}
          </VCardTitle>
          <VDivider />
          <VCardText>
            <VForm ref="formRef" @submit.prevent="submit">
              <VRow>
                <!-- Boutique Selection -->
                <VCol cols="12" md="6">
                  <VSelect
                    v-model="form.boutique_id"
                    :items="boutiques"
                    item-title="nom"
                    item-value="id"
                    :label="$t('admin_produits_boutique')"
                    :error-messages="errors.boutique_id"
                    required
                    variant="outlined"
                    prepend-inner-icon="tabler-building-store"
                  />
                </VCol>

                <!-- Category Selection -->
                <VCol cols="12" md="6">
                  <VSelect
                    v-model="form.categorie_id"
                    :items="categories"
                    item-title="nom"
                    item-value="id"
                    :label="$t('admin_produits_categorie')"
                    :error-messages="errors.categorie_id"
                    variant="outlined"
                    prepend-inner-icon="tabler-category"
                  />
                </VCol>

                <!-- Product Title -->
                <VCol cols="12">
                  <VTextField
                    v-model="form.titre"
                    :label="$t('admin_produits_titre')"
                    :placeholder="$t('admin_produits_titre_placeholder')"
                    :error-messages="errors.titre"
                    required
                    variant="outlined"
                    prepend-inner-icon="tabler-tag"
                  />
                </VCol>

                <!-- Product Description -->
                <VCol cols="12">
                  <VTextarea
                    v-model="form.description"
                    :label="$t('admin_produits_description')"
                    :placeholder="$t('admin_produits_description_placeholder')"
                    :error-messages="errors.description"
                    variant="outlined"
                    rows="4"
                    prepend-inner-icon="tabler-file-text"
                  />
                </VCol>
              </VRow>
            </VForm>
          </VCardText>
        </VCard>

        <!-- Pricing Information -->
        <VCard class="mb-6">
          <VCardTitle>
            <VIcon icon="tabler-currency-dollar" class="me-2" />
            {{ $t('admin_produits_pricing') }}
          </VCardTitle>
          <VDivider />
          <VCardText>
            <VRow>
              <!-- Purchase Price -->
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="form.prix_achat"
                  :label="$t('admin_produits_prix_achat')"
                  :placeholder="$t('admin_produits_prix_achat_placeholder')"
                  :error-messages="errors.prix_achat"
                  type="number"
                  step="0.01"
                  min="0"
                  suffix="DH"
                  variant="outlined"
                  prepend-inner-icon="tabler-shopping-cart"
                />
              </VCol>

              <!-- Sale Price -->
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="form.prix_vente"
                  :label="$t('admin_produits_prix_vente')"
                  :placeholder="$t('admin_produits_prix_vente_placeholder')"
                  :error-messages="errors.prix_vente"
                  type="number"
                  step="0.01"
                  min="0"
                  suffix="DH"
                  variant="outlined"
                  required
                  prepend-inner-icon="tabler-tag"
                />
              </VCol>

              <!-- Affiliate Price -->
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="form.prix_affilie"
                  :label="$t('admin_produits_prix_affilie')"
                  :placeholder="$t('admin_produits_prix_affilie_placeholder')"
                  :error-messages="errors.prix_affilie"
                  type="number"
                  step="0.01"
                  min="0"
                  suffix="DH"
                  variant="outlined"
                  prepend-inner-icon="tabler-users"
                />
              </VCol>

              <!-- Minimum Quantity -->
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="form.quantite_min"
                  :label="$t('admin_produits_quantite_min')"
                  :placeholder="$t('admin_produits_quantite_min_placeholder')"
                  :error-messages="errors.quantite_min"
                  type="number"
                  min="1"
                  variant="outlined"
                  prepend-inner-icon="tabler-package"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- Admin Notes -->
        <VCard class="mb-6">
          <VCardTitle>
            <VIcon icon="tabler-notes" class="me-2" />
            {{ $t('admin_produits_notes_admin') }}
          </VCardTitle>
          <VDivider />
          <VCardText>
            <VTextarea
              v-model="form.notes_admin"
              :label="$t('admin_produits_notes_admin')"
              :placeholder="$t('admin_produits_notes_admin_placeholder')"
              :error-messages="errors.notes_admin"
              variant="outlined"
              rows="3"
              prepend-inner-icon="tabler-note"
            />
          </VCardText>
        </VCard>
      </VCol>

      <!-- Sidebar -->
      <VCol cols="12" lg="4">
        <!-- Status & Actions -->
        <VCard class="mb-6">
          <VCardTitle>
            <VIcon icon="tabler-settings" class="me-2" />
            {{ $t('common.status') }}
          </VCardTitle>
          <VDivider />
          <VCardText>
            <VSwitch
              v-model="form.actif"
              :label="$t('admin_produits_actif')"
              color="primary"
              inset
            />
            <VAlert
              v-if="form.actif"
              type="success"
              variant="tonal"
              class="mt-4"
            >
              {{ $t('admin_produits_active_help') }}
            </VAlert>
            <VAlert
              v-else
              type="warning"
              variant="tonal"
              class="mt-4"
            >
              {{ $t('admin_produits_inactive_help') }}
            </VAlert>
          </VCardText>
        </VCard>

        <!-- Product Preview -->
        <VCard class="mb-6">
          <VCardTitle>
            <VIcon icon="tabler-eye" class="me-2" />
            {{ $t('admin_produits_preview') }}
          </VCardTitle>
          <VDivider />
          <VCardText>
            <div v-if="form.titre" class="mb-4">
              <h6 class="text-h6 mb-2">{{ form.titre }}</h6>
              <p v-if="form.description" class="text-body-2 text-medium-emphasis">
                {{ form.description.substring(0, 100) }}{{ form.description.length > 100 ? '...' : '' }}
              </p>
            </div>
            <div v-if="form.prix_vente" class="mb-4">
              <VChip color="primary" variant="tonal">
                {{ form.prix_vente }} DH
              </VChip>
            </div>
            <VAlert
              v-if="!form.titre"
              type="info"
              variant="tonal"
            >
              {{ $t('admin_produits_preview_empty') }}
            </VAlert>
          </VCardText>
        </VCard>

        <!-- Tips & Help -->
        <VCard class="mb-6">
          <VCardTitle>
            <VIcon icon="tabler-bulb" class="me-2" />
            {{ $t('admin_produits_tips') }}
          </VCardTitle>
          <VDivider />
          <VCardText>
            <VList density="compact">
              <VListItem>
                <VListItemTitle class="text-body-2">
                  {{ $t('admin_produits_tip_title') }}
                </VListItemTitle>
              </VListItem>
              <VListItem>
                <VListItemTitle class="text-body-2">
                  {{ $t('admin_produits_tip_description') }}
                </VListItemTitle>
              </VListItem>
              <VListItem>
                <VListItemTitle class="text-body-2">
                  {{ $t('admin_produits_tip_pricing') }}
                </VListItemTitle>
              </VListItem>
              <VListItem>
                <VListItemTitle class="text-body-2">
                  {{ $t('admin_produits_tip_images') }}
                </VListItemTitle>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>

        <!-- Quick Actions -->
        <VCard>
          <VCardTitle>
            <VIcon icon="tabler-bolt" class="me-2" />
            {{ $t('common.actions') }}
          </VCardTitle>
          <VDivider />
          <VCardText>
            <VBtn
              color="primary"
              block
              size="large"
              :loading="isSubmitting"
              @click="submit"
            >
              <VIcon icon="tabler-plus" class="me-2" />
              {{ $t('admin_produits_create') }}
            </VBtn>

            <VBtn
              variant="outlined"
              block
              class="mt-3"
              @click="goBack"
            >
              <VIcon icon="tabler-arrow-left" class="me-2" />
              {{ $t('common.cancel') }}
            </VBtn>

            <VDivider class="my-4" />

            <VAlert
              type="info"
              variant="tonal"
              class="text-caption"
            >
              {{ $t('admin_produits_create_help') }}
            </VAlert>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>