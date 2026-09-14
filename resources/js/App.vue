<template>
  <AppToast />
  <AppConfirm />
  <router-view />
  <ChatWidget />
</template>

<script setup>
import AppToast from './components/ui/AppToast.vue'
import AppConfirm from './components/ui/AppConfirm.vue'
import ChatWidget from './components/chatbot/ChatWidget.vue'
</script>
