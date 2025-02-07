<template>
  <div 
    class="terminal" 
    ref="terminal" 
    @keydown.space.prevent="continueConversation" 
    @click="continueConversation" 
    tabindex="0"
  >
    <div v-for="(msg, index) in messages" :key="index" class="message">
      <span class="prompt">{{ msg.sender }}:</span> <span v-html="msg.text"></span>
    </div>
    <div v-if="waitingForAI" class="loader">
      <pre>{{ currentLoaderFrame }}</pre>
    </div>
    <div v-if="waitingForUser" class="prompt">
      <span v-if="isMobile">[Tap to continue...]</span>
      <span v-else>[Press SPACE to continue...]</span>
    </div>
  </div>
</template>

<script setup>
import axios from "axios";
import { ref, onMounted, nextTick, onUnmounted } from "vue";

const messages = ref([]);
const terminal = ref(null);
const gamePayload = ref({});
const waitingForUser = ref(false);
const waitingForAI = ref(false);
const messageQueue = ref([]);
const isMobile = ref(false);

const loaderFrames = [
  "⠋ Loading...",
  "⠙ Loading...",
  "⠹ Loading...",
  "⠸ Loading...",
  "⠼ Loading...",
  "⠴ Loading...",
  "⠦ Loading...",
  "⠧ Loading...",
  "⠇ Loading...",
  "⠏ Loading..."
];
const currentLoaderFrame = ref(loaderFrames[0]);
let loaderInterval = null;

const startLoaderAnimation = () => {
  let index = 0;
  loaderInterval = setInterval(() => {
    currentLoaderFrame.value = loaderFrames[index];
    index = (index + 1) % loaderFrames.length;
  }, 100);
};

const stopLoaderAnimation = () => {
  clearInterval(loaderInterval);
};

const scrollToBottom = () => {
  nextTick(() => {
    if (terminal.value) {
      terminal.value.scrollTop = terminal.value.scrollHeight;
    }
  });
};

const addMessage = (sender, text) => {
  messages.value.push({ sender, text });
  scrollToBottom();
};

const simulateConversation = async () => {
  for (let index = 0; index < 30; index++) {
    try {
      waitingForAI.value = true;
      startLoaderAnimation();
      scrollToBottom();

      // Llama AI turn
      const llamaResponse = await axios.get(`/api/v1/llama`, {
        params: { llama_prompt: JSON.stringify(gamePayload.value) },
      });
      const llamaMessage = llamaResponse.data?.choices?.[0]?.message?.content || "No response";
      messageQueue.value.push({ sender: "Llama (Player 1)", text: llamaMessage });
      gamePayload.value = llamaMessage;

      // ChatGPT (DM) turn
      const chatResponse = await axios.get(`/api/v1/chatgpt`, {
        params: { chatgpt_prompt: JSON.stringify(gamePayload.value) },
      });
      const chatMessage = chatResponse.data || "No response";
      messageQueue.value.push({ sender: "ChatGPT (DM)", text: chatMessage });
      gamePayload.value = chatMessage;

      waitingForAI.value = false;
      stopLoaderAnimation();

      // Wait for user input before displaying messages
      await waitForUserInput();
    } catch (error) {
      console.error("Error in AI conversation:", error);
      messageQueue.value.push({ sender: "System", text: "An error occurred while fetching AI responses." });
      waitingForAI.value = false;
      stopLoaderAnimation();
      await waitForUserInput();
    }
  }
};

const waitForUserInput = async () => {
  return new Promise((resolve) => {
    waitingForUser.value = true;
    scrollToBottom();

    const handler = (event) => {
      if (event.key === " " || isMobile.value) {
        continueConversation();
        document.removeEventListener("keydown", handler);
        resolve();
      }
    };
    
    document.addEventListener("keydown", handler);
  });
};

const continueConversation = () => {
  if (waitingForUser.value && messageQueue.value.length > 0) {
    waitingForUser.value = false;
    const nextMessage = messageQueue.value.shift();
    addMessage(nextMessage.sender, nextMessage.text);
  }
};

onMounted(() => {
  isMobile.value = /Mobi|Android/i.test(navigator.userAgent); // Detect mobile device
  terminal.value.focus();
  simulateConversation();
});

onUnmounted(() => {
  stopLoaderAnimation();
});
</script>

<style scoped>
.terminal {
  background-color: black;
  color: #00ff00;
  font-family: "Courier New", monospace;
  padding: 10px;
  height: 300px;
  width: 100%;
  overflow-y: auto;
  border: 2px solid #00ff00;
  outline: none;
}

.message {
  margin: 5px 0;
}

.prompt {
  font-weight: bold;
  color: #00ff00;
}

.loader {
  color: #00ff00;
  white-space: pre-wrap;
  font-family: monospace;
  margin-top: 5px;
}
</style>