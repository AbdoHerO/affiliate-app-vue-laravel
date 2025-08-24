<script setup lang="ts">
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import misc404 from '@images/pages/404.png'
import miscMaskDark from '@images/pages/misc-mask-dark.png'
import miscMaskLight from '@images/pages/misc-mask-light.png'

const { t } = useI18n()
const authThemeMask = useGenerateImageVariant(miscMaskLight, miscMaskDark)

definePage({
  alias: '/pages/misc/not-found/:error(.*)',
  meta: {
    layout: 'blank',
    public: true,
  },
})
</script>

<template>
  <div class="misc-wrapper">
    <ErrorHeader
      status-code="404"
      :title="t('errors.pageNotFoundTitle')"
      :description="t('errors.pageNotFoundDescription')"
    />

    <VBtn
      to="/"
      class="mb-11"
    >
      {{ t('actions.backToHome') }}
    </VBtn>

    <!-- 👉 Image -->
    <div class="misc-avatar w-100 text-center">
      <VImg
        :src="misc404"
        :alt="t('a11y.error404Image')"
        :max-height="$vuetify.display.smAndDown ? 350 : 500"
        class="mx-auto"
      />
    </div>

    <img
      class="misc-footer-img d-none d-md-block"
      :src="authThemeMask"
      :alt="t('a11y.decorativeImage')"
      height="320"
    >
  </div>
</template>

<style lang="scss">
@use "@core-scss/template/pages/misc.scss";
</style>
