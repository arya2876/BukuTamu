<?php
/**
 * AW Digital Guestbook - Guest API
 * CRUD Operations with QR Code Support (Event-filtered)
 */

require_once 'config.php';

// Set CORS headers
setCorsHeaders();

// Initialize database
initDatabase();

// Start session
startSecureSession();

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Route requests
switch ($method) {
    case 'GET':
        if ($action === 'stats') {
            getStats();
        } elseif ($action === 'verify') {
            verifyQRCode();
        } elseif ($action === 'single' && isset($_GET['id'])) {
            getGuestById();
        } else {
            getGuests();
        }
        break;
    case 'POST':
        if ($action === 'checkin') {
            checkInGuest();
        } else {
            createGuest();
        }
        break;
    case 'PUT':
        updateGuest();
        break;
    case 'DELETE':
        deleteGuest();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

/**
 * Get event ID from request or session
 */
function getEventId()
{
    // First check URL parameter
    if (isset($_GET['event_id'])) {
        return (int) $_GET['event_id'];
    }
    // Then check session
    return getCurrentEventId();
}

/**
 * Validate event ownership
 */
function validateEventAccess($eventId, $conn)
{
    if (!$eventId) {
        return false;
    }

    $userId = getCurrentUserId();
    if (!$userId) {
        return false;
    }

    $stmt = $conn->prepare("SELECT id FROM events WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $eventId, $userId);
    $stmt->execute();
    $result = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $result;
}

/**
 * Get all guests (filtered by event)
 */
function getGuests()
{
    $conn = getConnection();

    $eventId = getEventId();
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

    // Build query
    if ($eventId && isAuthenticated()) {
        // If logged in and event specified, validate ownership
        if (!validateEventAccess($eventId, $conn)) {
            $conn->close();
            jsonResponse(['success' => false, 'message' => 'Event tidak ditemukan'], 404);
        }

        $sql = "SELECT * FROM guests WHERE event_id = $eventId";
    } else if (isAuthenticated()) {
        // Get all guests for all user's events
        $userId = getCurrentUserId();
        $sql = "SELECT g.* FROM guests g 
                JOIN events e ON g.event_id = e.id 
                WHERE e.user_id = $userId";
    } else {
        // Public access - no guests (must be logged in)
        jsonResponse(['success' => true, 'data' => []]);
        return;
    }

    if (!empty($search)) {
        $sql .= " AND (g.nama LIKE '%$search%' OR g.email LIKE '%$search%' OR g.telepon LIKE '%$search%')";
    }

    $sql .= " ORDER BY created_at DESC";

    $result = $conn->query($sql);

    $guests = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $guests[] = formatGuest($row);
        }
    }

    $conn->close();
    jsonResponse(['success' => true, 'data' => $guests]);
}

/**
 * Get single guest by ID
 */
function getGuestById()
{
    $conn = getConnection();
    $id = (int) $_GET['id'];

    $result = $conn->query("SELECT * FROM guests WHERE id = $id");

    if ($result->num_rows > 0) {
        $guest = $result->fetch_assoc();

        // Validate access if logged in
        if (isAuthenticated()) {
            $eventId = $guest['event_id'];
            if ($eventId && !validateEventAccess($eventId, $conn)) {
                $conn->close();
                jsonResponse(['success' => false, 'message' => 'Akses ditolak'], 403);
            }
        }

        $guest = formatGuest($guest);
        $conn->close();
        jsonResponse(['success' => true, 'data' => $guest]);
    } else {
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Tamu tidak ditemukan'], 404);
    }
}

/**
 * Get statistics (filtered by event)
 */
function getStats()
{
    $conn = getConnection();

    $eventId = getEventId();

    if ($eventId && isAuthenticated()) {
        if (!validateEventAccess($eventId, $conn)) {
            $conn->close();
            jsonResponse(['success' => false, 'message' => 'Event tidak ditemukan'], 404);
        }
        $whereClause = "WHERE event_id = $eventId";
    } else if (isAuthenticated()) {
        $userId = getCurrentUserId();
        $whereClause = "WHERE event_id IN (SELECT id FROM events WHERE user_id = $userId)";
    } else {
        jsonResponse(['success' => true, 'data' => ['total' => 0, 'today' => 0, 'checkedIn' => 0, 'pending' => 0]]);
        return;
    }

    // Total guests
    $totalResult = $conn->query("SELECT COUNT(*) as total FROM guests $whereClause");
    $total = $totalResult->fetch_assoc()['total'];

    // Today's guests
    $todayResult = $conn->query("SELECT COUNT(*) as today FROM guests $whereClause AND DATE(tanggal) = CURDATE()");
    $today = $todayResult->fetch_assoc()['today'];

    // Checked in guests
    $checkedInResult = $conn->query("SELECT COUNT(*) as checked_in FROM guests $whereClause AND status = 'checked_in'");
    $checkedIn = $checkedInResult->fetch_assoc()['checked_in'];

    // Pending guests
    $pendingResult = $conn->query("SELECT COUNT(*) as pending FROM guests $whereClause AND status = 'pending'");
    $pending = $pendingResult->fetch_assoc()['pending'];

    $conn->close();
    jsonResponse([
        'success' => true,
        'data' => [
            'total' => (int) $total,
            'today' => (int) $today,
            'checkedIn' => (int) $checkedIn,
            'pending' => (int) $pending
        ]
    ]);
}

