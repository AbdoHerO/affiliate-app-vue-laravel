<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useBoutiquesStore, type BoutiqueFormData } from '@/stores/admin/boutiques'
import { useNotifications } from '@/composables/useNotifications'
import { useApi } from '@/composables/useApi'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { t } = useI18n()
const router = useRouter()
const boutiquesStore = useBoutiquesStore()
const { showError } = useNotifications()

// Form state
const form = ref<BoutiqueFormData>({
  nom: '',
  slug: '',
  proprietaire_id: '',
  email_pro: '',
  adresse: '',
  statut: 'actif',
  commission_par_defaut: 0,
})

const isLoading = ref(false)
const errors = ref<Record<string, string[]>>({})
const users = ref<Array<{ id: string; nom_complet: string; email: string }>>([])

// Form validation
const validateForm = () => {
  errors.value = {}
  
  if (!form.value.nom.trim()) {
    errors.value.nom = ['Le nom est requis']
  }
  
  if (!form.value.proprietaire_id) {
    errors.value.proprietaire_id = ['Le propriétaire est requis']
  }
  
  if (form.value.email_pro && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email_pro)) {
    errors.value.email_pro = ['Format email invalide']
  }
  
  return Object.keys(errors.value).length === 0
}

// Load users for proprietaire selection
const loadUsers = async () => {
  try {
    const { data, error } = await useApi<any>('/admin/users?per_page=100')
    if (error.value) {
      showError('Erreur lors du chargement des utilisateurs')
      return
    }
    users.value = data.value?.users || []
  } catch (err) {
    showError('Erreur lors du chargement des utilisateurs')
  }
}

// Handle form submission
const handleSubmit = async () => {
  if (!validateForm()) return

  isLoading.value = true
  
  try {
    await boutiquesStore.create(form.value)
    router.push({ name: 'admin-boutiques' })
  } catch (error: any) {
    if (error.status === 422 && error.data?.errors) {
      errors.value = error.data.errors
    }
  } finally {
    isLoading.value = false
  }
}

const goBack = () => {
  router.push({ name: 'admin-boutiques' })
}

// Auto-generate slug from nom
const generateSlug = () => {
  if (form.value.nom && !form.value.slug) {
    form.value.slug = form.value.nom
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)/g, '')
  }
}

onMounted(() => {
  loadUsers()
})
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs
      :items="[
        { title: t('breadcrumb_home'), to: '/' },
        { title: t('admin_boutiques_title'), to: '/admin/boutiques' },
        { title: t('breadcrumb_create') },
      ]"
      class="pa-0 mb-4"
    />

    <VRow>
      <VCol cols="12">
        <VCard>
          <VCardTitle class="d-flex align-center justify-space-between">
            <span>{{ t('admin_boutiques_create_title') || 'Créer une boutique' }}</span>
            <VBtn
              variant="outlined"
              size="small"
              @click="goBack"
            >
              {{ t('action_cancel') || 'Retour' }}
            </VBtn>
          </VCardTitle>
          
          <VCardText>
            <VForm @submit.prevent="handleSubmit">
              <VRow>
                <!-- Nom -->
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="form.nom"
                    :label="t('admin_boutiques_name') || 'Nom'"
                    :error-messages="errors.nom"
                    variant="outlined"
                    required
                    @blur="generateSlug"
                  />
                </VCol>

                <!-- Slug -->
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="form.slug"
                    :label="t('admin_boutiques_slug') || 'Slug'"
                    :error-messages="errors.slug"
                    variant="outlined"
                    hint="URL-friendly version du nom (généré automatiquement)"
                  />
                </VCol>

                <!-- Propriétaire -->
                <VCol cols="12" md="6">
                  <VSelect
                    v-model="form.proprietaire_id"
                    :items="users"
                    item-value="id"
                    item-title="nom_complet"
                    :label="t('admin_boutiques_owner') || 'Propriétaire'"
                    :error-messages="errors.proprietaire_id"
                    variant="outlined"
                    required
                  >
                    <template #item="{ props, item }">
                      <VListItem v-bind="props">
                        <VListItemTitle>{{ item.raw.nom_complet }}</VListItemTitle>
                        <VListItemSubtitle>{{ item.raw.email }}</VListItemSubtitle>
                      </VListItem>
                    </template>
                  </VSelect>
                </VCol>

                <!-- Statut -->
                <VCol cols="12" md="6">
                  <VSelect
                    v-model="form.statut"
                    :items="[
                      { value: 'actif', title: t('admin_boutiques_filter_status_active') || 'Actif' },
                      { value: 'suspendu', title: t('admin_boutiques_filter_status_inactive') || 'Suspendu' },
                      { value: 'desactive', title: t('admin_boutiques_filter_status_pending') || 'Désactivé' },
                    ]"
                    :label="t('admin_boutiques_status') || 'Statut'"
                    :error-messages="errors.statut"
                    variant="outlined"
                    required
                  />
                </VCol>

                <!-- Email professionnel -->
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="form.email_pro"
                    :label="t('admin_boutiques_contact_email') || 'Email professionnel'"
                    :error-messages="errors.email_pro"
                    variant="outlined"
                    type="email"
                  />
                </VCol>

                <!-- Commission par défaut -->
                <VCol cols="12" md="6">
                  <VTextField
                    v-model.number="form.commission_par_defaut"
                    :label="t('admin_boutiques_commission_rate') || 'Commission par défaut (%)'"
                    :error-messages="errors.commission_par_defaut"
                    variant="outlined"
                    type="number"
                    min="0"
                    max="100"
                    suffix="%"
                  />
                </VCol>

                <!-- Adresse -->
                <VCol cols="12">
                  <VTextarea
                    v-model="form.adresse"
                    :label="t('admin_boutiques_address') || 'Adresse'"
                    :error-messages="errors.adresse"
                    variant="outlined"
                    rows="3"
                  />
                </VCol>
              </VRow>

              <!-- Actions -->
              <VRow class="mt-4">
                <VCol cols="12" class="d-flex gap-4 justify-end">
                  <VBtn
                    variant="outlined"
                    @click="goBack"
                  >
                    {{ t('common_cancel') || 'Annuler' }}
                  </VBtn>
                  <VBtn
                    type="submit"
                    color="primary"
                    :loading="isLoading"
                  >
                    {{ t('common_create') || 'Créer' }}
                  </VBtn>
                </VCol>
              </VRow>
            </VForm>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
