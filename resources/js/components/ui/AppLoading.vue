<template>
  <!-- Full-screen overlay -->
  <Teleport v-if="overlay && loading" to="body">
    <div class="pmbf-loading-overlay">
      <div class="text-center text-white">
        <div class="spinner-border spinner-border-lg mb-3" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <div v-if="text">{{ text }}</div>
      </div>
    </div>
  </Teleport>

  <!-- Inline spinner -->
  <div v-if="!overlay && loading" class="text-center py-4">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
    <div v-if="text" class="mt-2 text-muted">{{ text }}</div>
  </div>
</template>

<script setup>
defineProps({
  loading: {
    type: Boolean,
    default: false,
  },
  text: {
    type: String,
    default: '',
  },
  overlay: {
    type: Boolean,
    default: false,
  },
})
</script>

<style scoped>
.pmbf-loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.spinner-border-lg {
  width: 3rem;
  height: 3rem;
}
</style>
