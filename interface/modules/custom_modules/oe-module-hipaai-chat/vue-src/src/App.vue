<script setup>
import { ref } from 'vue';
import ChatList from './components/ChatList.vue';
import ChatWindow from './components/ChatWindow.vue';

const selectedChatId = ref(null);
const currentMessages = ref([]); // Holds messages for the currently selected chat
const messageError = ref(null);

// Function to handle when a chat is selected in the ChatList component
async function handleChatSelected(chatId) {
  console.log('App.vue: Chat selected event received -', chatId);
  selectedChatId.value = chatId;
  currentMessages.value = []; // Clear previous messages
  messageError.value = null;

  if (!chatId) {
    return; // Do nothing if chatId is null/invalid
  }

  try {
    console.log(`App.vue: Fetching messages for chat ${chatId}...`);
    const response = await fetch(`index.php?action=getMessages&chat_id=${chatId}`);
    console.log(`App.vue: GetMessages API Response Status: ${response.status}`);

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({
          error: `HTTP error! Status: ${response.status}`
      }));
      console.error('App.vue: GetMessages API Error Response:', errorData);
      throw new Error(errorData.error || `HTTP error! Status: ${response.status}`);
    }

    const result = await response.json();
    console.log('App.vue: GetMessages API Success Response:', result); 

    if (result.success && Array.isArray(result.data)) {
      currentMessages.value = result.data;
    } else {
      console.error('App.vue: GetMessages API returned success=false or data is not an array:', result);
      throw new Error(result.error || 'Failed to fetch messages or invalid data format.');
    }

  } catch (err) {
      console.error('App.vue: Error fetching messages:', err);
      messageError.value = err.message || 'An unknown error occurred while fetching messages.';
      currentMessages.value = []; // Clear messages on error
  }
}

// Function to handle when a NEW chat is created in ChatList
function handleChatCreated(newChatData) {
  console.log('App.vue: Chat created event received -', newChatData);
  selectedChatId.value = newChatData.chat_id;
  currentMessages.value = []; // New chat starts with no messages
  messageError.value = null;  // Clear any previous errors
  // NOTE: We do NOT fetch messages here, as it's a new chat.
}

// Function to handle when a chat is deleted in ChatList
function handleChatDeleted(deletedChatId) {
  console.log('App.vue: Chat deleted event received -', deletedChatId);
  // Check if the deleted chat is the currently selected one
  if (selectedChatId.value === deletedChatId) {
    console.log('App.vue: Currently selected chat was deleted. Resetting view.');
    selectedChatId.value = null; // Clear selected ID
    currentMessages.value = []; // Clear messages
    messageError.value = null; // Clear any previous errors
  }
}

// Function to handle sending a message (triggered by ChatWindow)
async function handleSendMessage(messageText) {
  const chatId = selectedChatId.value;
  if (!chatId) {
      console.error("App.vue: Cannot send message, no chat selected.");
      messageError.value = "Cannot send message, no chat selected.";
      return;
  }
  console.log('App.vue: Send message event received -', messageText);
  
  // Optimistic Update: Add user message immediately
  const userMessage = { role: 'user', content: messageText };
  currentMessages.value.push(userMessage);

  // Add Placeholder Assistant Message
  const placeholderId = `temp-loading-${Date.now()}`; // Unique ID for placeholder
  const loadingPlaceholder = {
      message_id: placeholderId, // Assign temporary ID for finding later
      role: 'assistant-loading', 
      content: 'Assistant is thinking...'
  };
  currentMessages.value.push(loadingPlaceholder);
  
  // Clear previous errors
  messageError.value = null; 

  try {
    console.log(`App.vue: Sending message to backend for chat ${chatId}:`, messageText);
    const response = await fetch('index.php?action=sendMessage', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ 
            chat_id: chatId, 
            message_content: messageText 
        })
    });

    console.log(`App.vue: SendMessage API Response Status: ${response.status}`);

    if (!response.ok) {
      // Attempt to parse error JSON, provide fallback
      const errorData = await response.json().catch(() => ({
          error: `HTTP error! Status: ${response.status}`
      }));
      console.error('App.vue: SendMessage API Error Response:', errorData);
      // Remove the optimistic user message if the send failed?
      // currentMessages.value.pop(); // Optional: Revert optimistic update on error
      const placeholderIndex = currentMessages.value.findIndex(msg => msg.message_id === placeholderId);
      if (placeholderIndex > -1) currentMessages.value.splice(placeholderIndex, 1);
      throw new Error(errorData.error || `HTTP error! Status: ${response.status}`);
    }

    const result = await response.json();
    console.log('App.vue: SendMessage API Success Response:', result);

    // Find and Replace Placeholder
    const placeholderIndex = currentMessages.value.findIndex(msg => msg.message_id === placeholderId);
    if (result.success && result.data && result.data.role === 'assistant') {
        if (placeholderIndex > -1) {
            // Replace placeholder with actual response
            currentMessages.value.splice(placeholderIndex, 1, result.data);
        } else {
             // Fallback: Placeholder not found, just push
             console.warn("App.vue: Loading placeholder not found, pushing assistant message.");
             currentMessages.value.push(result.data);
        }
    } else {
        // Handle API success=false or invalid data
        if (placeholderIndex > -1) currentMessages.value.splice(placeholderIndex, 1); // Remove placeholder on error
        console.error('App.vue: SendMessage API returned success=false or invalid data format:', result);
        throw new Error(result.error || 'Invalid response received from server after sending message.');
    }

  } catch (err) {
    // Handle fetch/processing errors
    const placeholderIndex = currentMessages.value.findIndex(msg => msg.message_id === placeholderId);
    if (placeholderIndex > -1) currentMessages.value.splice(placeholderIndex, 1); // Remove placeholder on error
    console.error('App.vue: Error sending message:', err);
    messageError.value = err.message || 'An unknown error occurred while sending the message.';
    // Optional: Add an error message object to the chat? 
    // currentMessages.value.push({ role: 'error', content: messageError.value });
  }
}

</script>

<template>
  <div id="chat-layout">
    <ChatList 
      @chat-selected="handleChatSelected" 
      @chat-created="handleChatCreated"  
      @chat-deleted="handleChatDeleted" 
      />
    <ChatWindow 
      :selected-chat-id="selectedChatId" 
      :messages="currentMessages" 
      :error="messageError"
      @send-message="handleSendMessage"
      /> 
  </div>
</template>

<style scoped>
/* Styles for overall layout */
#chat-layout {
  display: flex;
  width: 100%; 
  height: 100%; /* Use 100% to fill parent #app */
  box-sizing: border-box; 
  overflow: hidden; /* Added: Prevent children causing scrollbars */
}

/* Responsive behavior remains the same */
@media (max-width: 768px) {
  #chat-layout {
    flex-direction: column;
    height: auto; /* Allow content to determine height on small screens */
    width: 100%;
  }
}
</style>
