<script setup lang="ts">
interface Proposition {
  id: string
  titre?: string
  description: string
  type: string
  image_url?: string | null
  created_at: string
}

interface Props {
  propositions: Proposition[]
}

defineProps<Props>()

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('fr-FR', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const getTypeColor = (type: string) => {
  switch (type) {
    case 'nouveau': return 'success'
    case 'amelioration': return 'info'
    case 'probleme': return 'warning'
    default: return 'primary'
  }
}

const getTypeLabel = (type: string) => {
  switch (type) {
    case 'nouveau': return 'New Feature'
    case 'amelioration': return 'Improvement'
    case 'probleme': return 'Issue Report'
    default: return type
  }
}
</script>

<template>
  <VCard elevation="2">
    <VCardTitle class="d-flex align-center gap-2">
      <VIcon icon="tabler-lightbulb" />
      Propositions ({{ propositions.length }})
    </VCardTitle>
    <VCardText>
      <VRow>
        <VCol
          v-for="proposition in propositions"
          :key="proposition.id"
          cols="12"
          sm="6"
          md="4"
        >
          <VCard
            class="proposition-card h-100"
            elevation="1"
          >
            <div v-if="proposition.image_url" class="proposition-image">
              <VImg
                :src="proposition.image_url"
                :alt="proposition.titre || 'Proposition'"
                height="160"
                cover
              >
                <template #placeholder>
                  <div class="d-flex align-center justify-center fill-height bg-grey-lighten-4">
                    <VIcon icon="tabler-photo" size="32" color="grey" />
                  </div>
                </template>
              </VImg>
            </div>
            
            <VCardText class="pa-4 d-flex flex-column">
              <div class="mb-2">
                <VChip
                  :color="getTypeColor(proposition.type)"
                  size="small"
                  variant="outlined"
                >
                  {{ getTypeLabel(proposition.type) }}
                </VChip>
              </div>
              
              <h3 v-if="proposition.titre" class="text-subtitle-1 font-weight-bold mb-2">
                {{ proposition.titre }}
              </h3>
              
              <div class="text-body-2 text-medium-emphasis mb-3 flex-grow-1">
                {{ proposition.description }}
              </div>
              
              <div class="text-caption text-medium-emphasis">
                {{ formatDate(proposition.created_at) }}
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>

<style scoped>
.proposition-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  border: 1px solid rgba(0, 0, 0, 0.12);
}

.proposition-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.proposition-image {
  border-bottom: 1px solid rgba(0, 0, 0, 0.12);
}
</style>
