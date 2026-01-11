<?php
/**
 * AW Digital Guestbook - Events API
 * CRUD operations for events
 */

require_once 'config.php';

// Set CORS headers
setCorsHeaders();

// Initialize database
initDatabase();

// Check authentication
startSecureSession();

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Route requests
switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getEventById();
        } elseif ($action === 'stats') {
            getEventStats();
        } else {
            getEvents();
        }
        break;
    case 'POST':
        if ($action === 'switch') {
            switchEvent();
        } else {
            createEvent();
        }
        break;
    case 'PUT':
        updateEvent();
        break;
    case 'DELETE':
        deleteEvent();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

/**
 * Get all events for current user
 */
function getEvents()
{
    requireAuth();
    $userId = getCurrentUserId();
    $conn = getConnection();

    $includeArchived = isset($_GET['archived']) && $_GET['archived'] === 'true';

    $sql = "SELECT e.*, 
            (SELECT COUNT(*) FROM guests WHERE event_id = e.id) as guest_count,
            (SELECT COUNT(*) FROM guests WHERE event_id = e.id AND status = 'checked_in') as checked_in_count,
            (SELECT COUNT(*) FROM guests WHERE event_id = e.id AND status = 'pending') as pending_count
            FROM events e 
            WHERE e.user_id = ?";

    if (!$includeArchived) {
        $sql .= " AND e.is_archived = FALSE";
    }

    $sql .= " ORDER BY e.event_date DESC, e.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = formatEvent($row);
    }

    $stmt->close();
    $conn->close();

    jsonResponse(['success' => true, 'data' => $events]);
}

/**
 * Get single event by ID
 */
