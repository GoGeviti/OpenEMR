<?php

// Define path to OpenEMR root directory
$openemr_root_path = dirname(__DIR__, 5); 
$openemr_interface_path = $openemr_root_path . '/interface'; 

// --- Bootstrap OpenEMR Environment using globals.php ---
// Relies on globals.php to set up autoloading, sessions, etc.
$GLOBALS['OE_MODULE_DIR'] = dirname(__DIR__); 
require_once $openemr_interface_path . '/globals.php'; 

use OpenEMR\Modules\HipaaiChat\Api\ChatController; 

// --- API Action Routing ---
$action = $_GET['action'] ?? null; // Get requested action

if ($action) {
    // --- Handle API Request ---
    header('Content-Type: application/json'); // Set header for all API responses

    // Ensure user is authenticated for API actions
    if (empty($_SESSION['authUserID'])) { 
        http_response_code(401); 
        echo json_encode(['error' => 'User not authenticated.']);
        exit;
    }

    try {
        // Check if Controller class exists before instantiation
        if (!class_exists('\OpenEMR\Modules\HipaaiChat\Api\ChatController')) {
             throw new \Exception("Autoloading Error: Controller class \OpenEMR\Modules\HipaaiChat\Api\ChatController not found.");
        }

        $controller = new ChatController();

        switch ($action) {
            case 'getChats':
                if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                    $chats = $controller->getChats(); 
                    echo json_encode(['success' => true, 'data' => $chats]);
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method Not Allowed for getChats']);
                }
                break;
            
            // Add cases for other actions 

            default:
                http_response_code(404);
                echo json_encode(['error' => 'Unknown API action requested.']);
        }

    } catch (\Throwable $t) { 
        http_response_code(500);
        $error_details = "API Error in Module Page (" . basename(__FILE__) . ") Action '{$action}': " . $t->getMessage() . " in " . $t->getFile() . ":" . $t->getLine();
        error_log($error_details . "\nTrace:\n" . $t->getTraceAsString()); // Log trace too

        // Send back a cleaner JSON error, check logs for details
        echo json_encode([
            'success' => false, 
            'error' => 'An internal server error occurred processing the API request.', 
            'debug_message' => $t->getMessage() // Keep message for debugging client-side if needed
            ]); 
    }
    
    exit; // Exit after handling API action

} else {
    // --- Load Module UI (Vue App) ---
     include_once($GLOBALS['srcdir'] . '/api.inc'); 
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>HIPAAi Chat</title>
        <link rel="stylesheet" href="vue-dist/assets/index.css"> 
    </head>
    <body class="body_top">
        <div class="container"> 
            <h1>HIPAAi Chat Module</h1>
            <p>Loading chat interface...</p>
            <div id="app"></div> 
        </div>
        <script type="module" src="vue-dist/assets/index.js"></script> 
    </body>
    </html>
    <?php
} 