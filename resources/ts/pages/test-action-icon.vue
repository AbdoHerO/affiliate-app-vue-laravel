<script setup lang="ts">
import { ref } from 'vue'
import ActionIcon from '@/components/common/ActionIcon.vue'

definePage({
  meta: {
    layout: 'default',
    requiresAuth: true,
  },
})

const clickCount = ref(0)
const lastAction = ref('')

const handleClick = (action: string) => {
  console.log(`🎯 ${action} clicked!`)
  clickCount.value++
  lastAction.value = action
}
</script>

<template>
  <div class="pa-6">
    <VCard>
      <VCardTitle>ActionIcon Component Test</VCardTitle>
      <VCardText>
        <div class="mb-4">
          <p><strong>Click Count:</strong> {{ clickCount }}</p>
          <p><strong>Last Action:</strong> {{ lastAction }}</p>
        </div>

        <div class="d-flex gap-4 mb-6">
          <ActionIcon
            icon="tabler-eye"
            label="actions.view"
            variant="default"
            @click="handleClick('view')"
          />
          
          <ActionIcon
            icon="tabler-check"
            label="actions.approve"
            variant="success"
            @click="handleClick('approve')"
          />
          
          <ActionIcon
            icon="tabler-x"
            label="actions.reject"
            variant="danger"
            @click="handleClick('reject')"
          />
          
          <ActionIcon
            icon="tabler-edit"
            label="actions.adjust"
            variant="warning"
            @click="handleClick('adjust')"
          />
          
          <ActionIcon
            icon="tabler-trash"
            label="actions.delete"
            variant="danger"
            confirm
            confirm-title="Confirm Delete"
            confirm-message="Are you sure you want to delete this item?"
            @click="handleClick('delete')"
          />
        </div>

        <div class="mb-4">
          <h3>Regular VBtn for comparison:</h3>
          <div class="d-flex gap-4">
            <VBtn
              icon="tabler-eye"
              variant="text"
              size="small"
              @click="handleClick('regular-view')"
            >
              <VTooltip activator="parent" location="top">
                Regular View Button
              </VTooltip>
            </VBtn>
            
            <VBtn
              icon="tabler-check"
              variant="text"
              size="small"
              color="success"
              @click="handleClick('regular-approve')"
            >
              <VTooltip activator="parent" location="top">
                Regular Approve Button
              </VTooltip>
            </VBtn>
          </div>
        </div>

        <div>
          <h3>Test Results:</h3>
          <VAlert
            v-if="clickCount > 0"
            type="success"
            class="mt-2"
          >
            ✅ Buttons are working! Last action: {{ lastAction }}
          </VAlert>
          <VAlert
            v-else
            type="info"
            class="mt-2"
          >
            Click any button above to test functionality
          </VAlert>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
