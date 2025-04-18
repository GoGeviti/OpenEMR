<?php

// Assuming openemr.bootstrap.php sets up the necessary environment,
// including autoloading, session start, and global functions/constants.

namespace OpenEMR\Modules\HipaaiChat\Api;

use OpenEMR\Modules\HipaaiChat\GlobalConfig; // Added use statement

// use OpenEMR\Db\Db; // REVERTED: Not using modern Db class

class ChatController
{
    /**
     * Fetches the list of chat sessions for the currently logged-in user.
     */
    public function getChats()
    {
        // Get user ID from session (already checked in index.php)
        $currentUserId = $_SESSION['authUserID'];

        $chats = [];
        try {
            $sql = "SELECT chat_id, title, updated_at
                    FROM hipaaichat_sessions
                    WHERE user_id = ?
                    ORDER BY updated_at DESC";

            // REVERTED: Use legacy sql functions
            $resultSet = \sqlStatement($sql, [$currentUserId]);

            while ($row = \sqlFetchArray($resultSet)) {
                $chats[] = $row;
            }

            // No need to close statement with legacy functions

            return $chats;

        } catch (\Throwable $e) {
            // Re-throw for the index.php handler which logs details
            error_log("Error fetching chats: " . $e->getMessage()); // Log locally for debugging
            throw new \Exception("Error fetching chats: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Creates a new chat session for the currently logged-in user.
     */
    public function createChat()
    {
        $currentUserId = $_SESSION['authUserID'];
        // Generate a default title (e.g., "New Chat - YYYY-MM-DD HH:MM:SS")
        $defaultTitle = "New Chat - " . date("Y-m-d H:i:s");

        try {
            $sql = "INSERT INTO hipaaichat_sessions (user_id, title, created_at, updated_at)
                    VALUES (?, ?, NOW(), NOW())";

            // REVERTED: Use legacy sql functions for INSERT
            \sqlStatement($sql, [$currentUserId, $defaultTitle]);

            // WORKAROUND: Use SQL's LAST_INSERT_ID() function via sqlStatement/sqlFetchArray
            $idResult = \sqlStatement("SELECT LAST_INSERT_ID() as last_id");
            $idRow = \sqlFetchArray($idResult);
            $newChatId = $idRow['last_id'] ?? null; // Extract the ID

            // No need to close statement

            if (!$newChatId) {
                 // Log the row data if ID fetch failed
                 error_log("Failed to retrieve last insert ID using SELECT LAST_INSERT_ID(). Row data: " . print_r($idRow, true));
                 throw new \Exception("Failed to retrieve last insert ID after creating chat session using SELECT LAST_INSERT_ID().");
            }

            // Return the details of the new chat
            return [
                'chat_id' => $newChatId,
                'title' => $defaultTitle,
                'user_id' => $currentUserId 
            ];

        } catch (\Throwable $e) {
             error_log("Error creating new chat session: " . $e->getMessage()); // Log locally for debugging
            // Re-throw for the index.php handler
            throw new \Exception("Error creating new chat session: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Fetches messages for a specific chat session, ensuring user ownership.
     *
     * @param int $chatId The ID of the chat session.
     * @return array List of messages.
     * @throws \Exception If chat not found or user does not have permission.
     */
    public function getMessages(int $chatId)
    {
        $currentUserId = $_SESSION['authUserID'];
        $messages = [];

        try {
            // 1. Verify Ownership
            error_log("DEBUG: Checking ownership for chat ID: {$chatId}"); // Debug Log
            $ownershipSql = "SELECT user_id FROM hipaaichat_sessions WHERE chat_id = ? LIMIT 1";
            $stmt = \sqlStatement($ownershipSql, [$chatId]);
            $sessionOwner = \sqlFetchArray($stmt);
            error_log("DEBUG: Ownership check result: " . print_r($sessionOwner, true)); // Debug Log

            if (!$sessionOwner) {
                throw new \Exception("Chat session not found.", 404); // Use 404 for not found
            }
            if ($sessionOwner['user_id'] != $currentUserId) {
                // Forbidden - User does not own this chat
                throw new \Exception("Access denied to this chat session.", 403); // Use 403 for forbidden
            }
            error_log("DEBUG: Ownership verified for chat ID: {$chatId}"); // Debug Log

            // --- REMOVED TEMPORARY DEBUG ---

            // 2. Fetch Messages if ownership verified (Restored & Corrected)
            error_log("DEBUG: Fetching messages for chat ID: {$chatId}"); // Debug Log
            // Corrected column names to match table.sql (sender, timestamp)
            $messagesSql = "SELECT message_id, chat_id, user_id, sender, content, timestamp
                            FROM hipaaichat_messages
                            WHERE chat_id = ?
                            ORDER BY timestamp ASC"; // Order messages chronologically

            $messagesStmt = \sqlStatement($messagesSql, [$chatId]);

            while ($row = \sqlFetchArray($messagesStmt)) {
                // Map database 'sender'/'timestamp' to frontend 'role'/'created_at' if needed, 
                // or update frontend to use 'sender'/'timestamp'. Sticking to 'role'/'content' for now.
                // Assuming frontend ChatWindow expects { role: 'user'/'assistant', content: '...' }
                $messages[] = [
                    // 'message_id' => $row['message_id'], // Include if needed
                    'role' => ($row['sender'] === 'ai') ? 'assistant' : 'user', // Map 'ai' to 'assistant'
                    'content' => $row['content']
                    // 'created_at' => $row['timestamp'] // Include if needed
                ];
            }
            error_log("DEBUG: Fetched " . count($messages) . " messages for chat ID: {$chatId}"); // Debug Log

            return $messages;

        } catch (\Throwable $e) {
            // Log the specific error
            error_log("Error fetching messages for chat ID {$chatId}: " . $e->getMessage()); 

            // Re-throw with appropriate code if set in the try block
            $code = $e->getCode() === 403 || $e->getCode() === 404 ? $e->getCode() : 500;
            throw new \Exception($e->getMessage(), $code, $e);
        }
    }

    /**
     * Saves a user message, calls PIIPS API, saves assistant response,
     * and returns the assistant's response.
     *
     * @param int $chatId
     * @param string $userMessageContent
     * @return array Assistant's message object.
     * @throws \Exception
     */
    public function sendMessage(int $chatId, string $userMessageContent)
    {
        global $GLOBALS; // Need access to $GLOBALS for GlobalConfig
        $currentUserId = $_SESSION['authUserID'];

        try {
            // 1. Verify Ownership 
            $ownershipSql = "SELECT user_id FROM hipaaichat_sessions WHERE chat_id = ? LIMIT 1";
            $stmt = \sqlStatement($ownershipSql, [$chatId]);
            $sessionOwner = \sqlFetchArray($stmt);
            if (!$sessionOwner) { throw new \Exception("Chat session not found.", 404); }
            if ($sessionOwner['user_id'] != $currentUserId) { throw new \Exception("Access denied.", 403); }

            // 2. Save User Message (Using output buffering workaround)
            $insertUserSql = "INSERT INTO hipaaichat_messages (chat_id, user_id, sender, content, timestamp)
                              VALUES (?, ?, 'user', ?, NOW())";
            ob_start();
            $insertResult = \sqlStatement($insertUserSql, [$chatId, $currentUserId, $userMessageContent]);
            $directOutput = ob_get_clean();
            if (!empty($directOutput)) {
                 error_log("Direct output captured from sqlStatement (user message insert, chat {$chatId}): " . $directOutput);
                 throw new \Exception("Database error inserting user message.", 500);
            }
            error_log("DEBUG: User message INSERT statement executed successfully for chat {$chatId}.");
            // We don't strictly need the user message ID right now

            // --- 3. Call External LLM (PIIPS) --- 
            
            // 3a. Get API Key
            error_log("PIIPS Call: Instantiating GlobalConfig...");
            $moduleConfig = new GlobalConfig($GLOBALS);
            $apiKey = $moduleConfig->getPiipsApiKey();
            if (empty($apiKey)) {
                $decryptionError = $moduleConfig->getGlobalSetting(GlobalConfig::CONFIG_PIIPS_API_KEY) ? "Decryption likely failed" : "Setting not found";
                error_log("PIIPS Call: ERROR - API Key is EMPTY. Reason: " . $decryptionError);
                throw new \Exception("PIIPS API Key is not configured or could not be decrypted.", 500);
            }
            error_log("PIIPS Call: API key retrieved (length: " . strlen($apiKey) . ").");
            
            // 3b. Fetch Full Conversation History (including new user message)
            $historySql = "SELECT sender, content FROM hipaaichat_messages WHERE chat_id = ? ORDER BY timestamp ASC";
            $historyStmt = \sqlStatement($historySql, [$chatId]);
            $formatted_text = "";
            while ($msg = \sqlFetchArray($historyStmt)) {
                $role = ($msg['sender'] === 'ai') ? 'Assistant' : 'User'; // Match openai_chat.php format
                $formatted_text .= $role . ": " . $msg['content'] . "\n\n";
            }
            $formatted_text = trim($formatted_text);
            error_log("PIIPS Call: Formatted text (length: " . strlen($formatted_text) . ") for chat {$chatId}.");

            // 3c. Prepare PIIPS Request Data
            $piips_api_endpoint = 'https://pii-protection-service-production.up.railway.app/pii-guard-llm';
            $piipsData = ['text' => $formatted_text];

            // 3d. Send Request via cURL to PIIPS
            error_log("PIIPS Call: Initializing cURL...");
            $ch = curl_init($piips_api_endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($piipsData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey // Use retrieved key
            ]);
            // Add other curl opts like timeout, proxy, SSL verification as needed
            // curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            // curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 second timeout for response
            // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Should be true in production
            // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            // curl_setopt($ch, CURLOPT_CAINFO, '/path/to/cacert.pem'); // Specify CA bundle

            error_log("PIIPS Call: Executing cURL request...");
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            $curl_errno = curl_errno($ch);
            curl_close($ch);
            error_log("PIIPS Call: cURL finished. HTTP Code: $httpcode, Curl Error: [$curl_errno] $curl_error");

            // 3e. Handle PIIPS Response
            if ($curl_error) {
                error_log("PIIPS Call: ERROR - cURL Error: [$curl_errno] " . $curl_error);
                throw new \Exception("Failed to communicate with PII Protection Service (cURL Error).");
            }

            $responseData = json_decode($response, true);
            $json_last_error = json_last_error();

            if ($httpcode >= 400 || $json_last_error !== JSON_ERROR_NONE || !isset($responseData['text'])) {
                $errorMessage = isset($responseData['error']) ? $responseData['error'] : (isset($responseData['detail']) ? $responseData['detail'] : 'Invalid response from PII Protection Service.');
                error_log("PIIPS Call: ERROR - Invalid/Error response. HTTP: $httpcode, JSON Error: " . json_last_error_msg() . ", Message: " . $errorMessage . ", Raw Response: " . $response);
                 // Pass PIIPS HTTP code if it's an error, otherwise 500
                throw new \Exception($errorMessage, $httpcode >= 400 ? $httpcode : 500);
            }
            
            $assistantResponseContent = $responseData['text'];
            error_log("PIIPS Call: Successfully received response for chat {$chatId}.");

            // --- 4. Save Assistant Message --- 
            error_log("DB Save: Attempting to insert assistant message for chat {$chatId}.");
            $insertAssistantSql = "INSERT INTO hipaaichat_messages (chat_id, user_id, sender, content, timestamp)
                                   VALUES (?, ?, 'ai', ?, NOW())";
            // Use output buffering again for the assistant insert
            ob_start();
            $insertAsstResult = \sqlStatement($insertAssistantSql, [$chatId, $currentUserId, $assistantResponseContent]);
            $directOutputAsst = ob_get_clean();
            if (!empty($directOutputAsst)) {
                 error_log("Direct output captured from sqlStatement (assistant message insert, chat {$chatId}): " . $directOutputAsst);
                 // Decide how critical this is - maybe log and return response anyway?
                 // For now, throw error:
                 throw new \Exception("Database error inserting assistant message.", 500);
            }
            error_log("DB Save: Assistant message INSERT statement executed successfully for chat {$chatId}.");
            // We don't strictly need the assistant message ID right now

            // 5. Return Assistant Response Content (or full object)
            return [
                'role' => 'assistant',
                'content' => $assistantResponseContent
            ];

        } catch (\Throwable $e) {
            // Log the specific error and code
            error_log("Error in sendMessage for chat ID {$chatId}: [Code: " . $e->getCode() . "] " . $e->getMessage()); 
            // Determine HTTP code based on exception code or default to 500
            $httpCode = in_array($e->getCode(), [400, 401, 403, 404, 429]) ? $e->getCode() : 500;
            // Re-throw exception to be caught by index.php handler which sets HTTP status
            throw new \Exception($e->getMessage(), $httpCode, $e); 
        }
    }

    /**
     * Deletes a specific chat session and its messages, ensuring user ownership.
     *
     * @param int $chatId The ID of the chat session to delete.
     * @return bool True on success.
     * @throws \Exception If chat not found, user does not have permission, or deletion fails.
     */
    public function deleteChat(int $chatId)
    {
        $currentUserId = $_SESSION['authUserID'];

        // 1. Verify Ownership (Essential for security)
        $ownershipSql = "SELECT user_id FROM hipaaichat_sessions WHERE chat_id = ? LIMIT 1";
        $stmt = \sqlStatement($ownershipSql, [$chatId]);
        $sessionOwner = \sqlFetchArray($stmt);

        if (!$sessionOwner) {
            throw new \Exception("Chat session not found.", 404); // Not Found
        }
        if ($sessionOwner['user_id'] != $currentUserId) {
            throw new \Exception("Access denied to delete this chat session.", 403); // Forbidden
        }

        // Note: Transaction handling with legacy functions can be tricky.
        // Ideally, wrap these in a transaction if your DB setup supports it easily.
        // For simplicity here, we'll delete messages first, then the session.

        try {
            // 2. Delete Messages associated with the chat
            $deleteMessagesSql = "DELETE FROM hipaaichat_messages WHERE chat_id = ?";
            // Use output buffering to check for errors with legacy functions
            ob_start();
            \sqlStatement($deleteMessagesSql, [$chatId]);
            $deleteMessagesOutput = ob_get_clean();
            if (!empty($deleteMessagesOutput)) {
                 error_log("Direct output captured from sqlStatement (delete messages, chat {$chatId}): " . $deleteMessagesOutput);
                 throw new \Exception("Database error deleting messages for chat {$chatId}.", 500);
            }
            error_log("Successfully deleted messages for chat ID: {$chatId}");

            // 3. Delete the Chat Session itself
            $deleteSessionSql = "DELETE FROM hipaaichat_sessions WHERE chat_id = ?";
             ob_start();
            \sqlStatement($deleteSessionSql, [$chatId]);
            $deleteSessionOutput = ob_get_clean();
             if (!empty($deleteSessionOutput)) {
                 error_log("Direct output captured from sqlStatement (delete session, chat {$chatId}): " . $deleteSessionOutput);
                 // Even if messages were deleted, the session deletion failed.
                 throw new \Exception("Database error deleting chat session {$chatId}.", 500);
            }
            error_log("Successfully deleted chat session ID: {$chatId}");

            return true; // Indicate success

        } catch (\Throwable $e) {
            // Log the specific error
            error_log("Error deleting chat ID {$chatId}: " . $e->getMessage());

            // Re-throw the original exception or a new one with a 500 code
            $code = $e->getCode() ?: 500; // Use original code if available (like 403/404), else 500
            throw new \Exception("Failed to delete chat session: " . $e->getMessage(), $code, $e);
        }
    }
}

// Removed old example code 