function getEventById()
{
    requireAuth();
    $userId = getCurrentUserId();
    $eventId = (int) $_GET['id'];

    $conn = getConnection();

    $stmt = $conn->prepare("SELECT e.*, 
            (SELECT COUNT(*) FROM guests WHERE event_id = e.id) as guest_count,
            (SELECT COUNT(*) FROM guests WHERE event_id = e.id AND status = 'checked_in') as checked_in_count,
            (SELECT COUNT(*) FROM guests WHERE event_id = e.id AND status = 'pending') as pending_count
            FROM events e 
            WHERE e.id = ? AND e.user_id = ?");
    $stmt->bind_param("ii", $eventId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Event tidak ditemukan'], 404);
    }

    $event = formatEvent($result->fetch_assoc());
    $stmt->close();
    $conn->close();

    jsonResponse(['success' => true, 'data' => $event]);
}

/**
 * Get event statistics
 */
function getEventStats()
{
    requireAuth();
    $eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : getCurrentEventId();

    if (!$eventId) {
        jsonResponse(['success' => false, 'message' => 'Event tidak dipilih'], 400);
    }

    $userId = getCurrentUserId();
    $conn = getConnection();

    // Verify ownership
    $stmt = $conn->prepare("SELECT id FROM events WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $eventId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $stmt->close();
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Event tidak ditemukan'], 404);
    }
    $stmt->close();

    // Get stats
    $stats = [];

    // Total guests
    $result = $conn->query("SELECT COUNT(*) as total FROM guests WHERE event_id = $eventId");
    $stats['total'] = (int) $result->fetch_assoc()['total'];

    // Checked in
    $result = $conn->query("SELECT COUNT(*) as count FROM guests WHERE event_id = $eventId AND status = 'checked_in'");
    $stats['checkedIn'] = (int) $result->fetch_assoc()['count'];

    // Pending
    $stats['pending'] = $stats['total'] - $stats['checkedIn'];

    // Today's check-ins
    $result = $conn->query("SELECT COUNT(*) as count FROM guests WHERE event_id = $eventId AND DATE(checked_in_at) = CURDATE()");
    $stats['todayCheckins'] = (int) $result->fetch_assoc()['count'];

    // Hourly check-ins for today
    $hourlyStats = [];
    $hourlyResult = $conn->query("SELECT HOUR(checked_in_at) as hour, COUNT(*) as count 
        FROM guests 
        WHERE event_id = $eventId AND DATE(checked_in_at) = CURDATE() 
        GROUP BY HOUR(checked_in_at) 
        ORDER BY hour");
    while ($row = $hourlyResult->fetch_assoc()) {
        $hourlyStats[] = [
            'hour' => (int) $row['hour'],
            'count' => (int) $row['count']
        ];
    }
    $stats['hourlyCheckins'] = $hourlyStats;

    $conn->close();

    jsonResponse(['success' => true, 'data' => $stats]);
}

/**
 * Create new event
 */
function createEvent()
{
    requireAuth();
    $userId = getCurrentUserId();

    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    $errors = validateEvent($input);
    if (!empty($errors)) {
        jsonResponse(['success' => false, 'errors' => $errors], 400);
    }

    $conn = getConnection();

    $name = sanitizeInput($input['name']);
    $description = isset($input['description']) ? sanitizeInput($input['description']) : null;
    $eventDate = isset($input['eventDate']) && !empty($input['eventDate']) ? $input['eventDate'] : null;
    $eventTime = isset($input['eventTime']) && !empty($input['eventTime']) ? $input['eventTime'] : null;
    $location = isset($input['location']) ? sanitizeInput($input['location']) : null;
    $primaryColor = isset($input['primaryColor']) ? $input['primaryColor'] : '#667eea';
    $secondaryColor = isset($input['secondaryColor']) ? $input['secondaryColor'] : '#764ba2';

    $stmt = $conn->prepare("INSERT INTO events (user_id, name, description, event_date, event_time, location, primary_color, secondary_color) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssss", $userId, $name, $description, $eventDate, $eventTime, $location, $primaryColor, $secondaryColor);

    if ($stmt->execute()) {
        $eventId = $conn->insert_id;
        $stmt->close();

        // Set as current event
        setCurrentEventId($eventId);

        // Fetch created event
        $result = $conn->query("SELECT * FROM events WHERE id = $eventId");
        $event = formatEvent($result->fetch_assoc());

        $conn->close();

        jsonResponse([
            'success' => true,
            'message' => 'Event berhasil dibuat',
            'data' => $event
        ], 201);
    } else {
        $stmt->close();
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Gagal membuat event'], 500);
    }
}

/**
 * Update event
 */
function updateEvent()
{
    requireAuth();
    $userId = getCurrentUserId();

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id'])) {
        jsonResponse(['success' => false, 'message' => 'ID event tidak valid'], 400);
    }

    $eventId = (int) $input['id'];

    // Validate input
    $errors = validateEvent($input);
    if (!empty($errors)) {
        jsonResponse(['success' => false, 'errors' => $errors], 400);
    }

    $conn = getConnection();

    // Verify ownership
    $stmt = $conn->prepare("SELECT id FROM events WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $eventId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $stmt->close();
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Event tidak ditemukan'], 404);
    }
    $stmt->close();

    $name = sanitizeInput($input['name']);
    $description = isset($input['description']) ? sanitizeInput($input['description']) : null;
    $eventDate = isset($input['eventDate']) && !empty($input['eventDate']) ? $input['eventDate'] : null;
    $eventTime = isset($input['eventTime']) && !empty($input['eventTime']) ? $input['eventTime'] : null;
    $location = isset($input['location']) ? sanitizeInput($input['location']) : null;
    $primaryColor = isset($input['primaryColor']) ? $input['primaryColor'] : '#667eea';
    $secondaryColor = isset($input['secondaryColor']) ? $input['secondaryColor'] : '#764ba2';
    $isArchived = isset($input['isArchived']) ? (bool) $input['isArchived'] : false;

    $stmt = $conn->prepare("UPDATE events SET name = ?, description = ?, event_date = ?, event_time = ?, location = ?, primary_color = ?, secondary_color = ?, is_archived = ? WHERE id = ?");
    $archived = $isArchived ? 1 : 0;
    $stmt->bind_param("sssssssii", $name, $description, $eventDate, $eventTime, $location, $primaryColor, $secondaryColor, $archived, $eventId);

    if ($stmt->execute()) {
        $stmt->close();

        // Fetch updated event
        $result = $conn->query("SELECT e.*, 
            (SELECT COUNT(*) FROM guests WHERE event_id = e.id) as guest_count,
            (SELECT COUNT(*) FROM guests WHERE event_id = e.id AND status = 'checked_in') as checked_in_count,
            (SELECT COUNT(*) FROM guests WHERE event_id = e.id AND status = 'pending') as pending_count
            FROM events e WHERE e.id = $eventId");
        $event = formatEvent($result->fetch_assoc());

        $conn->close();

        jsonResponse([
            'success' => true,
            'message' => 'Event berhasil diperbarui',
            'data' => $event
        ]);
    } else {
        $stmt->close();
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Gagal memperbarui event'], 500);
    }
}

/**
 * Delete event
 */
function deleteEvent()
{
    requireAuth();
    $userId = getCurrentUserId();

    $eventId = isset($_GET['id']) ? (int) $_GET['id'] : null;

    if (!$eventId) {
        $input = json_decode(file_get_contents('php://input'), true);
        $eventId = isset($input['id']) ? (int) $input['id'] : null;
    }

    if (!$eventId) {
        jsonResponse(['success' => false, 'message' => 'ID event tidak valid'], 400);
    }

    $conn = getConnection();

    // Verify ownership
    $stmt = $conn->prepare("SELECT id FROM events WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $eventId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $stmt->close();
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Event tidak ditemukan'], 404);
    }
    $stmt->close();

    // Delete event (guests will be cascade deleted)
    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    $stmt->bind_param("i", $eventId);

    if ($stmt->execute()) {
        $stmt->close();

        // Clear current event if it was deleted
        if (getCurrentEventId() === $eventId) {
            setCurrentEventId(null);
        }

        $conn->close();

        jsonResponse([
            'success' => true,
            'message' => 'Event berhasil dihapus'
        ]);
    } else {
        $stmt->close();
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Gagal menghapus event'], 500);
    }
}

/**
 * Switch current event
 */
function switchEvent()
{
    requireAuth();
    $userId = getCurrentUserId();

    $input = json_decode(file_get_contents('php://input'), true);
    $eventId = isset($input['eventId']) ? (int) $input['eventId'] : null;

    if (!$eventId) {
        jsonResponse(['success' => false, 'message' => 'ID event tidak valid'], 400);
    }

    $conn = getConnection();

    // Verify ownership
    $stmt = $conn->prepare("SELECT id, name FROM events WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $eventId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        jsonResponse(['success' => false, 'message' => 'Event tidak ditemukan'], 404);
    }

    $event = $result->fetch_assoc();
    $stmt->close();
    $conn->close();

    // Set as current event
    setCurrentEventId($eventId);

    jsonResponse([
        'success' => true,
        'message' => 'Event aktif berhasil diubah',
        'data' => [
            'id' => (int) $event['id'],
            'name' => $event['name']
        ]
    ]);
}

/**
 * Format event data
 */
function formatEvent($row)
{
    return [
        'id' => (int) $row['id'],
        'userId' => (int) $row['user_id'],
        'name' => $row['name'],
        'description' => $row['description'],
        'eventDate' => $row['event_date'],
        'eventTime' => $row['event_time'],
        'location' => $row['location'],
        'logoPath' => $row['logo_path'],
        'primaryColor' => $row['primary_color'],
        'secondaryColor' => $row['secondary_color'],
        'isArchived' => (bool) $row['is_archived'],
        'guestCount' => isset($row['guest_count']) ? (int) $row['guest_count'] : 0,
        'checkedInCount' => isset($row['checked_in_count']) ? (int) $row['checked_in_count'] : 0,
        'pendingCount' => isset($row['pending_count']) ? (int) $row['pending_count'] : 0,
        'createdAt' => $row['created_at']
    ];
}

/**
 * Validate event input
 */
function validateEvent($input)
{
    $errors = [];

    if (empty($input['name'])) {
        $errors['name'] = 'Nama event harus diisi';
    } elseif (strlen($input['name']) < 3) {
        $errors['name'] = 'Nama event minimal 3 karakter';
    } elseif (strlen($input['name']) > 200) {
        $errors['name'] = 'Nama event maksimal 200 karakter';
    }

    if (!empty($input['eventDate']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['eventDate'])) {
        $errors['eventDate'] = 'Format tanggal tidak valid (YYYY-MM-DD)';
    }

    if (!empty($input['eventTime']) && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $input['eventTime'])) {
        $errors['eventTime'] = 'Format waktu tidak valid (HH:MM)';
    }

    return $errors;
}
?>