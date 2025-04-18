<script setup>
import { ref, defineProps, watch, defineEmits } from 'vue';

// Props definition - accepts messages and selected chat ID from parent
const props = defineProps({
  messages: {
    type: Array,
    default: () => [] // Default to empty array if no messages passed
  },
  selectedChatId: {
    type: [String, Number, null],
    default: null
  },
  error: {
      type: [String, null],
      default: null
  }
});

// Define emits (should be outside any function in <script setup>)
const emit = defineEmits(['send-message']);

const newMessage = ref(''); // Input field model
const messageContainer = ref(null); // Template ref for scrolling

// Function to handle sending a message
function sendMessage() {
  const text = newMessage.value.trim();
  if (text === '' || !props.selectedChatId) return; 

  console.log(`ChatWindow: Emitting send-message for chat ${props.selectedChatId}:`, text);
  // No need to import or define emit here anymore
  emit('send-message', text);

  newMessage.value = ''; // Clear input field
}

// Scroll to bottom when messages change 
watch(() => props.messages, () => {
    // Need a slight delay to allow DOM to update before scrolling
    setTimeout(() => {
      if (messageContainer.value) {
        messageContainer.value.scrollTop = messageContainer.value.scrollHeight;
      }
    }, 50); 
}, { deep: true, immediate: true }); // Changed immediate to true to scroll on initial load

</script>

<template>
  <main id="main-content">
    <div v-if="!selectedChatId" class="no-chat-selected">
      Please select a chat session from the sidebar to start messaging.
    </div>

    <template v-else>
        <!-- Message Display Area -->
        <div class="message-area" ref="messageContainer">
           <!-- Display error if message fetching failed -->
           <div v-if="error" class="error-message">Error loading messages: {{ error }}</div>
           <!-- Display no messages only if NOT loading (handled by placeholder now) and no error -->
           <div v-if="!error && messages.length === 0" class="no-messages">
               No messages in this chat yet. Send the first message!
           </div>
            <!-- Only show messages if no error -->
            <template v-if="!error">
                 <div v-for="(message) in messages" :key="message.message_id || message.content" class="message" :class="message.role"> 
                    <!-- Added key with fallback -->
                    <!-- Render loading indicator for assistant-loading role -->
                    <span v-if="message.role === 'assistant-loading'" class="loading-indicator">
                        {{ message.content }}
                    </span>
                    <!-- Render normal content otherwise -->
                    <span v-else class="content">
                        {{ message.content }}
                    </span> 
                 </div>
            </template>
        </div>

        <!-- Input Area -->
        <div class="input-area">
            <textarea 
                v-model="newMessage"
                placeholder="Type your message here..."
                @keydown.enter.prevent="sendMessage" 
                rows="3"
                :disabled="!!error" 
            ></textarea>
            <button @click="sendMessage" :disabled="!!error || !newMessage.trim()">
                Send
            </button>
        </div>
    </template>
  </main>
</template>

<style scoped>
#main-content {
  min-width: 0; 
  flex-grow: 1; /* ADDED: Allows this element to take remaining space */
  display: flex;
  flex-direction: column;
  height: 100%; /* Fill parent height */
  overflow: hidden; /* Prevent content overflow */
  background-color: #ffffff; /* Original bg restored */
  /* background-color: red !important; /* TEMPORARY DEBUG */
}

.no-chat-selected, .no-messages, .loading-message, .error-message {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100%;
    color: #6c757d;
    font-size: 1.1em;
    padding: 2rem;
    text-align: center;
}

.loading-message {
    /* Style differently if needed */
    font-style: italic;
}

.error-message {
    color: #dc3545; /* Bootstrap danger color */
    font-weight: bold;
}

.message-area {
  flex-grow: 1; /* Takes available space */
  overflow-y: auto; /* Enable scrolling for messages */
  padding: 1rem;
  border-bottom: 1px solid #e0e0e0;
}

.message {
  margin-bottom: 0.75rem;
  padding: 0.5rem 0.75rem;
  border-radius: 8px;
  max-width: 80%;
  word-wrap: break-word;
  line-height: 1.4;
}

.message.user {
  background-color: #d1e7ff;
  color: #0a58ca;
  margin-left: auto; /* Align user messages to the right */
  text-align: right;
}

.message.assistant {
  background-color: #f8f9fa;
  color: #212529;
  border: 1px solid #dee2e6;
  margin-right: auto; /* Align assistant messages to the left */
  text-align: left;
}

/* Removed role span */
.message .content {
    /* Style message content if needed */
}

.input-area {
  display: flex;
  padding: 0.75rem;
  border-top: 1px solid #e0e0e0;
  background-color: #f8f9fa;
}

.input-area textarea {
  flex-grow: 1;
  padding: 0.5rem;
  border: 1px solid #ced4da;
  border-radius: 4px;
  margin-right: 0.5rem;
  resize: none; /* Prevent manual resizing */
  font-family: inherit;
  font-size: 1rem;
}

.input-area button {
  padding: 0.5rem 1rem;
  background-color: #0d6efd;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 1rem;
}

.input-area button:hover {
  background-color: #0b5ed7;
}

.input-area button:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
}

.input-area textarea:disabled {
    background-color: #e9ecef;
    cursor: not-allowed;
}

/* Style for the loading placeholder message */
.message.assistant-loading {
    background-color: #f8f9fa; /* Same as assistant */
    color: #6c757d; /* Muted color */
    border: 1px solid #dee2e6;
    margin-right: auto; 
    text-align: left;
    font-style: italic;
}

.loading-indicator {
    /* Add specific styles if needed, but parent handles basics */
}

/* Styles for overall error message (if needed) */
.error-message {
    color: #dc3545; 
    font-weight: bold;
    height: 100%; /* Ensure it takes space if messages are empty */
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Remove styles related to the old top-level loading message if any */

/* Styles for input disable */
.input-area textarea:disabled {
    background-color: #e9ecef;
    cursor: not-allowed;
}
.input-area button:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
}
</style> 