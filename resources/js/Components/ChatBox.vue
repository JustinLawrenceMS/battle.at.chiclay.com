<template>
  <div class="terminal" ref="terminal">
    <div v-for="(msg, index) in messages" :key="index" class="message">
      <span class="prompt">{{ msg.sender }}:</span> <span v-html="msg.text"></span>
    </div>
  </div>
</template>

<script setup>
import axios from "axios";
import { ref, onMounted, nextTick } from "vue";

const messages = ref([]);
const terminal = ref(null);
const gamePayload = ref({});
const delay = 3000;

const addMessage = (sender, text) => {
  messages.value.push({ sender, text });

  nextTick(() => {
    if (terminal.value) {
      terminal.value.scrollTop = terminal.value.scrollHeight;
    }
  });
};

const simulateConversation = async () => {
  for (let index = 0; index < 4; index++) {
    try {
      // Llama AI turn
      const llamaResponse = await axios.get(`/api/v1/llama`, {
        params: { llama_prompt: JSON.stringify(gamePayload.value) },
      });
      const llamaMessage = llamaResponse.data?.choices?.[0]?.message?.content || "No response";
      addMessage("Llama (Player 1)", llamaMessage);

      gamePayload.value = llamaMessage;
      // ChatGPT (DM) turn
      const chatResponse = await axios.get(`/api/v1/chatgpt`, {
        params: { chatgpt_prompt: JSON.stringify(gamePayload.value) },
      });
      const chatMessage = chatResponse || "No response";
      addMessage("ChatGPT (DM)", chatMessage.data);
      gamePayload.value = chatMessage.data;
    } catch (error) {
      console.error("Error in AI conversation:", error);
      addMessage("System", "An error occurred while fetching AI responses.");
    }

    await new Promise((resolve) => setTimeout(resolve, delay));
  }
};

onMounted(() => {
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
}

.message {
  margin: 5px 0;
}

.prompt {
  font-weight: bold;
  color: #00ff00;
}
</style>