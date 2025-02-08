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

const isMobile = ref(false);
const messages = ref([]);
const payload = ref("");
const terminal = ref(null);
const waitingForAI = ref(false);
const waitingForUser = ref(false);

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
  scrollToBottom();
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
  const conversationRounds = 30;
  payload.value = "You are a player in a Dungeons and Dragons game.  Create a character.";
  startLoaderAnimation();
  await waitForUserInput();
  stopLoaderAnimation();
  for (let round = 0; round < conversationRounds; round++) {
    try {
      payload.value = await getLlamaResponse(payload.value);
      
      addMessage('Llama (Player 1)', (payload.value || 'No response'));
      addMessage('Press SPACE or tap screen');
      await waitForUserInput();

      payload.value = await getChatGPTResponse(payload.value);
      addMessage('ChatGPT (DM)', (payload.value || 'No response'));

      if (round < conversationRounds - 1) {
        addMessage('Press SPACE or tap screen');
        await waitForUserInput();
      }

    } catch (error) {
      console.error('Error in AI conversation:', error);
      addMessage('System', 'An error occurred while fetching AI responses.');
    }
  }
};

const getLlamaResponse = async (prompt) => {
  try {
    waitingForAI.value = true;
    startLoaderAnimation();
    const response = await axios.get('/api/v1/llama', { params: { llama_prompt: { prompt } } });
    stopLoaderAnimation();
    waitingForAI.value = false;
    return response.data?.choices?.[0]?.message?.content || 'No response';
  } catch (error) {
    stopLoaderAnimation();
    waitingForAI.value = false;
    console.error('Error fetching Llama response:', error);
    return 'Error fetching response';
  }
};


const getChatGPTResponse = async (prompt) => {
  try {
    waitingForAI.value = true;
    startLoaderAnimation();
    const response = await axios.get('/api/v1/chatgpt', { params: { chatgpt_prompt: { prompt } } });
    console.dir(response);
    stopLoaderAnimation();
    waitingForAI.value = false;
    return response.data || 'No response';
  } catch (error) {
    stopLoaderAnimation();
    waitingForAI.value = false;
    console.error('Error fetching ChatGPT response:', error);
    return 'Error fetching response';
  }
};


const waitForUserInput = () => {
  return new Promise((resolve) => {
    const handler = (event) => {
      if (event.type === "keydown" && event.code === "Space") {
        event.preventDefault();
        document.removeEventListener("keydown", handler);
        document.removeEventListener("click", handler);
        resolve();
      } else if (event.type === "click") {
        document.removeEventListener("keydown", handler);
        document.removeEventListener("click", handler);
        resolve();
      }
    };

    document.addEventListener("keydown", handler);
    document.addEventListener("click", handler);
  });
};

const continueConversation = () => {
  if (waitingForUser.value) {
    waitingForUser.value = false;
  }
};

onMounted(() => {
  isMobile.value = /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
  terminal.value.focus();
  addMessage('Press SPACE or tap screen');
  waitForUserInput();
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
