<template>
  <!-- Floating Chat Button -->
  <button
    v-if="!isOpen"
    @click="openChat"
    class="chat-fab"
    title="Ask PMBF Assistant"
  >
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
    </svg>
    <span v-if="!hasInteracted" class="chat-fab-pulse"></span>
  </button>

  <!-- Chat Panel -->
  <Transition name="chat-slide">
    <div v-if="isOpen" class="chat-panel">
      <!-- Header -->
      <div class="chat-header">
        <div class="chat-header-info">
          <div class="chat-header-avatar">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5" />
            </svg>
          </div>
          <div>
            <div class="chat-header-title">PMBF Assistant</div>
            <div class="chat-header-subtitle">Ask me anything about PMBF</div>
          </div>
        </div>
        <div class="chat-header-actions">
          <button @click="clearChat" class="chat-header-btn" title="Clear chat">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" />
            </svg>
          </button>
          <button @click="isOpen = false" class="chat-header-btn" title="Close">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Messages -->
      <div ref="messagesContainer" class="chat-messages">
        <!-- Welcome -->
        <div v-if="messages.length === 0" class="chat-welcome">
          <div class="chat-welcome-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456z" />
            </svg>
          </div>
          <h4 class="chat-welcome-title">Hi! I'm your PMBF Assistant</h4>
          <p class="chat-welcome-text">Ask me about loans, payments, benefits, and more.</p>
        </div>

        <!-- Messages -->
        <div v-for="(msg, idx) in messages" :key="idx" :class="['chat-msg-row', msg.role === 'user' ? 'chat-msg-right' : 'chat-msg-left']">
          <div :class="['chat-bubble', msg.role === 'user' ? 'chat-bubble-user' : 'chat-bubble-bot']">
            <div v-if="msg.role === 'model'" v-html="renderMarkdown(msg.content)"></div>
            <span v-else>{{ msg.content }}</span>
          </div>
        </div>

        <!-- Typing indicator -->
        <div v-if="isLoading" class="chat-msg-row chat-msg-left">
          <div class="chat-bubble chat-bubble-bot">
            <div class="chat-typing">
              <span class="chat-typing-dot" style="animation-delay: 0ms"></span>
              <span class="chat-typing-dot" style="animation-delay: 150ms"></span>
              <span class="chat-typing-dot" style="animation-delay: 300ms"></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Suggestions -->
      <div v-if="suggestions.length > 0 && !isLoading" class="chat-suggestions">
        <div class="chat-suggestions-label">Suggested questions</div>
        <div class="chat-suggestions-list">
          <button
            v-for="sug in suggestions"
            :key="sug.id"
            @click="selectSuggestion(sug.question)"
            class="chat-suggestion-btn"
          >
            {{ sug.question }}
          </button>
        </div>
      </div>

      <!-- Input -->
      <div class="chat-input-area">
        <form @submit.prevent="sendMessage" class="chat-input-form">
          <input
            ref="inputRef"
            v-model="inputText"
            @input="onInputChange"
            type="text"
            placeholder="Type your question..."
            class="chat-input"
            :disabled="isLoading"
            maxlength="1000"
          />
          <button
            type="submit"
            :disabled="!inputText.trim() || isLoading"
            class="chat-send-btn"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
            </svg>
          </button>
        </form>
        <div class="chat-powered-by">Powered by AI — answers may not always be accurate</div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ref, nextTick, onMounted } from 'vue'
import chatbotService from '../../services/chatbot'

const isOpen = ref(false)
const isLoading = ref(false)
const hasInteracted = ref(false)
const inputText = ref('')
const messages = ref([])
const suggestions = ref([])
const messagesContainer = ref(null)
const inputRef = ref(null)

let debounceTimer = null

onMounted(() => {
  loadSuggestions()
})

function openChat() {
  isOpen.value = true
  hasInteracted.value = true
  nextTick(() => inputRef.value?.focus())
}

async function sendMessage() {
  const text = inputText.value.trim()
  if (!text || isLoading.value) return

  messages.value.push({ role: 'user', content: text })
  inputText.value = ''
  isLoading.value = true
  suggestions.value = []
  scrollToBottom()

  try {
    const history = messages.value.slice(-11, -1).map(m => ({
      role: m.role,
      content: m.content,
    }))

    const { data } = await chatbotService.sendMessage(text, history)

    if (data.success && data.data?.reply) {
      messages.value.push({ role: 'model', content: data.data.reply })
    } else {
      messages.value.push({
        role: 'model',
        content: 'Sorry, I couldn\'t process that. Please try again or contact pmbf.philrice@gmail.com for support.',
      })
    }
  } catch {
    messages.value.push({
      role: 'model',
      content: 'I\'m having trouble connecting right now. Please try again in a moment.',
    })
  } finally {
    isLoading.value = false
    scrollToBottom()
    loadSuggestions(text)
  }
}

