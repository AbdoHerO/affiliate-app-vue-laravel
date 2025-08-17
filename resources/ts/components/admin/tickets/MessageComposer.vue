<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import TiptapEditor from '@/@core/components/TiptapEditor.vue'

interface Props {
  loading?: boolean
  disabled?: boolean
}

interface Emits {
  (e: 'submit', data: {
    type: 'public' | 'internal'
    body: string
    attachments: File[]
  }): void
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  disabled: false,
})

const emit = defineEmits<Emits>()

const { t } = useI18n()

// State
const messageType = ref<'public' | 'internal'>('public')
const messageBody = ref('')
const attachments = ref<File[]>([])

// Computed
const canSubmit = computed(() => {
  return messageBody.value.trim().length > 0 && !props.loading && !props.disabled
})

const attachmentSizeLimit = 5 * 1024 * 1024 // 5MB
const maxAttachments = 5

// Methods
const handleFileSelect = (files: File[]) => {
  const validFiles = files.filter(file => {
    // Check file size
    if (file.size > attachmentSizeLimit) {
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

  // Limit total attachments
  const totalFiles = attachments.value.length + validFiles.length
  if (totalFiles > maxAttachments) {
    const allowedCount = maxAttachments - attachments.value.length
    attachments.value.push(...validFiles.slice(0, allowedCount))
  } else {
    attachments.value.push(...validFiles)
  }
}

const removeAttachment = (index: number) => {
  attachments.value.splice(index, 1)
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

const handleSubmit = () => {
  if (!canSubmit.value) return

  emit('submit', {
    type: messageType.value,
    body: messageBody.value,
    attachments: attachments.value,
  })

  // Reset form
  messageBody.value = ''
  attachments.value = []
}

const handleKeydown = (event: KeyboardEvent) => {
  // Submit on Ctrl+Enter
  if (event.ctrlKey && event.key === 'Enter') {
    event.preventDefault()
    handleSubmit()
  }
}
</script>

<template>
  <VCard>
    <VCardText>
      <!-- Message Type Tabs -->
      <VTabs
        v-model="messageType"
        class="mb-4"
      >
        <VTab value="public">
          <VIcon icon="tabler-eye" class="me-2" />
          {{ t('message_type_public') }}
        </VTab>
        <VTab value="internal">
          <VIcon icon="tabler-lock" class="me-2" />
          {{ t('message_type_internal') }}
        </VTab>
      </VTabs>

      <!-- Message Body Editor -->
      <div class="mb-4">
        <TiptapEditor
          v-model="messageBody"
          :placeholder="t('message_body_placeholder')"
          :disabled="disabled"
          @keydown="handleKeydown"
        />
      </div>

      <!-- File Attachments -->
      <div class="mb-4">
        <VFileInput
          :label="t('attachments')"
          multiple
          accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.txt"
          variant="outlined"
          density="compact"
          :disabled="disabled || attachments.length >= maxAttachments"
          @update:model-value="handleFileSelect"
        >
          <template #prepend-inner>
            <VIcon icon="tabler-paperclip" />
          </template>
        </VFileInput>

        <!-- Attachment List -->
        <div v-if="attachments.length > 0" class="mt-2">
          <VChip
            v-for="(file, index) in attachments"
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
          {{ t('attachment_limits', { max: maxAttachments, size: '5MB' }) }}
        </div>
      </div>

      <!-- Actions -->
      <div class="d-flex justify-end">
        <VBtn
          :loading="loading"
          :disabled="!canSubmit"
          color="primary"
          @click="handleSubmit"
        >
          <VIcon icon="tabler-send" class="me-2" />
          {{ t('send_message') }}
        </VBtn>
      </div>

      <!-- Keyboard Shortcut Hint -->
      <div class="text-caption text-medium-emphasis mt-2 text-end">
        {{ t('keyboard_shortcut_hint') }}
      </div>
    </VCardText>
  </VCard>
</template>
