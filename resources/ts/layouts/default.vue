<script lang="ts" setup>
import { useConfigStore } from '@core/stores/config'
import { AppContentLayoutNav } from '@layouts/enums'
import { switchToVerticalNavOnLtOverlayNavBreakpoint } from '@layouts/utils'
import GlobalConfirmProvider from '@/components/common/GlobalConfirmProvider.vue'
import GlobalSnackbar from '@/components/common/GlobalSnackbar.vue'


const DefaultLayoutWithHorizontalNav = defineAsyncComponent(() => import('./components/DefaultLayoutWithHorizontalNav.vue'))
const DefaultLayoutWithVerticalNav = defineAsyncComponent(() => import('./components/DefaultLayoutWithVerticalNav.vue'))

const configStore = useConfigStore()

// ℹ️ This will switch to vertical nav when define breakpoint is reached when in horizontal nav layout
// Remove below composable usage if you are not using horizontal nav layout in your app
switchToVerticalNavOnLtOverlayNavBreakpoint()

const { layoutAttrs, injectSkinClasses } = useSkins()

injectSkinClasses()

// SECTION: Loading Indicator
const isFallbackStateActive = ref(false)
const refLoadingIndicator = ref<any>(null)

// watching if the fallback state is active and the refLoadingIndicator component is available
watch([isFallbackStateActive, refLoadingIndicator], () => {
  if (isFallbackStateActive.value && refLoadingIndicator.value)
    refLoadingIndicator.value.fallbackHandle()

  if (!isFallbackStateActive.value && refLoadingIndicator.value)
    refLoadingIndicator.value.resolveHandle()
}, { immediate: true })

// Suspense event handlers
const onSuspensePending = () => {
  console.log('🔄 [Layout] Suspense pending - component loading')
  isFallbackStateActive.value = true
}

const onSuspenseError = (error: Error) => {
  console.error('🚫 [Layout] Suspense error:', error)
  isFallbackStateActive.value = false
  // Don't throw the error, let the router handle it
}

// Transition event handlers to prevent vnode errors
const onBeforeLeave = (el: Element) => {
  console.log('🔄 [Layout] Component leaving:', el)
  // Force cleanup of any pending operations
  isFallbackStateActive.value = false
}

const onAfterLeave = (el: Element) => {
  console.log('✅ [Layout] Component left:', el)
  // Ensure clean state after component unmount
}
// !SECTION
</script>

<template>
  <Component
    v-bind="layoutAttrs"
    :is="configStore.appContentLayoutNav === AppContentLayoutNav.Vertical ? DefaultLayoutWithVerticalNav : DefaultLayoutWithHorizontalNav"
  >
    <AppLoadingIndicator ref="refLoadingIndicator" />

  <!-- Global confirm dialog provider (renders once for entire app) -->
  <GlobalConfirmProvider />

  <!-- Global snackbar for notifications -->
  <GlobalSnackbar />

    <RouterView v-slot="{ Component, route }">
      <Transition
        name="page-transition"
        mode="out-in"
        @before-leave="onBeforeLeave"
        @after-leave="onAfterLeave"
      >
        <Suspense
          v-if="Component"
          :timeout="0"
          @fallback="isFallbackStateActive = true"
          @resolve="isFallbackStateActive = false"
          @pending="onSuspensePending"
          @error="onSuspenseError"
        >
          <template #default>
            <Component
              :is="Component"
              :key="route?.fullPath || 'default'"
            />
          </template>
          <template #fallback>
            <div class="d-flex align-center justify-center" style="min-height: 200px;">
              <VProgressCircular indeterminate color="primary" />
            </div>
          </template>
        </Suspense>
      </Transition>
    </RouterView>


  </Component>
</template>

<style lang="scss">
// As we are using `layouts` plugin we need its styles to be imported
@use "@layouts/styles/default-layout";

// Page transition styles
.page-transition-enter-active,
.page-transition-leave-active {
  transition: opacity 0.2s ease;
}

.page-transition-enter-from,
.page-transition-leave-to {
  opacity: 0;
}
</style>
