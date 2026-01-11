<?php
/**
 * AW Digital Guestbook - Configuration
 * Database connection and utility functions
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'aw_guestbook');

// Application settings
define('APP_NAME', 'AW Digital Guestbook');
define('APP_VERSION', '2.0.0');
define('QR_PREFIX', 'AWDG');

// Session configuration
define('SESSION_LIFETIME', 86400); // 24 hours

/**
 * Start secure session
 */
function startSecureSession()
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        session_start();
    }
}

/**
 * Set CORS headers
 */
function setCorsHeaders()
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

/**
 * Get database connection
 */
function getConnection()
{
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        jsonResponse(['success' => false, 'message' => 'Database connection failed'], 500);
    }

    $conn->set_charset("utf8mb4");
    return $conn;
}

/**
 * Initialize database (create if not exists)
 */
function initDatabase()
{
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

    if ($conn->connect_error) {
        return false;
    }

    // Create database if not exists
    $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->close();

    return true;
}

/**
 * Generate CSRF token
 */
function generateCsrfToken()
{
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCsrfToken($token)
{
    startSecureSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if user is authenticated
 */
function isAuthenticated()
{
    startSecureSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function getCurrentUserId()
{
    startSecureSession();
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 */
function getCurrentUser()
{
    startSecureSession();
    if (!isAuthenticated()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'operator'
    ];
}

/**
 * Set user session
 */
function setUserSession($user)
{
    startSecureSession();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['logged_in_at'] = time();
}

/**
 * Clear user session
 */
function clearUserSession()
{
    startSecureSession();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Require authentication - returns error if not logged in
 */
function requireAuth()
{
    if (!isAuthenticated()) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized. Please login.'], 401);
    }
}

/**
 * Require admin role
 */
function requireAdmin()
{
    requireAuth();
    startSecureSession();
    if ($_SESSION['user_role'] !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'Access denied. Admin only.'], 403);
    }
}

/**
 * Get current event ID from session
 */
function getCurrentEventId()
{
    startSecureSession();
    return $_SESSION['current_event_id'] ?? null;
}

/**
 * Set current event ID
 */
function setCurrentEventId($eventId)
{
    startSecureSession();
    $_SESSION['current_event_id'] = $eventId;
}

/**
 * Hash password securely
 */
function hashPassword($password)
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash)
{
    return password_verify($password, $hash);
}

/**
 * Generate random token
 */
function generateToken($length = 32)
{
    return bin2hex(random_bytes($length));
}

/**
 * Sanitize input string
 */
function sanitizeInput($input)
{
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email format
 */
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}
?>