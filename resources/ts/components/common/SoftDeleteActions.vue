<template>
  <div class="soft-delete-actions">
    <!-- Active item actions -->
    <template v-if="!isSoftDeleted(item)">
      <VBtn
        v-if="showEdit"
        icon
        size="small"
        color="primary"
        variant="text"
        @click="$emit('edit', item)"
      >
        <VIcon icon="tabler-edit" />
        <VTooltip activator="parent" location="top">
          {{ $t('action_edit') }}
        </VTooltip>
      </VBtn>
      
      <VBtn
        v-if="showView"
        icon
        size="small"
        color="info"
        variant="text"
        @click="$emit('view', item)"
      >
        <VIcon icon="tabler-eye" />
        <VTooltip activator="parent" location="top">
          {{ $t('action_view') }}
        </VTooltip>
      </VBtn>
      
      <VBtn
        icon
        size="small"
        color="error"
        variant="text"
        :loading="isLoading"
        @click="handleSoftDelete"
      >
        <VIcon icon="tabler-trash" />
        <VTooltip activator="parent" location="top">
          {{ $t('action_delete') }}
        </VTooltip>
      </VBtn>
    </template>

    <!-- Soft deleted item actions -->
    <template v-else>
      <VBtn
        v-if="showView"
        icon
        size="small"
        color="info"
        variant="text"
        @click="$emit('view', item)"
      >
        <VIcon icon="tabler-eye" />
        <VTooltip activator="parent" location="top">
          {{ $t('action_view') }}
        </VTooltip>
      </VBtn>
      
      <VBtn
        icon
        size="small"
        color="success"
        variant="text"
        :loading="isLoading"
        @click="handleRestore"
      >
        <VIcon icon="tabler-restore" />
        <VTooltip activator="parent" location="top">
          {{ $t('action_restore') }}
        </VTooltip>
      </VBtn>
      
      <VBtn
        v-if="showPermanentDelete"
        icon
        size="small"
        color="error"
        variant="text"
        :loading="isLoading"
        @click="handlePermanentDelete"
      >
        <VIcon icon="tabler-trash-x" />
        <VTooltip activator="parent" location="top">
          {{ $t('action_permanent_delete') }}
        </VTooltip>
      </VBtn>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import { useSoftDelete } from '@/composables/useSoftDelete'

interface Props {
  item: any
  entityName: string
  apiEndpoint: string
  itemNameField?: string
  showEdit?: boolean
  showView?: boolean
  showPermanentDelete?: boolean
}

interface Emits {
  (e: 'edit', item: any): void
  (e: 'view', item: any): void
  (e: 'deleted'): void
  (e: 'restored'): void
  (e: 'permanentlyDeleted'): void
}

const props = withDefaults(defineProps<Props>(), {
  itemNameField: 'name',
  showEdit: true,
  showView: true,
  showPermanentDelete: true
})

const emit = defineEmits<Emits>()

const { t } = useI18n()
const { confirmDelete, confirmRestore, confirmPermanentDelete } = useQuickConfirm()

const {
  isLoading,
  softDelete,
  restore,
  forceDelete,
  isSoftDeleted
} = useSoftDelete({
  entityName: props.entityName,
  apiEndpoint: props.apiEndpoint,
  onSuccess: () => {
    // Emit appropriate event based on action
  }
})

// Get item display name
const itemName = computed(() => {
  return props.item[props.itemNameField] || props.item.nom || props.item.titre || props.item.nom_complet || 'Item'
})

// Handle soft delete
const handleSoftDelete = async () => {
  const confirmed = await confirmDelete(props.entityName, itemName.value)
  if (!confirmed) return

  const success = await softDelete(props.item.id, itemName.value)
  if (success) {
    emit('deleted')
  }
}

// Handle restore
const handleRestore = async () => {
  const confirmed = await confirmRestore(props.entityName, itemName.value)
  if (!confirmed) return

  const success = await restore(props.item.id, itemName.value)
  if (success) {
    emit('restored')
  }
}

// Handle permanent delete
const handlePermanentDelete = async () => {
  const confirmed = await confirmPermanentDelete(props.entityName, itemName.value)
  if (!confirmed) return

  const success = await forceDelete(props.item.id, itemName.value)
  if (success) {
    emit('permanentlyDeleted')
  }
}
</script>

<style scoped>
.soft-delete-actions {
  display: flex;
  gap: 4px;
  align-items: center;
}
</style>
