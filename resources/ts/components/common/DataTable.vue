<template>
  <VDataTable
    :items="items"
    :headers="headers"
    :loading="loading"
    :items-per-page="perPage"
    :page="page"
    :server-items-length="total"
    :sort-by="sortBy"
    class="elevation-0"
    @update:options="handleOptionsUpdate"
  >
    <!-- Pass through all slots -->
    <template v-for="(_, slot) in $slots" #[slot]="scope">
      <slot :name="slot" v-bind="scope" />
    </template>
  </VDataTable>
</template>

<script setup lang="ts">
interface Props {
  items: any[]
  headers: any[]
  loading?: boolean
  total?: number
  page?: number
  perPage?: number
  sortBy?: string
  sortDesc?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  total: 0,
  page: 1,
  perPage: 10,
  sortBy: '',
  sortDesc: false
})

const emit = defineEmits<{
  'update:page': [page: number]
  'update:per-page': [perPage: number]
  'update:sort': [sort: { sortBy: string; sortDesc: boolean }]
}>()

const handleOptionsUpdate = (options: any) => {
  if (options.page !== props.page) {
    emit('update:page', options.page)
  }
  
  if (options.itemsPerPage !== props.perPage) {
    emit('update:per-page', options.itemsPerPage)
  }
  
  if (options.sortBy?.length > 0) {
    const sort = options.sortBy[0]
    emit('update:sort', {
      sortBy: sort.key,
      sortDesc: sort.order === 'desc'
    })
  }
}
</script>
