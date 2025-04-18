<?php

// Suppress direct error output to ensure clean JSON responses
error_reporting(0);
ini_set('display_errors', 0);

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

            case 'createChat':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $newChat = $controller->createChat(); 
                    echo json_encode(['success' => true, 'data' => $newChat]);
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method Not Allowed for createChat']);
                }
                break;

            case 'getMessages':
                if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                    if (!isset($_GET['chat_id'])) {
                        http_response_code(400); // Bad Request
                        echo json_encode(['success' => false, 'error' => 'Missing chat_id parameter.']);
                        exit;
                    }
                    $chatId = (int)$_GET['chat_id']; // Cast to integer
                    if ($chatId <= 0) {
                         http_response_code(400); // Bad Request
                         echo json_encode(['success' => false, 'error' => 'Invalid chat_id parameter.']);
                         exit;
                    }
                    try {
                         $messages = $controller->getMessages($chatId); 
                         echo json_encode(['success' => true, 'data' => $messages]);
                     } catch (\Throwable $t) {
                         // Handle specific exceptions from getMessages (403, 404) or default to 500
                         $httpCode = $t->getCode() === 403 || $t->getCode() === 404 ? $t->getCode() : 500;
                         http_response_code($httpCode);
                         $error_details = "API Error in Module Page (" . basename(__FILE__) . ") Action 'getMessages': " . $t->getMessage() . " in " . $t->getFile() . ":" . $t->getLine();
                         error_log($error_details); // Log trace separately if needed

                         echo json_encode([
                             'success' => false, 
                             'error' => $t->getMessage(), // Send back the specific error (e.g., Access denied)
                             'debug_message' => ($httpCode === 500) ? 'An internal server error occurred.' : $t->getMessage()
                         ]); 
                     }
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method Not Allowed for getMessages']);
                }
                break;
            
            case 'sendMessage':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    // Get data from JSON body
                    $input = json_decode(file_get_contents('php://input'), true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                         http_response_code(400);
                         echo json_encode(['success' => false, 'error' => 'Invalid JSON input.']);
                         exit;
                    }

                    $chatId = filter_var($input['chat_id'] ?? null, FILTER_VALIDATE_INT);
                    $messageContent = trim($input['message_content'] ?? '');

                    if (!$chatId || $chatId <= 0) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Missing or invalid chat_id.']);
                        exit;
                    }
                    if (empty($messageContent)) {
                         http_response_code(400);
                         echo json_encode(['success' => false, 'error' => 'Missing message_content.']);
                         exit;
                    }

                    try {
                         // Call controller method
                         $assistantResponse = $controller->sendMessage($chatId, $messageContent); 
                         echo json_encode(['success' => true, 'data' => $assistantResponse]);
                     } catch (\Throwable $t) {
                         $httpCode = $t->getCode() === 403 || $t->getCode() === 404 ? $t->getCode() : 500;
                         http_response_code($httpCode);
                         $error_details = "API Error in Module Page (" . basename(__FILE__) . ") Action 'sendMessage': " . $t->getMessage();
                         error_log($error_details . " | Input: " . print_r($input, true)); 

                         echo json_encode([
                             'success' => false, 
                             'error' => $t->getMessage(),
                             'debug_message' => ($httpCode === 500) ? 'An internal server error occurred.' : $t->getMessage()
                         ]); 
                     }
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method Not Allowed for sendMessage']);
                }
                break;

            case 'deleteChat':
                if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
                    if (!isset($_GET['chat_id'])) {
                        http_response_code(400); // Bad Request
                        echo json_encode(['success' => false, 'error' => 'Missing chat_id parameter.']);
                        exit;
                    }
                    $chatId = filter_var($_GET['chat_id'], FILTER_VALIDATE_INT);
                    if (!$chatId || $chatId <= 0) {
                         http_response_code(400); // Bad Request
                         echo json_encode(['success' => false, 'error' => 'Invalid chat_id parameter.']);
                         exit;
                    }
                    try {
                        $controller->deleteChat($chatId);
                        echo json_encode(['success' => true]); // Return simple success on delete
                     } catch (\Throwable $t) {
                         // Handle specific exceptions (e.g., 403 Forbidden, 404 Not Found)
                         $httpCode = ($t->getCode() === 403 || $t->getCode() === 404) ? $t->getCode() : 500;
                         http_response_code($httpCode);
                         $error_details = "API Error in Module Page (" . basename(__FILE__) . ") Action 'deleteChat': " . $t->getMessage();
                         error_log($error_details . " | Chat ID: {$chatId}"); 

                         echo json_encode([
                             'success' => false, 
                             'error' => $t->getMessage(),
                             'debug_message' => ($httpCode === 500) ? 'An internal server error occurred.' : $t->getMessage()
                         ]); 
                     }
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method Not Allowed for deleteChat']);
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

    // --- Find Built Vue Assets ---
    $vue_dist_path = __DIR__ . '/../vue-src/dist/assets'; // Path to assets relative to this file
    $css_files = glob($vue_dist_path . '/index-*.css');
    $js_files = glob($vue_dist_path . '/index-*.js');

    // Determine asset URLs relative to the module's public directory
    $css_url = !empty($css_files) ? '../vue-src/dist/assets/' . basename($css_files[0]) : ''; 
    $js_url = !empty($js_files) ? '../vue-src/dist/assets/' . basename($js_files[0]) : '';

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>HIPAAi Chat</title>
        <style>
            /* Minimal style - body margin reset */
            body { margin: 0; }
            /* Basic height setting for root elements within iframe */
            html, body { height: 100%; }
            /* Override external margin/padding */
            #app {
                 height: 100%; 
                 width: 100%;
                 margin: 0 !important; 
                 padding: 0 !important;
            }
        </style>
        <?php if ($css_url): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($css_url); ?>">
        <?php else: ?>
            <!-- CSS file not found -->
            <style>body::before { content: 'Error: Vue CSS asset not found!'; color: red; padding: 10px; display: block; }</style>
        <?php endif; ?>
    </head>
    <body class="body_top">
        <!-- Remove the container and default loading text, Vue app will provide UI -->
        <div id="app"></div> 

        <?php if ($js_url): ?>
            <script type="module" src="<?php echo htmlspecialchars($js_url); ?>"></script>
        <?php else: ?>
             <!-- JS file not found -->
             <script>document.getElementById('app').innerHTML = '<p style="color: red;">Error: Vue JS asset not found!</p>';</script>
        <?php endif; ?>
    </body>
    </html>
    <?php
} 