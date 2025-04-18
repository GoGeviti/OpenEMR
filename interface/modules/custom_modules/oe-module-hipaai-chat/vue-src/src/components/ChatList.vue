<script setup>
import { ref, onMounted, defineEmits } from 'vue';

const chats = ref([]); // Reactive variable to hold the list of chats
const isLoading = ref(false); // Track loading state for list fetching
const isCreating = ref(false); // Track loading state for creation
const error = ref(null); // Store any fetch/create errors

// Define emits for this component
const emit = defineEmits(['chat-selected', 'chat-created', 'chat-deleted']);

// Function to fetch chat sessions from the backend
async function fetchChats() {
  isLoading.value = true;
  error.value = null;
  console.log('ChatList: Fetching chats from API...'); // Debug log

  try {
    const response = await fetch('index.php?action=getChats');
    console.log('ChatList: API Response Status:', response.status); // Debug log

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({
          error: `HTTP error! Status: ${response.status}`
      }));
      console.error('ChatList: API Error Response:', errorData);
      throw new Error(errorData.error || `HTTP error! Status: ${response.status}`);
    }

    const result = await response.json();
    console.log('ChatList: API Success Response:', result); // Debug log

    if (result.success && Array.isArray(result.data)) {
      chats.value = result.data;
    } else {
      console.error('ChatList: API returned success=false or data is not an array:', result);
      throw new Error(result.error || 'Failed to fetch chats or invalid data format.');
    }
  } catch (err) {
    console.error('ChatList: Error fetching chats:', err);
    error.value = err.message || 'An unknown error occurred while fetching chats.';
    chats.value = []; // Clear chats on error
  } finally {
    isLoading.value = false;
  }
}

// Function to handle clicking on a chat item
function selectChat(chatId) {
  console.log('ChatList: Chat selected -', chatId);
  emit('chat-selected', chatId); // Emit event to parent component
}

// Function to create a new chat session
async function createNewChat() {
    isCreating.value = true;
    error.value = null;
    console.log('ChatList: Attempting to create new chat...');

    try {
        const response = await fetch('index.php?action=createChat', {
            method: 'POST',
            headers: {
                // No Content-Type needed for POST without body
            },
            // No body needed for this specific create action
        });

        console.log('ChatList: Create Chat API Response Status:', response.status);

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({
                error: `HTTP error! Status: ${response.status}`
            }));
            console.error('ChatList: Create Chat API Error Response:', errorData);
            throw new Error(errorData.error || `HTTP error! Status: ${response.status}`);
        }

        const result = await response.json();
        console.log('ChatList: Create Chat API Success Response:', result);

        if (result.success && result.data && result.data.chat_id) {
            const createdChatApiData = result.data; // Data from createChat API { chat_id: '7715', ... }
            console.log('ChatList: New chat created successfully, ID from API:', createdChatApiData.chat_id);
            
            // Refresh the chat list to show the new chat
            await fetchChats(); 
            
            // Find the newly added chat in the refreshed list (should be the first one)
            if (chats.value.length > 0) {
                const newChatInList = chats.value[0];
                console.log('ChatList: Top chat in refreshed list, ID:', newChatInList.chat_id, 'Title:', newChatInList.title);
                
                // Emit event using the ID from the REFRESHED list
                emit('chat-created', { chat_id: newChatInList.chat_id, title: newChatInList.title });
            } else {
                console.error("ChatList: Refreshed chat list is empty after creation. Cannot emit chat-created event.");
                // Optionally emit with the API data as a fallback, but this is less likely to work
                // emit('chat-created', { chat_id: createdChatApiData.chat_id, title: createdChatApiData.title });
            }
            
            // REMOVED: Old emit logic
            // REMOVED: await new Promise(resolve => setTimeout(resolve, 50)); 
            // REMOVED: selectChat(newChatId);
        } else {
            console.error('ChatList: Create chat API returned success=false or invalid data:', result);
            throw new Error(result.error || 'Failed to create chat or invalid data format.');
        }
    } catch (err) {
        console.error('ChatList: Error creating new chat:', err);
        error.value = err.message || 'An unknown error occurred while creating the chat.';
    } finally {
        isCreating.value = false;
    }
}

// Function to confirm deletion
function confirmDeleteChat(chatId, chatTitle) {
  const title = chatTitle || `Chat ${chatId}`;
  if (confirm(`Are you sure you want to delete "${title}"? This action cannot be undone.`)) {
    deleteChat(chatId);
  }
}

