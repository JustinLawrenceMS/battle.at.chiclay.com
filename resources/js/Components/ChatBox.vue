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
                @keydown.enter.prevent="submitHumanInput"
                @keydown.space.stop
                @click.stop
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
            <!-- Always use the same continue prompt regardless of player join status -->
            <span>[tap or click to continue...]</span>
        </div>
    </div>
    <!-- Button to scroll to the latest message -->
    <button class="scroll-to-top" @click.stop="scrollToLatestMessage">↑</button>
    <!-- Button for a human player to jump in -->
    <button class="human-player" @click.stop="humanPlayerJumpIn">+</button>
</template>

<script setup>
import axios from "axios";
import { ref, onMounted, nextTick, onUnmounted, watch } from "vue";
import DOMPurify from "dompurify";

const isMobile = ref(false);
const messages = ref([]);
const humanInput = ref("");
const payload = ref("");
const terminal = ref(null);
const waitingForAI = ref(false);
const waitingForUser = ref(false);
const waitingForHuman = ref(false);
const currentResolver = ref(null);
const requestTimedOut = ref(3);
// Turn order variables.
const currentTurn = ref("player1"); // can be "player1", "player2", or "dm"
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
    if (waitingForUser.value && currentResolver.value) {
        currentResolver.value();
        currentResolver.value = null;
        waitingForUser.value = false;
    }
};

const joinOpportunity = ref(true); // Only available on the first round

const humanPlayerJumpIn = () => {
    if (!joinOpportunity.value) {
        // The join window is closed—simply ignore the click.
        return;
    }
    // Process the join request.
    humanJoined.value = true;
    //currentTurn.value = "player2";
    joinOpportunity.value = false; // Disable further join attempts.
    waitingForHuman.value = true;
};

const submitHumanInput = async () => {
    if (!waitingForHuman.value) {
        return;
    }

    const inputValue = humanInput.value.trim();
    if (!inputValue) return; // Ignore empty input

    waitingForHuman.value = false;
    addMessage("Human (Player 2)", " " + inputValue);

    player2Message.value = `this is Player 2. ${inputValue}`;
    payload.value += player2Message.value;
    payload.value = payload.value.slice(-15999); // Ensure it doesn’t exceed limits

    humanInput.value = "";
    currentTurn.value = "dm"; // Hand over to DM
};

// Fixed labels.
const player1Prefix = "This is player 1:";
const player2Prefix = "This is player 2:";

// Store individual prompts.
const player1Message = ref("");
const player2Message = ref("");

// In your simulateConversation function, update the player2 branch:
const simulateConversation = async () => {
    currentTurn.value = "player1";
    while (true) {
        if (currentTurn.value === "player1") {
            // Player 1’s turn: fetch AI response.
            waitingForAI.value = true;
            startLoaderAnimation();
            var response = await getGeminiResponse(payload.value);
            payload.value = response + payload.value;
            payload.value = payload.value.slice(-15999);
            stopLoaderAnimation();
            waitingForAI.value = false;
            player1Message.value = response || "No response";
            addMessage("Gemini (Player 1)", " " + player1Message.value);

            if (joinOpportunity.value) {
                addMessage(
                    "System",
                    "Press + to join the game as Player 2! (This is your only chance)"
                );

            }                
            
            waitingForUser.value = true;
            await waitForUserInput();
            waitingForUser.value = false; 
            joinOpportunity.value = false;
            currentTurn.value = humanJoined.value ? "player2" : "dm";
        } else if (currentTurn.value === "player2") {
            // Set flag to show input and then wait until submitHumanInput clears it.
            waitingForHuman.value = true;
            // Instead of awaiting a helper, poll until the human input has been submitted.
            while (currentTurn.value === "player2") {
                await waitForUserInput();
                waitingForHuman.value = false;
                currentTurn.value = "dm";
            }
            // When submitHumanInput runs, it should set waitingForHuman to false and currentTurn to "dm".
        } else if (currentTurn.value === "dm") {
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
            addMessage("ChatGPT (DM)", " " + (dmResponse || "No response"));
            waitingForUser.value = true;
            await waitForUserInput();
            waitingForUser.value = false;
            currentTurn.value = "player1";
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

        const response = await axios.post(
            "/api/v1/gemini",
            { gemini_prompt: { prompt } },
            { timeout: 15000 } 
        );
        stopLoaderAnimation();
        waitingForAI.value = false;
        return "this is Player 1. " + response.data.response || "No response";
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
        const response = await axios.post(
            "/api/v1/chatgpt",
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

onMounted(() => {
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
