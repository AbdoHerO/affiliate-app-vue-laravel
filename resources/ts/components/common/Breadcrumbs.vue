<template>
  <div class="d-flex flex-column gap-2">
    <VBreadcrumbs
      v-if="items && items.length > 0"
      :items="breadcrumbItems"
      divider="/"
    >
      <template #item="{ item }">
        <VBreadcrumbsItem
          v-if="item.href && !item.disabled"
          :href="item.href"
          :title="item.title"
          @click.prevent="$router.push(item.href)"
        />
        <VBreadcrumbsItem
          v-else
          :title="item.title"
          :disabled="item.disabled"
        />
      </template>
    </VBreadcrumbs>
    
    <h1 v-if="title" class="text-h4 font-weight-bold">
      {{ title }}
    </h1>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface BreadcrumbItem {
  title: string
  to?: string
  active?: boolean
}

interface Props {
  items?: BreadcrumbItem[]
  title?: string
}

const props = defineProps<Props>()

const breadcrumbItems = computed(() => {
  return props.items?.map(item => ({
    title: item.title,
    href: item.to,
    disabled: item.active
  })) || []
})
</script>
