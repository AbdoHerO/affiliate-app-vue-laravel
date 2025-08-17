<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useTicketsStore } from '@/stores/admin/tickets'
import { useFormErrors } from '@/composables/useFormErrors'
import { useApi } from '@/composables/useApi'
import { useAuthStore } from '@/stores/auth'
import TicketAssigneeSelect from '@/components/admin/tickets/TicketAssigneeSelect.vue'
import TiptapEditor from '@/@core/components/TiptapEditor.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const router = useRouter()
const { t } = useI18n()
const ticketsStore = useTicketsStore()
const authStore = useAuthStore()
const { api } = useApi()
const { errors, setErrors, clearErrors, hasError } = useFormErrors()

// Form data
const form = reactive({
  subject: '',
  category: 'general',
  priority: 'normal',
  requester_id: '',
  ticket_type: 'on_behalf' as 'on_behalf' | 'internal',
  relations: [] as Array<{ related_type: string; related_id: string }>,
  first_message: {
    body: '',
    type: 'public' as 'public' | 'internal',
    attachments: [] as File[],
  },
})

// State
const loading = ref(false)
const users = ref([])
const loadingUsers = ref(false)
const showFirstMessage = ref(false)

// Options
const categoryOptions = [
  { title: t('ticket_category_general'), value: 'general' },
  { title: t('ticket_category_orders'), value: 'orders' },
  { title: t('ticket_category_payments'), value: 'payments' },
  { title: t('ticket_category_commissions'), value: 'commissions' },
  { title: t('ticket_category_kyc'), value: 'kyc' },
  { title: t('ticket_category_technical'), value: 'technical' },
  { title: t('ticket_category_other'), value: 'other' },
]

const priorityOptions = [
  { title: t('ticket_priority_low'), value: 'low' },
  { title: t('ticket_priority_normal'), value: 'normal' },
  { title: t('ticket_priority_high'), value: 'high' },
  { title: t('ticket_priority_urgent'), value: 'urgent' },
]

const messageTypeOptions = [
  { title: t('message_type_public'), value: 'public' },
  { title: t('message_type_internal'), value: 'internal' },
]

const ticketTypeOptions = [
  { title: t('ticket_type_on_behalf'), value: 'on_behalf' },
  { title: t('ticket_type_internal'), value: 'internal' },
]

// Methods
const loadUsers = async () => {
  loadingUsers.value = true
  try {
    const response = await api.get('/admin/users', {
      params: { per_page: 100 }
    })
    if (response.data.success) {
      users.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to load users:', error)
  } finally {
    loadingUsers.value = false
  }
}

const handleFileSelect = (files: File[]) => {
  const validFiles = files.filter(file => {
    // Check file size (5MB limit)
    if (file.size > 5 * 1024 * 1024) {
      console.warn(`File ${file.name} is too large (max 5MB)`)
      return false
    }
    
    // Check file type
    const allowedTypes = [
      'image/jpeg', 'image/png', 'image/gif', 'image/webp',
      'application/pdf',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'text/plain'
    ]
    
    if (!allowedTypes.includes(file.type)) {
      console.warn(`File ${file.name} has unsupported type`)
      return false
    }
    
    return true
  })

  // Limit total attachments to 5
  const totalFiles = form.first_message.attachments.length + validFiles.length
  if (totalFiles > 5) {
    const allowedCount = 5 - form.first_message.attachments.length
    form.first_message.attachments.push(...validFiles.slice(0, allowedCount))
  } else {
    form.first_message.attachments.push(...validFiles)
  }
}

const removeAttachment = (index: number) => {
  form.first_message.attachments.splice(index, 1)
}

const getFileIcon = (file: File) => {
  if (file.type.startsWith('image/')) return 'tabler-photo'
  if (file.type === 'application/pdf') return 'tabler-file-type-pdf'
  if (file.type.includes('word')) return 'tabler-file-type-doc'
  if (file.type === 'text/plain') return 'tabler-file-type-txt'
  return 'tabler-file'
}

const formatFileSize = (bytes: number) => {
  const units = ['B', 'KB', 'MB', 'GB']
  let size = bytes
  let unitIndex = 0
  
  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024
    unitIndex++
  }
  
  return `${Math.round(size * 100) / 100} ${units[unitIndex]}`
}