/**
 * Verify QR Code
 */
function verifyQRCode()
{
    $conn = getConnection();

    $code = isset($_GET['code']) ? $conn->real_escape_string($_GET['code']) : '';

    if (empty($code)) {
        jsonResponse(['success' => false, 'message' => 'Kode QR tidak valid'], 400);
        return;
    }

    // Parse the QR code format: AWDG-{id}-{token} or BUKUTAMU-{id}-{token} (legacy)
    $parts = explode('-', $code);

    if (count($parts) !== 3 || ($parts[0] !== 'AWDG' && $parts[0] !== 'BUKUTAMU')) {
        jsonResponse(['success' => false, 'message' => 'Format QR Code tidak valid'], 400);
        return;
    }

    $guestId = (int) $parts[1];
    $token = $conn->real_escape_string($parts[2]);

    // Find guest by ID and token
    $result = $conn->query("SELECT * FROM guests WHERE id = $guestId AND qr_token = '$token'");

    if ($result->num_rows > 0) {
        $guest = formatGuest($result->fetch_assoc());
        $conn->close();
        jsonResponse([
            'success' => true,
            'message' => 'Tamu terverifikasi',
            'data' => $guest
        ]);
    } else {
        $conn->close();
        jsonResponse([
            'success' => false,
            'message' => 'QR Code tidak terdaftar dalam sistem'
        ], 404);
    }
}

/**
 * Check in guest
 */
function checkInGuest()
{
    $conn = getConnection();

    $input = json_decode(file_get_contents('php://input'), true);
    $id = isset($input['id']) ? (int) $input['id'] : 0;

    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID tidak valid'], 400);
        return;
    }

    // Update status to checked_in
    $sql = "UPDATE guests SET status = 'checked_in', checked_in_at = NOW() WHERE id = $id";

    if ($conn->query($sql)) {
        $result = $conn->query("SELECT * FROM guests WHERE id = $id");
        $guest = formatGuest($result->fetch_assoc());
        $conn->close();
        jsonResponse([
            'success' => true,
            'message' => 'Tamu berhasil check-in',
            'data' => $guest
        ]);
    } else {
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Gagal check-in'], 500);
    }
}

/**
 * Create new guest
 */