// Function to delete a chat session via API
async function deleteChat(chatId) {
  console.log('ChatList: Attempting to delete chat:', chatId);
  // Ideally, add a loading state specific to this delete action
  error.value = null;
  let wasDeleted = false; // Flag to track successful deletion
  try {
    const response = await fetch(`index.php?action=deleteChat&chat_id=${chatId}`, {
      method: 'DELETE'
    });

    console.log('ChatList: Delete Chat API Response Status:', response.status);

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({ error: `HTTP error! Status: ${response.status}` }));
      console.error('ChatList: Delete Chat API Error Response:', errorData);
      throw new Error(errorData.error || `HTTP error! Status: ${response.status}`);
    }

    const result = await response.json();
    console.log('ChatList: Delete Chat API Success Response:', result);

    if (result.success) {
      console.log('ChatList: Chat deleted successfully, refreshing list...');
      // Refresh the list after deletion
      await fetchChats(); 
      wasDeleted = true; // Mark as successfully deleted
    } else {
      console.error('ChatList: Delete chat API returned success=false:', result);
      throw new Error(result.error || 'Failed to delete chat.');
    }

  } catch (err) {
    console.error('ChatList: Error deleting chat:', err);
    error.value = err.message || 'An unknown error occurred while deleting the chat.';
  } finally {
    // Turn off any specific delete loading state here
    // Emit the event AFTER the try...catch...finally block
    if (wasDeleted) {
      emit('chat-deleted', chatId); // Emit event if deletion was successful
    }
  }
}

// Fetch chats when the component is mounted
onMounted(fetchChats);
</script>

<template>
  <aside id="sidebar">
    <div class="sidebar-header">
        <h2>Chat Sessions</h2>
    </div>
    <button class="new-chat-button" @click="createNewChat" :disabled="isCreating || isLoading">
         {{ isCreating ? 'Creating...' : 'New Chat' }}
    </button>
    
    <div v-if="isLoading" class="loading-message">Loading chats...</div>
    <div v-if="error" class="error-message">Error: {{ error }}</div>
    <ul v-if="!isLoading && chats.length > 0">
      <li v-for="chat in chats" :key="chat.chat_id" @click="selectChat(chat.chat_id)">
        <span class="chat-title">{{ chat.title || `Chat ${chat.chat_id}` }}</span>
        <button 
           class="delete-button" 
           @click.stop="confirmDeleteChat(chat.chat_id, chat.title)"
           title="Delete Chat"
        >
          🗑️
        </button>
        <!-- Add timestamp if needed: <small>{{ chat.updated_at }}</small> -->
      </li>
    </ul>
    <div v-if="!isLoading && chats.length === 0 && !error">
      No chat sessions found.
    </div>
  </aside>
</template>

<style scoped>
#sidebar {
  width: 300px; /* Increased width */
  border-right: 1px solid #ccc;
  padding: 1rem;
  overflow-y: auto; 
  background-color: #f8f9fa; /* Original bg restored */
  /* background-color: blue !important; /* TEMPORARY DEBUG */
  height: 100%; 
  box-sizing: border-box;
  display: flex; /* Use flex column for easier spacing */
  flex-direction: column;
}

.sidebar-header {
    margin-bottom: 0.5rem; /* Reduced space below header */
}

.sidebar-header h2 {
    margin: 0 0 0.5rem 0; /* Add space below h2 */
}

/* Style for the moved New Chat button */
.new-chat-button {
    margin-bottom: 1rem; /* Space below button */
    padding: 0.5rem 1rem;
    cursor: pointer;
    width: 100%; /* Make button full width */
    box-sizing: border-box;
}

.loading-message,
.error-message {
  margin-top: 1rem;
  padding: 0.5rem;
  border-radius: 4px;
}
.loading-message {
  color: #004085;
  background-color: #cce5ff;
  border: 1px solid #b8daff;
}
.error-message {
  color: #721c24;
  background-color: #f8d7da;
  border: 1px solid #f5c6cb;
}

#sidebar ul {
  list-style: none;
  padding: 0;
  margin: 0; /* Removed top margin */
  flex-grow: 1; /* Allow list to take remaining space */
  overflow-y: auto; /* Scroll list independently if needed */
}

#sidebar li {
  padding: 0.5rem;
  cursor: pointer;
  border-bottom: 1px solid #eee;
  display: flex; /* Use flexbox to position elements */
  justify-content: space-between; /* Space between title and button */
  align-items: center; /* Vertically center items */
}

#sidebar li:hover {
  background-color: #e9ecef;
}

.chat-title {
    flex-grow: 1; /* Allow title to take available space */
    margin-right: 0.5rem; /* Add some space before the button */
    /* Optional: prevent title from wrapping if too long */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis; 
}

.delete-button {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    padding: 0.2rem; /* Adjust padding as needed */
    color: #dc3545; /* A common 'danger' color */
}

.delete-button:hover {
    color: #a02530; /* Darker red on hover */
}
</style> 