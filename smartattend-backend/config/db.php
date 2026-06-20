<?php
// ============================================================
//  config/db.php
//  Central configuration for database + XMPP
//  Include this at the top of every API file:
//    require_once '../../config/db.php';
// ============================================================

// ---- MySQL Settings ----------------------------------------
define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // change to your MySQL user
define('DB_PASS', '');              // change to your MySQL password
define('DB_NAME', 'smartattend');

// ---- XMPP Settings -----------------------------------------
// Install ejabberd on localhost (see README)
define('XMPP_HOST',   'localhost');
define('XMPP_DOMAIN', 'college.edu');   // your XMPP domain
define('XMPP_PORT',   5222);            // standard XMPP port
define('XMPP_ADMIN',  'admin@college.edu');
define('XMPP_ADMIN_PASS', 'admin123');

// ---- App Settings ------------------------------------------
define('BASE_URL',        'http://localhost/smartattend-backend');
define('UPLOAD_DIR',      __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE',   5 * 1024 * 1024);  // 5 MB
define('PROOF_DEADLINE_DAYS', 3);            // days after event to upload proof

// ---- CORS Headers (allow frontend to call API) -------------
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================================
//  getDB()
//  Returns a MySQLi connection. Call this in every API file.
//  Usage:  $db = getDB();
// ============================================================
function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $conn->connect_error
        ]);
        exit();
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

// ============================================================
//  response()
//  Sends a JSON response and exits
//  Usage:  response(true, 'Done', ['id' => 5]);
// ============================================================
function response($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ]);
    exit();
}

// ============================================================
//  authCheck($allowed_roles)
//  Verifies session and role before allowing API access
//  Usage:  authCheck(['student'])  or  authCheck(['mentor','hod'])
// ============================================================
function authCheck($allowed_roles = []) {
    session_start();
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
        http_response_code(401);
        response(false, 'Unauthorized. Please login.');
    }
    if (!empty($allowed_roles) && !in_array($_SESSION['user_type'], $allowed_roles)) {
        http_response_code(403);
        response(false, 'Access denied for your role.');
    }
}

// ============================================================
//  generateReqCode()
//  Generates a unique request code like SA-2047
// ============================================================
function generateReqCode($db) {
    $result = $db->query("SELECT MAX(req_id) as max_id FROM leave_requests");
    $row    = $result->fetch_assoc();
    $next   = ($row['max_id'] ?? 2000) + 1;
    return 'SA-' . $next;
}
?>
