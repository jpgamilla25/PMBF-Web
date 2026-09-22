<template>
  <AppModal
    :show="state.open"
    :title="state.title"
    size="sm"
    :dismissable="false"
    @close="state.resolve(false)"
  >
    <p class="mb-0">{{ state.message }}</p>

    <template #footer>
      <AppButton variant="outline-secondary" @click="state.resolve(false)">
        {{ state.cancelLabel }}
      </AppButton>
      <AppButton :variant="state.variant" @click="state.resolve(true)">
        {{ state.confirmLabel }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
/**
 * The single host for useConfirm(). Mounted once in App.vue.
 *
 * Replaces window.confirm, which could not be themed, ignored the app's dark
 * mode, and blocked the tab. The promise-based API is unchanged, so call sites
 * did not have to move.
 */
import { confirmState } from '@/composables/useConfirm'
import AppModal from './AppModal.vue'
import AppButton from './AppButton.vue'

const state = confirmState
</script>
