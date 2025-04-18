<?php

// Assuming openemr.bootstrap.php sets up the necessary environment,
// including autoloading, session start, and global functions/constants.

namespace OpenEMR\Modules\HipaaiChat\Api;

// use OpenEMR\Db\Db; // Keep use statement for clarity, but autoloader should handle it

class ChatController
{
    /**
     * Fetches the list of chat sessions for the currently logged-in user.
     */
    public function getChats()
    {
        // Get user ID from session (already checked in index.php)
        $currentUserId = $_SESSION['authUserID'];

        // --- REMOVE Direct Include for Db class ---
        // $openemr_root_path_from_here = dirname(__DIR__, 6);
        // $db_class_path = $openemr_root_path_from_here . '/src/Db/Db.php';
        // if (file_exists($db_class_path)) {
        //     require_once $db_class_path;
        // } else {
        //      throw new \Exception("Core Db class file not found at: " . htmlspecialchars($db_class_path));
        // }
        // --- End REMOVE Direct Include ---

        $chats = [];
        try {
            // Use legacy sql functions available in this environment
            $sql = "SELECT chat_id, title, updated_at
                    FROM hipaaichat_sessions
                    WHERE user_id = ?
                    ORDER BY updated_at DESC";

            // Use sqlStatement with parameter binding
            $resultSet = \sqlStatement($sql, [$currentUserId]);

            // Fetch results using sqlFetchArray
            while ($row = \sqlFetchArray($resultSet)) {
                $chats[] = $row;
            }

            return $chats;

        } catch (\Throwable $e) {
            // Re-throw for the index.php handler which logs details
            throw new \Exception("Error fetching chats: " . $e->getMessage(), 0, $e);
        }
    }

    // We might add other methods here later (createChat, getMessages, etc.)
}

// How this controller gets instantiated and the method called depends on the routing mechanism.
// For a simple API endpoint, sometimes a direct procedural call is used after including this file.
// Example (might need adjustment based on OpenEMR routing):
// $controller = new ChatController();
// $controller->getChats(); 