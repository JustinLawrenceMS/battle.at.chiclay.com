<template>
  <div 
    class="terminal" 
    ref="terminal" 
    @keydown.space.prevent="continueConversation($event)" 
    @click="continueConversation($event)" 
    tabindex="0"
  >
    <div v-for="(msg, index) in messages" :key="index" class="message">
      <span class="prompt">{{ msg.sender }}:</span> <span v-html="msg.text"></span>
    </div>
    <!-- This input appears when a human turn is activated -->
    <div v-if="waitingForHuman" class="prompt human-input" @click.stop>
      <span>Human (Player 2):</span>
      <input 
        type="text" 
        v-model="humanInput" 
        @keydown.space.stop
        @keyup.enter="submitHumanInput" 
        autofocus 
        placeholder="Enter your message">
    </div>
    <div v-if="waitingForAI" class="loader">
      <pre>{{ currentLoaderFrame }}</pre>
    </div>
    <div v-if="waitingForUser" class="prompt">
      <span v-if="isMobile">[Tap to continue...]</span>
      <span v-else>[Press SPACE to continue...]</span>
    </div>
  </div>
  <!-- Button to scroll to the latest message -->
  <button class="scroll-to-top" @click.stop="scrollToLatestMessage">↑</button>
  <!-- Button for a human player to jump in -->
  <button class="human-player" @click.stop="humanPlayerJumpIn">+</button>
</template>

<script setup>
import axios from "axios";
import { ref, onMounted, nextTick, onUnmounted } from "vue";

const isMobile = ref(false);
const messages = ref([]);
const humanInput = ref("");
const payload = ref("");
const terminal = ref(null);
const waitingForAI = ref(false);
const waitingForUser = ref(false);
const waitingForHuman = ref(false);
const currentResolver = ref(null); // to store the resolver for waitForUserInput

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

const scrollToTop = () => {
  nextTick(() => {
    if (terminal.value) {
      terminal.value.scrollTop = 0;
    }
  });
};

const scrollToLatestMessage = () => {
  console.log("scrollToLatestMessage triggered");
  nextTick(() => {
    const messageElements = terminal.value.querySelectorAll('.prompt');
    if(messageElements.length >= 2){
      // Use the penultimate prompt because the last one is typically the "Press SPACE..." prompt.
      const lastMessageElement = messageElements[messageElements.length - 2];
      lastMessageElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
};

const addMessage = (sender, text) => {
  messages.value.push({ sender, text });
  scrollToBottom();
};

const waitForUserInput = () => {
  return new Promise((resolve) => {
    // store the resolver so that it can be triggered by the Human Player button
    currentResolver.value = resolve;
    
    const handler = (event) => {
      // Ignore clicks coming from the scroll-to-top or human player buttons
      if (event.type === "click" && (event.target.closest('.scroll-to-top') || event.target.closest('.human-player'))) {
        return;
      }
  
      if (event.type === "keydown" && event.code === "Space") {
        event.preventDefault();
        terminal.value.removeEventListener("keydown", handler);
        terminal.value.removeEventListener("click", handler);
        currentResolver.value = null;
        resolve();
      } else if (event.type === "click") {
        terminal.value.removeEventListener("keydown", handler);
        terminal.value.removeEventListener("click", handler);
        currentResolver.value = null;
        resolve();
      }
    };

    terminal.value.addEventListener("keydown", handler);
    terminal.value.addEventListener("click", handler);
  });
};

const humanPlayerJumpIn = () => {
  console.log("Human Player button clicked");
  // Resolve any pending wait-for-user promise.
  if (currentResolver.value) {
    currentResolver.value();
    currentResolver.value = null;
  }
  // Now enable the human input field turn.
  waitingForHuman.value = true;
};

const submitHumanInput = async () => {
  // Add the human player's input as a new message.
  addMessage('Human (Player 2)', humanInput.value);
  waitingForHuman.value = false;
  
  // Send the human input to ChatGPT (not Llama AI).
  const response = await getChatGPTResponse(humanInput.value);
  addMessage('ChatGPT (DM)', response);
  
  // Clear the input.
  humanInput.value = "";
  
  // Add a prompt for the user to continue.
  waitingForUser.value = true;
  
  // Wait for user input before continuing.
  await waitForUserInput();
  waitingForUser.value = false;
};

const continueConversation = (event) => {
  // Ignore clicks originating from the human-player or scroll-to-top buttons.
  if (event && (event.target.closest('.human-player') || event.target.closest('.scroll-to-top'))) {
    return;
  }
  if (waitingForUser.value) {
    waitingForUser.value = false;
  }
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
pre {
  font-family: 'Courier New', Courier, monospace;
}

.terminal {
  background-color: black;
  color: #00ff00;
  font-family: "Courier New", monospace;
  padding: 10px;
  height: 631px;
  width: 80%;
  margin: 0 auto;
  margin-top: 15px;
  overflow-y: auto;
  border: 2px solid #00ff00;
  outline: none;
  position: relative;
}

@media screen and (max-width: 768px) {
  .terminal {
    width: 90%;
    height: 60vh;
  }
}

.message {
  margin: 5px 0;
}

.prompt {
  font-weight: bold;
  color: #00ff00;
}

.human-input input {
  margin-left: 0px;
  background: transparent;
  border: none;
  cursor: text;
  outline: none;
  color: #00ff00;
  padding: 5px;
}

input::placeholder {
  color: #00ff00;
}

.loader {
  color: #00ff00;
  white-space: pre-wrap;
  font-family: monospace;
  margin-top: 5px;
}

.scroll-to-top, .human-player {
  background-color: rgba(0, 0, 0, 0.5);
  color: #00ff00;
  border: 2px solid #00ff00; 
  padding: 10px;
  cursor: pointer;
  border-radius: 50%;
  font-size: 20px;
  line-height: 20px;
  text-align: center;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background-color 0.3s;
  z-index: 100;
  margin: 0 auto;
  margin-top: 11px;
}

/* Position Human Player button a bit to the left */
.human-player {
  right: 60px;
}

.scroll-to-top:hover, .human-player:hover {
  background-color: rgba(0, 0, 0, 0.7);
}
</style>
