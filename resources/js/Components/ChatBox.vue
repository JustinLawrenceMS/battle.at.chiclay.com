<template>
  <div class="terminal" ref="terminal" @keydown.space.prevent="continueConversation" tabindex="0">
    <div v-for="(msg, index) in messages" :key="index" class="message">
      <span class="prompt">{{ msg.sender }}:</span> <span v-html="msg.text"></span>
    </div>
    <div v-if="waitingForUser" class="prompt">[Press SPACE to continue...]</div>
  </div>
</template>

<script setup>
import axios from "axios";
import { ref, onMounted, nextTick } from "vue";

const messages = ref([]);
const terminal = ref(null);
const gamePayload = ref({});
const waitingForUser = ref(false);
const messageQueue = ref([]);

const addMessage = (sender, text) => {
  messages.value.push({ sender, text });

  nextTick(() => {
    if (terminal.value) {
      terminal.value.scrollTop = terminal.value.scrollHeight;
    }
  });
};

const simulateConversation = async () => {
  for (let index = 0; index < 30; index++) {
    try {
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
      
      // Wait for user input before displaying messages
      await waitForUserInput();
    } catch (error) {
      console.error("Error in AI conversation:", error);
      messageQueue.value.push({ sender: "System", text: "An error occurred while fetching AI responses." });
      await waitForUserInput();
    }
  }
};

const waitForUserInput = async () => {
  return new Promise((resolve) => {
    waitingForUser.value = true;
    const handler = (event) => {
      if (event.key === " ") {
        waitingForUser.value = false;
        if (messageQueue.value.length > 0) {
          const nextMessage = messageQueue.value.shift();
          addMessage(nextMessage.sender, nextMessage.text);
        }
        document.removeEventListener("keydown", handler);
        resolve();
      }
    };
    document.addEventListener("keydown", handler);
  });
};

onMounted(() => {
  terminal.value.focus();
  simulateConversation();
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
</style>
