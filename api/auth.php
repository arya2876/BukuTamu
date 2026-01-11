<?php
/**
 * AW Digital Guestbook - Authentication API
 * Handles login, register, logout, password reset
 */

require_once 'config.php';

// Set CORS headers
setCorsHeaders();

// Initialize database
initDatabase();

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Route requests
switch ($method) {
    case 'GET':
        if ($action === 'check') {
            checkAuth();
        } elseif ($action === 'csrf') {
            getCsrfToken();
        } elseif ($action === 'user') {
            getUser();
        } else {
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
        }
        break;
    case 'POST':
        switch ($action) {
            case 'login':
                login();
                break;
            case 'register':
                register();
                break;
            case 'logout':
                logout();
                break;
            case 'forgot':
                forgotPassword();
                break;
            case 'reset':
                resetPassword();
                break;
            default:
                jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
        }
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

/**
 * Check authentication status
 */
function checkAuth()
{
    jsonResponse([
        'success' => true,
        'authenticated' => isAuthenticated(),
        'user' => getCurrentUser()
    ]);
}

/**
 * Get CSRF token
 */
function getCsrfToken()
{
    jsonResponse([
        'success' => true,
        'token' => generateCsrfToken()
    ]);
}

/**
 * Get current user
 */
function getUser()
{
    if (!isAuthenticated()) {
        jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
    }

    jsonResponse([
        'success' => true,
        'data' => getCurrentUser()
    ]);
}

/**
 * Login user
 */
function login()
{
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    if (empty($input['email']) || empty($input['password'])) {
        jsonResponse(['success' => false, 'message' => 'Email dan password harus diisi'], 400);
    }

    $email = strtolower(trim($input['email']));
    $password = $input['password'];

    // Validate email format
    if (!isValidEmail($email)) {
        jsonResponse(['success' => false, 'message' => 'Format email tidak valid'], 400);
    }

    $conn = getConnection();

    // Find user by email
    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Email atau password salah'], 401);
    }

    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();

    // Verify password
    if (!verifyPassword($password, $user['password'])) {
        jsonResponse(['success' => false, 'message' => 'Email atau password salah'], 401);
    }

    // Set session
    setUserSession([
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role']
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Login berhasil',
        'data' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);
}

/**
 * Register new user
 */
function register()
{
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    $errors = [];

    if (empty($input['name'])) {
        $errors['name'] = 'Nama harus diisi';
    } elseif (strlen($input['name']) < 3) {
        $errors['name'] = 'Nama minimal 3 karakter';
    }

    if (empty($input['email'])) {
        $errors['email'] = 'Email harus diisi';
    } elseif (!isValidEmail($input['email'])) {
        $errors['email'] = 'Format email tidak valid';
    }

    if (empty($input['password'])) {
        $errors['password'] = 'Password harus diisi';
    } elseif (strlen($input['password']) < 6) {
        $errors['password'] = 'Password minimal 6 karakter';
    }

    if (empty($input['confirmPassword'])) {
        $errors['confirmPassword'] = 'Konfirmasi password harus diisi';
    } elseif ($input['password'] !== $input['confirmPassword']) {
        $errors['confirmPassword'] = 'Password tidak cocok';
    }

    if (!empty($errors)) {
        jsonResponse(['success' => false, 'errors' => $errors], 400);
    }

    $name = sanitizeInput($input['name']);
    $email = strtolower(trim($input['email']));
    $password = hashPassword($input['password']);

    $conn = getConnection();

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt->close();
        $conn->close();
        jsonResponse(['success' => false, 'errors' => ['email' => 'Email sudah terdaftar']], 400);
    }
    $stmt->close();

    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
    $stmt->bind_param("sss", $name, $email, $password);

    if ($stmt->execute()) {
        $userId = $conn->insert_id;
        $stmt->close();

        // Auto login after registration
        setUserSession([
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'role' => 'admin'
        ]);

        // Create default event for new user
        $eventName = "Event Pertama";
        $eventStmt = $conn->prepare("INSERT INTO events (user_id, name, description) VALUES (?, ?, 'Event default Anda')");
        $eventStmt->bind_param("is", $userId, $eventName);
        $eventStmt->execute();
        $eventId = $conn->insert_id;
        $eventStmt->close();

        // Set as current event
        setCurrentEventId($eventId);

        $conn->close();

        jsonResponse([
            'success' => true,
            'message' => 'Registrasi berhasil',
            'data' => [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'role' => 'admin'
            ]
        ], 201);
    } else {
        $stmt->close();
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Gagal mendaftarkan akun'], 500);
    }
}

/**
 * Logout user
 */
function logout()
{
    clearUserSession();
    jsonResponse([
        'success' => true,
        'message' => 'Logout berhasil'
    ]);
}

/**
 * Forgot password - send reset link
 */
function forgotPassword()
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['email'])) {
        jsonResponse(['success' => false, 'message' => 'Email harus diisi'], 400);
    }

    $email = strtolower(trim($input['email']));

    if (!isValidEmail($email)) {
        jsonResponse(['success' => false, 'message' => 'Format email tidak valid'], 400);
    }

    $conn = getConnection();

    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        // Don't reveal if email exists or not
        jsonResponse(['success' => true, 'message' => 'Jika email terdaftar, link reset password akan dikirim']);
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Generate reset token
    $token = generateToken(32);
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Save reset token
    $updateStmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
    $updateStmt->bind_param("ssi", $token, $expires, $user['id']);
    $updateStmt->execute();
    $updateStmt->close();
    $conn->close();

    // In production, send email with reset link
    // For now, just return success
    // $resetLink = "http://localhost/Projek14%20PWD/reset-password.html?token=" . $token;

    jsonResponse([
        'success' => true,
        'message' => 'Jika email terdaftar, link reset password akan dikirim',
        // For development only - remove in production
        'dev_token' => $token
    ]);
}

/**
 * Reset password with token
 */
function resetPassword()
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['token']) || empty($input['password'])) {
        jsonResponse(['success' => false, 'message' => 'Token dan password baru harus diisi'], 400);
    }

    if (strlen($input['password']) < 6) {
        jsonResponse(['success' => false, 'message' => 'Password minimal 6 karakter'], 400);
    }

    $token = $input['token'];
    $password = hashPassword($input['password']);

    $conn = getConnection();

    // Find user by token
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Token tidak valid atau sudah expired'], 400);
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Update password and clear token
    $updateStmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
    $updateStmt->bind_param("si", $password, $user['id']);
    $updateStmt->execute();
    $updateStmt->close();
    $conn->close();

    jsonResponse([
        'success' => true,
        'message' => 'Password berhasil direset. Silakan login dengan password baru.'
    ]);
}
?>