function createGuest()
{
    requireAuth();

    $conn = getConnection();

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Get event ID
    $eventId = isset($input['event_id']) ? (int) $input['event_id'] : getEventId();

    if (!$eventId) {
        jsonResponse(['success' => false, 'message' => 'Event harus dipilih terlebih dahulu'], 400);
        return;
    }

    // Validate event ownership
    if (!validateEventAccess($eventId, $conn)) {
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Event tidak ditemukan'], 404);
        return;
    }

    // Validate input
    $errors = validateGuest($input);
    if (!empty($errors)) {
        jsonResponse(['success' => false, 'errors' => $errors], 400);
        return;
    }

    // Generate unique QR token
    $qrToken = bin2hex(random_bytes(8));

    // Sanitize input
    $nama = $conn->real_escape_string(trim($input['nama']));
    $email = $conn->real_escape_string(trim(strtolower($input['email'])));
    $telepon = $conn->real_escape_string(trim($input['telepon']));
    $pesan = $conn->real_escape_string(trim($input['pesan']));
    $tableNumber = isset($input['tableNumber']) ? $conn->real_escape_string(trim($input['tableNumber'])) : null;

    // Insert guest
    $stmt = $conn->prepare("INSERT INTO guests (event_id, nama, email, telepon, pesan, table_number, qr_token) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $eventId, $nama, $email, $telepon, $pesan, $tableNumber, $qrToken);

    if ($stmt->execute()) {
        $id = $conn->insert_id;
        $stmt->close();

        // Fetch the created guest
        $result = $conn->query("SELECT * FROM guests WHERE id = $id");
        $guest = formatGuest($result->fetch_assoc());

        $conn->close();
        jsonResponse([
            'success' => true,
            'message' => 'Tamu berhasil ditambahkan',
            'data' => $guest
        ], 201);
    } else {
        $stmt->close();
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Gagal menambahkan tamu'], 500);
    }
}

/**
 * Update guest
 */
function updateGuest()
{
    requireAuth();

    $conn = getConnection();

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate ID
    if (!isset($input['id']) || !is_numeric($input['id'])) {
        jsonResponse(['success' => false, 'message' => 'ID tidak valid'], 400);
        return;
    }

    $id = (int) $input['id'];

    // Check if guest exists and validate access
    $checkResult = $conn->query("SELECT event_id FROM guests WHERE id = $id");
    if ($checkResult->num_rows === 0) {
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Tamu tidak ditemukan'], 404);
        return;
    }

    $guestData = $checkResult->fetch_assoc();
    if ($guestData['event_id'] && !validateEventAccess($guestData['event_id'], $conn)) {
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Akses ditolak'], 403);
        return;
    }

    // Validate input
    $errors = validateGuest($input);
    if (!empty($errors)) {
        jsonResponse(['success' => false, 'errors' => $errors], 400);
        return;
    }

    // Sanitize input
    $nama = $conn->real_escape_string(trim($input['nama']));
    $email = $conn->real_escape_string(trim(strtolower($input['email'])));
    $telepon = $conn->real_escape_string(trim($input['telepon']));
    $pesan = $conn->real_escape_string(trim($input['pesan']));
    $tableNumber = isset($input['tableNumber']) ? $conn->real_escape_string(trim($input['tableNumber'])) : null;

    // Update guest
    $stmt = $conn->prepare("UPDATE guests SET nama=?, email=?, telepon=?, pesan=?, table_number=? WHERE id=?");
    $stmt->bind_param("sssssi", $nama, $email, $telepon, $pesan, $tableNumber, $id);

    if ($stmt->execute()) {
        $stmt->close();

        // Fetch the updated guest
        $result = $conn->query("SELECT * FROM guests WHERE id = $id");
        $guest = formatGuest($result->fetch_assoc());

        $conn->close();
        jsonResponse([
            'success' => true,
            'message' => 'Data tamu berhasil diperbarui',
            'data' => $guest
        ]);
    } else {
        $stmt->close();
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Gagal memperbarui data tamu'], 500);
    }
}

/**
 * Delete guest
 */
function deleteGuest()
{
    requireAuth();

    $conn = getConnection();

    // Get ID from query string or body
    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;

    if (!$id) {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = isset($input['id']) ? (int) $input['id'] : null;
    }

    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID tidak valid'], 400);
        return;
    }

    // Check if guest exists and validate access
    $checkResult = $conn->query("SELECT event_id FROM guests WHERE id = $id");
    if ($checkResult->num_rows === 0) {
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Tamu tidak ditemukan'], 404);
        return;
    }

    $guestData = $checkResult->fetch_assoc();
    if ($guestData['event_id'] && !validateEventAccess($guestData['event_id'], $conn)) {
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Akses ditolak'], 403);
        return;
    }

    // Delete guest
    $sql = "DELETE FROM guests WHERE id = $id";

    if ($conn->query($sql)) {
        $conn->close();
        jsonResponse([
            'success' => true,
            'message' => 'Data tamu berhasil dihapus'
        ]);
    } else {
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Gagal menghapus data tamu'], 500);
    }
}

/**
 * Format guest data for response
 */
function formatGuest($row)
{
    $prefix = defined('QR_PREFIX') ? QR_PREFIX : 'AWDG';

    return [
        'id' => (int) $row['id'],
        'eventId' => isset($row['event_id']) ? (int) $row['event_id'] : null,
        'nama' => $row['nama'],
        'email' => $row['email'],
        'telepon' => $row['telepon'],
        'pesan' => $row['pesan'],
        'message' => isset($row['message']) ? $row['message'] : null,
        'tableNumber' => isset($row['table_number']) ? $row['table_number'] : null,
        'qrToken' => $row['qr_token'],
        'qrCode' => $prefix . '-' . $row['id'] . '-' . $row['qr_token'],
        'status' => $row['status'] ?? 'pending',
        'checkedInAt' => $row['checked_in_at'],
        'tanggal' => $row['tanggal'],
        'createdAt' => strtotime($row['created_at']) * 1000
    ];
}

/**
 * Validate guest input
 */
function validateGuest($input)
{
    $errors = [];

    // Validate nama
    if (empty($input['nama'])) {
        $errors['nama'] = 'Nama harus diisi';
    } elseif (strlen($input['nama']) < 3) {
        $errors['nama'] = 'Nama minimal 3 karakter';
    } elseif (strlen($input['nama']) > 100) {
        $errors['nama'] = 'Nama maksimal 100 karakter';
    }

    // Validate email
    if (empty($input['email'])) {
        $errors['email'] = 'Email harus diisi';
    } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid';
    }

    // Validate telepon
    if (empty($input['telepon'])) {
        $errors['telepon'] = 'Nomor telepon harus diisi';
    } elseif (!preg_match('/^[0-9]{10,13}$/', preg_replace('/\D/', '', $input['telepon']))) {
        $errors['telepon'] = 'Nomor telepon harus 10-13 digit angka';
    }

    // Validate pesan
    if (empty($input['pesan'])) {
        $errors['pesan'] = 'Pesan harus diisi';
    } elseif (strlen($input['pesan']) < 10) {
        $errors['pesan'] = 'Pesan minimal 10 karakter';
    } elseif (strlen($input['pesan']) > 500) {
        $errors['pesan'] = 'Pesan maksimal 500 karakter';
    }

    return $errors;
}
?>