function selectSuggestion(question) {
  inputText.value = question
  sendMessage()
}

function onInputChange() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    loadSuggestions(inputText.value)
  }, 300)
}

async function loadSuggestions(query = '') {
  try {
    const { data } = await chatbotService.getSuggestions(query)
    if (data.success) {
      suggestions.value = data.data || []
    }
  } catch {
    // Suggestions are optional
  }
}

function clearChat() {
  messages.value = []
  suggestions.value = []
  loadSuggestions()
}

function scrollToBottom() {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

function renderMarkdown(text) {
  if (!text) return ''
  let html = text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
  html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
  html = html.replace(/\*(.+?)\*/g, '<em>$1</em>')
  html = html.replace(/`([^`]+)`/g, '<code class="chat-code-inline">$1</code>')
  html = html.replace(/```[\s\S]*?```/g, (match) => {
    const code = match.replace(/```\w*\n?/g, '').replace(/```/g, '')
    return `<pre class="chat-code-block"><code>${code}</code></pre>`
  })
  html = html.replace(/^### (.+)$/gm, '<div class="chat-heading">$1</div>')
  html = html.replace(/^## (.+)$/gm, '<div class="chat-heading chat-heading-lg">$1</div>')
  html = html.replace(/^[•\-\*] (.+)$/gm, '<div class="chat-list-item">• $1</div>')
  html = html.replace(/^\d+\.\s(.+)$/gm, '<div class="chat-list-item-num">$1</div>')
  html = html.replace(/\|(.+)\|\n\|[-|\s]+\|\n((?:\|.+\|\n?)*)/g, (match, header, body) => {
    const headers = header.split('|').filter(h => h.trim()).map(h => `<th>${h.trim()}</th>`).join('')
    const rows = body.trim().split('\n').map(row => {
      const cells = row.split('|').filter(c => c.trim()).map(c => `<td>${c.trim()}</td>`).join('')
      return `<tr>${cells}</tr>`
    }).join('')
    return `<table class="chat-table"><thead><tr>${headers}</tr></thead><tbody>${rows}</tbody></table>`
  })
  html = html.replace(/\n/g, '<br>')
  html = html.replace(/<br><br>/g, '<br>')
  return html
}
</script>

<style scoped>
/* ─── Floating Action Button ──────────────────────────── */
.chat-fab {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 9999;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  border: none;
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 4px 20px rgba(37, 99, 235, 0.4);
  transition: all 0.3s ease;
}
.chat-fab:hover {
  transform: scale(1.1);
  box-shadow: 0 6px 28px rgba(37, 99, 235, 0.5);
}
.chat-fab-pulse {
  position: absolute;
  top: -2px;
  right: -2px;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #ef4444;
  animation: pulse-dot 2s infinite;
}
@keyframes pulse-dot {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.7; transform: scale(1.3); }
}

/* ─── Chat Panel ──────────────────────────────────────── */
.chat-panel {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 9999;
  width: 400px;
  max-height: 600px;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid #e5e7eb;
}

/* ─── Header ──────────────────────────────────────────── */
.chat-header {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  padding: 16px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}
.chat-header-info {
  display: flex;
  align-items: center;
  gap: 12px;
}
.chat-header-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(255,255,255,0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
}
.chat-header-title {
  color: #fff;
  font-weight: 600;
  font-size: 14px;
}
.chat-header-subtitle {
  color: rgba(255,255,255,0.7);
  font-size: 12px;
}
.chat-header-actions {
  display: flex;
  align-items: center;
  gap: 4px;
}
.chat-header-btn {
  background: none;
  border: none;
  color: rgba(255,255,255,0.6);
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}
.chat-header-btn:hover {
  color: #fff;
  background: rgba(255,255,255,0.1);
}

/* ─── Messages Area ───────────────────────────────────── */
.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  background: #f8f9fa;
  min-height: 0;
}
.chat-messages::-webkit-scrollbar { width: 6px; }
.chat-messages::-webkit-scrollbar-track { background: transparent; }
.chat-messages::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }

/* ─── Welcome Screen ──────────────────────────────────── */
.chat-welcome {
  text-align: center;
  padding: 32px 16px;
}
.chat-welcome-icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: #dbeafe;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 12px;
  color: #3b82f6;
}
.chat-welcome-title {
  color: #1f2937;
  font-weight: 600;
  font-size: 16px;
  margin: 0 0 4px;
}
.chat-welcome-text {
  color: #6b7280;
  font-size: 13px;
  margin: 0;
}

/* ─── Message Bubbles ─────────────────────────────────── */
.chat-msg-row {
  display: flex;
  margin-bottom: 12px;
}
.chat-msg-right { justify-content: flex-end; }
.chat-msg-left { justify-content: flex-start; }

.chat-bubble {
  max-width: 85%;
  padding: 10px 14px;
  font-size: 13px;
  line-height: 1.5;
  word-wrap: break-word;
}
.chat-bubble-user {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: #fff;
  border-radius: 16px 16px 4px 16px;
}
.chat-bubble-bot {
  background: #fff;
  color: #1f2937;
  border-radius: 16px 16px 16px 4px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  border: 1px solid #f3f4f6;
}

/* ─── Typing Indicator ────────────────────────────────── */
.chat-typing {
  display: flex;
  gap: 5px;
  padding: 4px 0;
}
.chat-typing-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #9ca3af;
  animation: chat-bounce 1.4s ease-in-out infinite;
}
@keyframes chat-bounce {
  0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
  40% { transform: scale(1); opacity: 1; }
}

/* ─── Suggestions ─────────────────────────────────────── */
.chat-suggestions {
  padding: 10px 16px;
  background: #fff;
  border-top: 1px solid #f3f4f6;
  flex-shrink: 0;
}
.chat-suggestions-label {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #9ca3af;
  font-weight: 600;
  margin-bottom: 6px;
}
.chat-suggestions-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.chat-suggestion-btn {
  font-size: 11px;
  padding: 5px 12px;
  background: #eff6ff;
  color: #2563eb;
  border: 1px solid #dbeafe;
  border-radius: 20px;
  cursor: pointer;
  transition: all 0.2s;
  text-align: left;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.chat-suggestion-btn:hover {
  background: #dbeafe;
  border-color: #93c5fd;
}

/* ─── Input Area ──────────────────────────────────────── */
.chat-input-area {
  padding: 12px 16px;
  background: #fff;
  border-top: 1px solid #e5e7eb;
  flex-shrink: 0;
}
.chat-input-form {
  display: flex;
  align-items: center;
  gap: 8px;
}
.chat-input {
  flex: 1;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 10px 14px;
  font-size: 13px;
  background: #f9fafb;
  outline: none;
  transition: all 0.2s;
}
.chat-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  background: #fff;
}
.chat-input:disabled {
  opacity: 0.6;
}
.chat-send-btn {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  border: none;
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  flex-shrink: 0;
}
.chat-send-btn:hover:not(:disabled) {
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.4);
}
.chat-send-btn:disabled {
  background: #d1d5db;
  cursor: not-allowed;
}
.chat-powered-by {
  font-size: 10px;
  color: #9ca3af;
  text-align: center;
  margin-top: 6px;
}

/* ─── Markdown Content ────────────────────────────────── */
.chat-bubble-bot :deep(.chat-code-inline) {
  background: #f3f4f6;
  padding: 1px 5px;
  border-radius: 4px;
  font-size: 12px;
  font-family: monospace;
}
.chat-bubble-bot :deep(.chat-code-block) {
  background: #f3f4f6;
  border-radius: 8px;
  padding: 8px;
  font-size: 11px;
  overflow-x: auto;
  margin: 6px 0;
  font-family: monospace;
}
.chat-bubble-bot :deep(.chat-heading) {
  font-weight: 600;
  color: #111827;
  margin: 8px 0 4px;
}
.chat-bubble-bot :deep(.chat-heading-lg) {
  font-weight: 700;
  font-size: 14px;
}
.chat-bubble-bot :deep(.chat-list-item) {
  padding-left: 8px;
  margin: 2px 0;
}
.chat-bubble-bot :deep(.chat-list-item-num) {
  padding-left: 8px;
  margin: 2px 0;
}
.chat-bubble-bot :deep(.chat-table) {
  width: 100%;
  border-collapse: collapse;
  margin: 6px 0;
  font-size: 12px;
}
.chat-bubble-bot :deep(.chat-table th) {
  background: #f3f4f6;
  padding: 4px 8px;
  text-align: left;
  font-weight: 600;
  font-size: 11px;
}
.chat-bubble-bot :deep(.chat-table td) {
  padding: 4px 8px;
  border-top: 1px solid #e5e7eb;
}

/* ─── Transitions ─────────────────────────────────────── */
.chat-slide-enter-active,
.chat-slide-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.chat-slide-enter-from,
.chat-slide-leave-to {
  opacity: 0;
  transform: translateY(20px) scale(0.95);
}

/* ─── Responsive ──────────────────────────────────────── */
@media (max-width: 480px) {
  .chat-panel {
    width: calc(100vw - 16px);
    max-height: calc(100vh - 16px);
    bottom: 8px;
    right: 8px;
    border-radius: 12px;
  }
}
</style>
