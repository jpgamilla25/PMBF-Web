<template>
  <div class="pmbf-guest-layout">
    <div class="container">
      <div class="row justify-content-center align-items-center min-vh-100 py-5">
        <div class="col-sm-10 col-md-8 col-lg-5 col-xl-4">
          <!-- Logo/Title -->
          <div class="text-center mb-4">
            <div class="pmbf-guest-logo mb-3">
              <i class="bi bi-bank fs-1 text-white"></i>
            </div>
            <h2 class="text-white fw-bold mb-1">PMBF</h2>
            <p class="text-white-50 small">PhilRice Mutual Benefit Fund</p>
          </div>

          <!-- Card -->
          <div class="card shadow-lg border-0">
            <div class="card-body p-4">
              <slot />
            </div>
          </div>

          <p class="text-center text-white-50 small mt-4">
            &copy; {{ currentYear }} PMBF. All rights reserved.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const currentYear = new Date().getFullYear()
</script>

<style scoped>
.pmbf-guest-layout {
  min-height: 100vh;
  background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #1e40af 100%);
}

.pmbf-guest-logo {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.15);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(10px);
}

.card {
  border-radius: 1rem;
}
</style>
