<template>
  <VSelect
    v-model="selectedFilter"
    :items="filterOptions"
    :label="$t('common.filter_by_status')"
    item-title="title"
    item-value="value"
    variant="outlined"
    density="compact"
    class="soft-delete-filter"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <template #prepend-inner>
      <VIcon 
        :icon="getFilterIcon(selectedFilter)" 
        :color="getFilterColor(selectedFilter)"
        size="20"
      />
    </template>
  </VSelect>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { SoftDeleteFilter } from '@/composables/useSoftDelete'

interface Props {
  modelValue: SoftDeleteFilter
}

interface Emits {
  (e: 'update:modelValue', value: SoftDeleteFilter): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { t } = useI18n()

const selectedFilter = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const filterOptions = computed(() => [
  { 
    title: t('common.active'), 
    value: 'active' as SoftDeleteFilter,
    icon: 'tabler-check',
    color: 'success'
  },
  { 
    title: t('common.trashed'), 
    value: 'trashed' as SoftDeleteFilter,
    icon: 'tabler-trash',
    color: 'error'
  },
  { 
    title: t('common.all'), 
    value: 'all' as SoftDeleteFilter,
    icon: 'tabler-list',
    color: 'primary'
  }
])

const getFilterIcon = (filter: SoftDeleteFilter): string => {
  const option = filterOptions.value.find(opt => opt.value === filter)
  return option?.icon || 'tabler-list'
}

const getFilterColor = (filter: SoftDeleteFilter): string => {
  const option = filterOptions.value.find(opt => opt.value === filter)
  return option?.color || 'primary'
}
</script>

<style scoped>
.soft-delete-filter {
  min-width: 150px;
  max-width: 200px;
}
</style>
