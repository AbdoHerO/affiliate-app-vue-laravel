<template>
  <div class="soft-delete-actions">
    <!-- Active item actions -->
    <template v-if="!isSoftDeleted(item)">
      <ActionIcon
        v-if="showEdit"
        icon="tabler-edit"
        label="actions.edit"
        variant="primary"
        @click="$emit('edit', item)"
      />

      <ActionIcon
        v-if="showView"
        icon="tabler-eye"
        label="actions.view"
        variant="default"
        @click="$emit('view', item)"
      />

      <ActionIcon
        icon="tabler-trash"
        label="actions.delete"
        variant="danger"
        :loading="isLoading"
        confirm
        confirm-title="Confirmer la suppression"
        :confirm-message="`Êtes-vous sûr de vouloir supprimer ${getItemName(item)} ?`"
        @click="handleSoftDelete"
      />
    </template>

    <!-- Soft deleted item actions -->
    <template v-else>
      <ActionIcon
        v-if="showView"
        icon="tabler-eye"
        label="actions.view"
        variant="default"
        @click="$emit('view', item)"
      />

      <ActionIcon
        icon="tabler-restore"
        label="actions.restore"
        variant="success"
        :loading="isLoading"
        @click="handleRestore"
      />

      <ActionIcon
        v-if="showPermanentDelete"
        icon="tabler-trash-x"
        label="actions.force_delete"
        variant="danger"
        :loading="isLoading"
        confirm
        confirm-title="Suppression définitive"
        :confirm-message="`Êtes-vous sûr de vouloir supprimer définitivement ${getItemName(item)} ? Cette action est irréversible.`"
        @click="handlePermanentDelete"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import { useSoftDelete } from '@/composables/useSoftDelete'
import ActionIcon from '@/components/common/ActionIcon.vue'

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

const getItemName = (item: any) => {
  return item[props.itemNameField] || item.nom || item.titre || item.nom_complet || 'cet élément'
}

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
