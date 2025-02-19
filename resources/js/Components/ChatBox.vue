<template>
    <div
        class="terminal"
        ref="terminal"
        @keydown.space.prevent="continueConversation($event)"
        @click="continueConversation($event)"
        tabindex="0"
    >
        <div v-for="(msg, index) in messages" :key="index" class="message">
            <span class="prompt">{{ msg.sender }}:</span>
            <span v-html="msg.text"></span>
        </div>
        <!-- This input appears when a human turn is activated -->
        <div v-if="waitingForHuman" class="prompt human-input" @click.stop>
            <span>Human (Player 2): </span>
            <input
                type="text"
                v-model="humanInput"
                @keydown.space.stop
                @keyup.enter="submitHumanInput"
                autofocus
                :placeholder="
                    isMobile ? 'Tap to enter message…' : 'Enter your message'
                "
            />
        </div>
        <div v-if="waitingForAI" class="loader">
            <pre>{{ currentLoaderFrame }}</pre>
        </div>
        <div v-if="waitingForUser" class="prompt">
            <template v-if="humanJoined">
                <span v-if="isMobile">[Tap the (+) button to continue]</span>
                <span v-else>[Press the (+) button to continue]</span>
            </template>
            <template v-else>
                <span v-if="isMobile">[Tap to continue...]</span>
                <span v-else>[Press SPACE to continue...]</span>
            </template>
        </div>
    </div>
    <!-- Button to scroll to the latest message -->
    <button class="scroll-to-top" @click.stop="scrollToLatestMessage">↑</button>
    <!-- Button for a human player to jump in -->
    <button 
        class="human-player" 
        @click.stop="humanPlayerJumpIn" 
        @mouseenter="showJoinUnavailableMessage" 
        @mouseleave="resetJoinUnavailableMessage">
        +
    </button>
</template>

<script setup>
import axios from "axios";
import { ref, onMounted, nextTick, onUnmounted } from "vue";
import DOMPurify from "dompurify";

const isMobile = ref(false);
const messages = ref([]);
const humanInput = ref("");
const payload = ref("");
const terminal = ref(null);
const waitingForAI = ref(false);
const waitingForUser = ref(false);
const waitingForHuman = ref(false);
const terminalListenersEnabled = ref(true);
const currentResolver = ref(null);
const requestTimedOut = ref(3);
// Turn order variables.
const currentTurn = ref("player1"); // can be "player1", "player2", or "dm"
const humanRequested = ref(false);
const humanJoined = ref(false);

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
    "⠏ Loading...",
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

const scrollToLatestMessage = () => {
    nextTick(() => {
        const messageElements = terminal.value.querySelectorAll(".prompt");
        if (messageElements.length >= 2) {
            const lastMessageElement =
                messageElements[messageElements.length - 2];
            lastMessageElement.scrollIntoView({
                behavior: "smooth",
                block: "start",
            });
        }
    });
};

const addMessage = (sender, text) => {
    const safeText = DOMPurify.sanitize(text);
    messages.value.push({ sender, text: safeText });
    scrollToBottom();
};

