<template>
  <AppToast />
  <router-view />
  <ChatWidget />
</template>

<script setup>
import AppToast from './components/ui/AppToast.vue'
import ChatWidget from './components/chatbot/ChatWidget.vue'
</script>