const handleSubmit = async () => {
  loading.value = true
  clearErrors()

  try {
    const ticketData = {
      subject: form.subject,
      category: form.category,
      priority: form.priority,
      requester_id: form.ticket_type === 'internal' ? authStore.user?.id : form.requester_id,
      ticket_type: form.ticket_type,
      relations: form.relations.length > 0 ? form.relations : undefined,
      first_message: showFirstMessage.value ? {
        body: form.first_message.body,
        type: form.first_message.type,
        attachments: form.first_message.attachments.length > 0 ? form.first_message.attachments : undefined,
      } : undefined,
    }

    const ticket = await ticketsStore.createTicket(ticketData)
    
    if (ticket) {
      router.push(`/admin/support/tickets/${ticket.id}`)
    }
  } catch (error: any) {
    if (error.response?.status === 422) {
      setErrors(error.response.data.errors)
    }
  } finally {
    loading.value = false
  }
}

const goBack = () => {
  router.push('/admin/support/tickets')
}

// Load initial data
loadUsers()
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex align-center mb-6">
      <VBtn
        icon="tabler-arrow-left"
        variant="text"
        @click="goBack"
      />
      <div class="ms-4">
        <h1 class="text-h4 font-weight-bold mb-1">
          {{ t('create_ticket_admin') }}
        </h1>
        <p class="text-body-1 text-medium-emphasis mb-0">
          {{ t('create_ticket_admin_description') }}
        </p>
      </div>
    </div>

    <VForm @submit.prevent="handleSubmit">
      <VRow>
        <!-- Main Form -->
        <VCol cols="12" md="8">
          <VCard>
            <VCardTitle>{{ t('ticket_details') }}</VCardTitle>
            <VCardText>
              <VRow>
                <!-- Ticket Type -->
                <VCol cols="12">
                  <VSelect
                    v-model="form.ticket_type"
                    :items="ticketTypeOptions"
                    :label="t('ticket_type')"
                    :error-messages="errors.ticket_type"
                    variant="outlined"
                    required
                  >
                    <template #item="{ props: itemProps, item }">
                      <VListItem v-bind="itemProps">
                        <VListItemTitle>{{ item.title }}</VListItemTitle>
                        <VListItemSubtitle>
                          {{ item.value === 'on_behalf' ? t('ticket_type_on_behalf_desc') : t('ticket_type_internal_desc') }}
                        </VListItemSubtitle>
                      </VListItem>
                    </template>
                  </VSelect>
                </VCol>

                <!-- Subject -->
                <VCol cols="12">
                  <VTextField
                    v-model="form.subject"
                    :label="t('subject')"
                    :placeholder="t('subject_placeholder')"
                    :error-messages="errors.subject"
                    variant="outlined"
                    required
                  />
                </VCol>

                <!-- Category -->
                <VCol cols="12" md="6">
                  <VSelect
                    v-model="form.category"
                    :items="categoryOptions"
                    :label="t('category')"
                    :error-messages="errors.category"
                    variant="outlined"
                    required
                  />
                </VCol>

                <!-- Priority -->
                <VCol cols="12" md="6">
                  <VSelect
                    v-model="form.priority"
                    :items="priorityOptions"
                    :label="t('priority')"
                    :error-messages="errors.priority"
                    variant="outlined"
                    required
                  />
                </VCol>

                <!-- Requester (only for on_behalf tickets) -->
                <VCol v-if="form.ticket_type === 'on_behalf'" cols="12">
                  <VAutocomplete
                    v-model="form.requester_id"
                    :items="users"
                    :loading="loadingUsers"
                    :label="t('requester')"
                    :placeholder="t('select_requester')"
                    :error-messages="errors.requester_id"
                    item-title="nom_complet"
                    item-value="id"
                    variant="outlined"
                    required
                  >
                    <template #item="{ props: itemProps, item }">
                      <VListItem v-bind="itemProps">
                        <template #prepend>
                          <VAvatar size="32">
                            <VImg
                              v-if="item.raw.photo_profil"
                              :src="item.raw.photo_profil"
                              :alt="item.raw.nom_complet"
                            />
                            <VIcon v-else icon="tabler-user" size="18" />
                          </VAvatar>
                        </template>
                        
                        <VListItemTitle>{{ item.raw.nom_complet }}</VListItemTitle>
                        <VListItemSubtitle>{{ item.raw.email }}</VListItemSubtitle>
                      </VListItem>
                    </template>
                  </VAutocomplete>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <!-- First Message (Optional) -->
          <VCard class="mt-6">
            <VCardTitle>
              <div class="d-flex align-center justify-space-between">
                <span>{{ t('first_message') }}</span>
                <VSwitch
                  v-model="showFirstMessage"
                  :label="t('add_first_message')"
                  color="primary"
                  hide-details
                />
              </div>
            </VCardTitle>

            <VExpandTransition>
              <VCardText v-if="showFirstMessage">
                <VRow>
                  <!-- Message Type -->
                  <VCol cols="12">
                    <VSelect
                      v-model="form.first_message.type"
                      :items="messageTypeOptions"
                      :label="t('message_type')"
                      variant="outlined"
                      density="compact"
                    />
                  </VCol>

                  <!-- Message Body -->
                  <VCol cols="12">
                    <TiptapEditor
                      v-model="form.first_message.body"
                      :placeholder="t('message_body_placeholder')"
                    />
                  </VCol>

                  <!-- Attachments -->
                  <VCol cols="12">
                    <VFileInput
                      :label="t('attachments')"
                      multiple
                      accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.txt"
                      variant="outlined"
                      density="compact"
                      :disabled="form.first_message.attachments.length >= 5"
                      @update:model-value="handleFileSelect"
                    >
                      <template #prepend-inner>
                        <VIcon icon="tabler-paperclip" />
                      </template>
                    </VFileInput>

                    <!-- Attachment List -->
                    <div v-if="form.first_message.attachments.length > 0" class="mt-2">
                      <VChip
                        v-for="(file, index) in form.first_message.attachments"
                        :key="index"
                        closable
                        size="small"
                        class="me-2 mb-2"
                        @click:close="removeAttachment(index)"
                      >
                        <VIcon
                          :icon="getFileIcon(file)"
                          size="14"
                          class="me-1"
                        />
                        {{ file.name }} ({{ formatFileSize(file.size) }})
                      </VChip>
                    </div>

                    <!-- Attachment Limits Info -->
                    <div class="text-caption text-medium-emphasis mt-1">
                      {{ t('attachment_limits', { max: 5, size: '5MB' }) }}
                    </div>
                  </VCol>
                </VRow>
              </VCardText>
            </VExpandTransition>
          </VCard>
        </VCol>

        <!-- Sidebar -->
        <VCol cols="12" md="4">
          <!-- Actions -->
          <VCard>
            <VCardTitle>{{ t('actions') }}</VCardTitle>
            <VCardText>
              <div class="d-flex flex-column gap-3">
                <VBtn
                  type="submit"
                  color="primary"
                  block
                  :loading="loading"
                >
                  <VIcon icon="tabler-plus" class="me-2" />
                  {{ t('create_ticket_admin') }}
                </VBtn>

                <VBtn
                  variant="outlined"
                  block
                  @click="goBack"
                >
                  <VIcon icon="tabler-x" class="me-2" />
                  {{ t('cancel') }}
                </VBtn>
              </div>
            </VCardText>
          </VCard>

          <!-- Help -->
          <VCard class="mt-6">
            <VCardTitle>{{ t('help') }}</VCardTitle>
            <VCardText>
              <div class="text-body-2">
                <p class="mb-2">{{ t('create_ticket_admin_help_1') }}</p>
                <p class="mb-2">{{ t('create_ticket_admin_help_2') }}</p>
                <p class="mb-0">{{ t('create_ticket_admin_help_3') }}</p>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VForm>
  </div>
</template>