const waitForUserInput = () => {
    return new Promise((resolve) => {
        currentResolver.value = resolve;
        const handler = (event) => {
            if (!terminalListenersEnabled.value) return;
            if (
                event.type === "click" &&
                (event.target.closest(".scroll-to-top") ||
                    event.target.closest(".human-player"))
            ) {
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

const continueConversation = (event) => {
    if (!terminalListenersEnabled.value) return;
    if (waitingForUser.value && currentResolver.value) {
        currentResolver.value();
        currentResolver.value = null;
        waitingForUser.value = false;
    }
};

const joinOpportunity = ref(true); // Only available on the first round

const humanPlayerJumpIn = () => {
  // Only allow join if we're in the join window
  if (!joinOpportunity.value) {
    // Silently ignore if join opportunity is over.
    return;
  }
  // If terminal listeners are disabled, re-enable and resolve waiting state.
  if (!terminalListenersEnabled.value) {
    terminalListenersEnabled.value = true;
    if (currentResolver.value) {
      currentResolver.value();
      currentResolver.value = null;
    }
    waitingForUser.value = false;
    return;
  }
  // Process a valid join:
  humanJoined.value = true;
  joinOpportunity.value = false; // disable further attempts
  terminalListenersEnabled.value = false;
  waitingForHuman.value = true;
};

const submitHumanInput = async () => {
    // Validate that it is indeed player 2's turn
    if (currentTurn.value !== "player2" || !waitingForHuman.value) {
        addMessage(
            "System",
            " It's not your turn yet. Wait for player 1."
        );
        return;
    }
    waitingForHuman.value = false;

    if (requestTimedOut.value < 2) {
        humanInput.value = "Player 2 timed out";
        requestTimedOut.value++;
        return;
    }

    addMessage("Human (Player 2)", " " + humanInput.value);
    player2Message.value = "this is Player 2. " + humanInput.value;
    payload.value += player2Message.value;
    payload.value = payload.value.slice(-15999); // truncate to 16kb
    humanInput.value = "";
};

// Fixed labels.
const player1Prefix = "This is player 1:";
const player2Prefix = "This is player 2:";

// Store individual prompts.
const player1Message = ref("");
const player2Message = ref("");

// Modify simulateConversation to use Gemini for Player 1.
const simulateConversation = async () => {
  currentTurn.value = "player1";
  while (true) {
    if (currentTurn.value === "player1") {
      // Player 1’s turn: fetch AI response
      waitingForAI.value = true;
      startLoaderAnimation();
      var response = await getGeminiResponse(payload.value);
      // Update payload and messages
      payload.value = response + payload.value;
      payload.value = payload.value.slice(-15999);
      stopLoaderAnimation();
      waitingForAI.value = false;
      player1Message.value = response || "No response";
      addMessage("Gemini (Player 1)", " " + player1Message.value);

      // Only in the first round, offer a one-time join opportunity.
      if (joinOpportunity.value) {
        addMessage("System", "Press + to join the game as Player 2! (This is your only chance)");
        waitingForUser.value = true;
        // Wait for up to 5 seconds for the user to press +
        await new Promise(resolve => setTimeout(resolve, 5000));
        waitingForUser.value = false;
        // Disable further join attempts regardless of join success.
        joinOpportunity.value = false;
      }

      // Switch turn: if the join was successful, move to player2; otherwise, DM.
      if (humanJoined.value) {
        currentTurn.value = "player2";
      } else {
        currentTurn.value = "dm";
      }
    } else if (currentTurn.value === "player2") {
      // Wait for player2's input. This branch assumes that humanPlayerJumpIn set waitingForHuman.
      waitingForHuman.value = true;
      while (waitingForHuman.value) {
        await new Promise(r => setTimeout(r, 100));
      }
      currentTurn.value = "dm";
    } else if (currentTurn.value === "dm") {
      // DM turn: build prompt from available player messages
      let dmPrompt = humanJoined.value
        ? `${player1Prefix} ${player1Message.value}\n${player2Prefix} ${player2Message.value}`
        : `${player1Prefix} ${player1Message.value}`;
      waitingForAI.value = true;
      startLoaderAnimation();
      const dmResponse = await getChatGPTResponse(dmPrompt);
      payload.value += dmResponse;
      payload.value = payload.value.slice(-15999);
      stopLoaderAnimation();
      waitingForAI.value = false;
      addMessage("ChatGPT (DM)", " " + dmResponse || "No response");
      // After DM response, return to player1 turn.
      currentTurn.value = "player1";
      // Use a single waiting phase for continueConversation if no human join
      if (!humanJoined.value) {
        waitingForUser.value = true;
        await waitForUserInput();
        waitingForUser.value = false;
      }
    }
  }
};

const getGeminiResponse = async (prompt) => {
    try {
        waitingForAI.value = true;
        startLoaderAnimation();
        if (requestTimedOut.value < 2) {
            requestTimedOut.value++;
            return "Gemini response request timed out";
        }

        const response = await axios.post("/api/v1/gemini",
            { gemini_prompt: { prompt } }, // This is the request body
            { timeout: 15000 } // This is the config object
        );
        stopLoaderAnimation();
        waitingForAI.value = false;
        return 'this is Player 1. ' + response.data.response || "No response";
    } catch (error) {
        stopLoaderAnimation();
        waitingForAI.value = false;
        if (error.code === "ECONNABORTED") {
            console.error("Gemini response request timed out");
            return "Request timed out";
        }
        console.error(" Error fetching Gemini response:", error);
        return "Error fetching response";
    }
};

const getChatGPTResponse = async (prompt) => {
    try {
        waitingForAI.value = true;
        startLoaderAnimation();
        const response = await axios.post("/api/v1/chatgpt",
            { chatgpt_prompt: { prompt } },
            { timeout: 25000 }
        );
        stopLoaderAnimation();
        waitingForAI.value = false;
        return response.data || "No response";
    } catch (error) {
        stopLoaderAnimation();
        waitingForAI.value = false;
        if (error.code === "ECONNABORTED") {
            console.error("ChatGPT response request timed out");
            requestTimedOut.value = 0;
            return "Request timed out";
        }
        console.error("Error fetching ChatGPT response:", error);
        return "Error fetching response";
    }
};

// New flag to avoid spamming join unavailable message
const joinUnavailableMessageDisplayed = ref(false);

const showJoinUnavailableMessage = () => {
  if (!joinOpportunity.value && !joinUnavailableMessageDisplayed.value) {
    addMessage("System", "Join game is no longer available");
    joinUnavailableMessageDisplayed.value = true;
  }
};

const resetJoinUnavailableMessage = () => {
  // Clear the flag once the mouse leaves the button
  joinUnavailableMessageDisplayed.value = false;
};

onMounted(() => {
    isMobile.value = /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
    terminal.value.focus();
    simulateConversation();
});

onUnmounted(() => {
    stopLoaderAnimation();
});
</script>

<style scoped>
pre {
    font-family: "Courier New", Courier, monospace;
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

.scroll-to-top,
.human-player {
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

.human-player {
    right: 60px;
}

.scroll-to-top:hover,
.human-player:hover {
    background-color: rgba(0, 0, 0, 0.7);
}
</style>
