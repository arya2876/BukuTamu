<?php
/**
 * AW Digital Guestbook - Database Setup Script
 * Handles fresh install and migrations
 */

require_once 'config.php';

echo "<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>AW Digital Guestbook - Setup</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap' rel='stylesheet'>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .setup-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h2 {
            color: #667eea;
            margin-bottom: 1.5rem;
        }
        .step {
            padding: 0.75rem 1rem;
            margin: 0.5rem 0;
            border-radius: 10px;
            display: flex;
            align-items: center;
        }
        .step-success { background: #d4edda; color: #155724; }
        .step-error { background: #f8d7da; color: #721c24; }
        .step-info { background: #d1ecf1; color: #0c5460; }
        .step-warning { background: #fff3cd; color: #856404; }
        .step-icon { margin-right: 0.75rem; font-size: 1.2rem; }
        .links { margin-top: 2rem; }
        .links a {
            display: block;
            padding: 0.75rem 1.5rem;
            margin: 0.5rem 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .links a:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
<div class='setup-card'>";

echo "<h2>🚀 AW Digital Guestbook Setup</h2>";

// Connect to MySQL server (without selecting database)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

if ($conn->connect_error) {
    echo "<div class='step step-error'><span class='step-icon'>❌</span>Connection failed: " . $conn->connect_error . "</div>";
    die("</div></body></html>");
}

echo "<div class='step step-success'><span class='step-icon'>✅</span>Connected to MySQL server</div>";

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql)) {
    echo "<div class='step step-success'><span class='step-icon'>✅</span>Database '" . DB_NAME . "' ready</div>";
} else {
    echo "<div class='step step-error'><span class='step-icon'>❌</span>Error creating database: " . $conn->error . "</div>";
    die("</div></body></html>");
}

// Select the database
$conn->select_db(DB_NAME);

// ==========================================
// USERS TABLE
// ==========================================
$usersExists = $conn->query("SHOW TABLES LIKE 'users'")->num_rows > 0;

if (!$usersExists) {
    $sql = "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'operator') DEFAULT 'admin',
        reset_token VARCHAR(64) NULL,
        reset_expires DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        echo "<div class='step step-success'><span class='step-icon'>✅</span>Table 'users' created</div>";
    } else {
        echo "<div class='step step-error'><span class='step-icon'>❌</span>Error creating users table: " . $conn->error . "</div>";
    }
} else {
    echo "<div class='step step-info'><span class='step-icon'>ℹ️</span>Table 'users' already exists</div>";
}

// ==========================================
// EVENTS TABLE
// ==========================================
$eventsExists = $conn->query("SHOW TABLES LIKE 'events'")->num_rows > 0;

if (!$eventsExists) {
    $sql = "CREATE TABLE events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(200) NOT NULL,
        description TEXT,
        event_date DATE NULL,
        event_time TIME NULL,
        location VARCHAR(255),
        logo_path VARCHAR(255),
        primary_color VARCHAR(7) DEFAULT '#667eea',
        secondary_color VARCHAR(7) DEFAULT '#764ba2',
        is_archived BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id),
        INDEX idx_date (event_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        echo "<div class='step step-success'><span class='step-icon'>✅</span>Table 'events' created</div>";
    } else {
        echo "<div class='step step-error'><span class='step-icon'>❌</span>Error creating events table: " . $conn->error . "</div>";
    }
} else {
    echo "<div class='step step-info'><span class='step-icon'>ℹ️</span>Table 'events' already exists</div>";
}

// ==========================================
// GUESTS TABLE
// ==========================================
$guestsExists = $conn->query("SHOW TABLES LIKE 'guests'")->num_rows > 0;

if (!$guestsExists) {
    $sql = "CREATE TABLE guests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        nama VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        telepon VARCHAR(15) NOT NULL,
        pesan TEXT NOT NULL,
        message TEXT NULL,
        table_number VARCHAR(20) NULL,
        qr_token VARCHAR(32) NOT NULL,
        status ENUM('pending', 'checked_in') DEFAULT 'pending',
        checked_in_at DATETIME NULL,
        tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
        INDEX idx_event (event_id),
        INDEX idx_tanggal (tanggal),
        INDEX idx_email (email),
        UNIQUE INDEX idx_qr_token (qr_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        echo "<div class='step step-success'><span class='step-icon'>✅</span>Table 'guests' created (new schema)</div>";
    } else {
        echo "<div class='step step-error'><span class='step-icon'>❌</span>Error creating guests table: " . $conn->error . "</div>";
    }
} else {
    // Check if event_id column exists (migration from old schema)
    $eventIdExists = $conn->query("SHOW COLUMNS FROM guests LIKE 'event_id'")->num_rows > 0;

    if (!$eventIdExists) {
        echo "<div class='step step-warning'><span class='step-icon'>⚡</span>Migrating guests table to new schema...</div>";

        // Add event_id column
        $conn->query("ALTER TABLE guests ADD COLUMN event_id INT NULL AFTER id");
        $conn->query("ALTER TABLE guests ADD COLUMN message TEXT NULL AFTER pesan");
        $conn->query("ALTER TABLE guests ADD COLUMN table_number VARCHAR(20) NULL AFTER message");

        echo "<div class='step step-success'><span class='step-icon'>✅</span>Added new columns to guests table</div>";
    } else {
        echo "<div class='step step-info'><span class='step-icon'>ℹ️</span>Table 'guests' already has new schema</div>";
    }

    // Check for qr_token column (from old migration)
    $qrTokenExists = $conn->query("SHOW COLUMNS FROM guests LIKE 'qr_token'")->num_rows > 0;
    if (!$qrTokenExists) {
        $conn->query("ALTER TABLE guests ADD COLUMN qr_token VARCHAR(32) NULL");
        $conn->query("ALTER TABLE guests ADD COLUMN status ENUM('pending', 'checked_in') DEFAULT 'pending'");
        $conn->query("ALTER TABLE guests ADD COLUMN checked_in_at DATETIME NULL");

        // Generate tokens for existing guests
        $existingGuests = $conn->query("SELECT id FROM guests WHERE qr_token IS NULL OR qr_token = ''");
        if ($existingGuests && $existingGuests->num_rows > 0) {
            while ($row = $existingGuests->fetch_assoc()) {
                $token = bin2hex(random_bytes(8));
                $stmt = $conn->prepare("UPDATE guests SET qr_token = ? WHERE id = ?");
                $stmt->bind_param("si", $token, $row['id']);
                $stmt->execute();
                $stmt->close();
            }
            echo "<div class='step step-success'><span class='step-icon'>✅</span>Generated QR tokens for existing guests</div>";
        }
    }
}

// ==========================================
// CREATE DEMO DATA
// ==========================================
$userCount = $conn->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'];

if ($userCount == 0) {
    echo "<div class='step step-info'><span class='step-icon'>📝</span>Creating demo account...</div>";

    // Create demo user
    $demoName = "Admin Demo";
    $demoEmail = "admin@awdigital.com";
    $demoPassword = password_hash("admin123", PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
    $stmt->bind_param("sss", $demoName, $demoEmail, $demoPassword);
    $stmt->execute();
    $userId = $conn->insert_id;
    $stmt->close();

    echo "<div class='step step-success'><span class='step-icon'>✅</span>Demo user created: admin@awdigital.com / admin123</div>";

    // Create demo event
    $eventName = "Pernikahan Ahmad & Siti";
    $eventDesc = "Acara pernikahan Ahmad dan Siti di Ballroom Hotel Grand";
    $eventDate = date('Y-m-d', strtotime('+7 days'));
    $eventTime = "10:00:00";
    $eventLocation = "Ballroom Hotel Grand, Jakarta";

    $stmt = $conn->prepare("INSERT INTO events (user_id, name, description, event_date, event_time, location) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $userId, $eventName, $eventDesc, $eventDate, $eventTime, $eventLocation);
    $stmt->execute();
    $eventId = $conn->insert_id;
    $stmt->close();

    echo "<div class='step step-success'><span class='step-icon'>✅</span>Demo event created: " . htmlspecialchars($eventName) . "</div>";

    // Create demo guests
    $demoGuests = [
        ['Budi Santoso', 'budi@email.com', '081234567890', 'Selamat menempuh hidup baru! Semoga bahagia selalu.', 'A1'],
        ['Dewi Lestari', 'dewi@email.com', '082345678901', 'Barakallah untuk pernikahan kalian. Semoga menjadi keluarga sakinah.', 'A2'],
        ['Eko Prasetyo', 'eko@email.com', '083456789012', 'Selamat ya! Semoga langgeng sampai kakek nenek.', 'B1']
    ];

    foreach ($demoGuests as $guest) {
        $token = bin2hex(random_bytes(8));
        $stmt = $conn->prepare("INSERT INTO guests (event_id, nama, email, telepon, pesan, table_number, qr_token) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $eventId, $guest[0], $guest[1], $guest[2], $guest[3], $guest[4], $token);
        $stmt->execute();
        $stmt->close();
    }

    echo "<div class='step step-success'><span class='step-icon'>✅</span>Created 3 demo guests</div>";
}

$conn->close();

echo "
<hr style='margin: 2rem 0; border-color: #eee;'>
<h3 style='color: #28a745;'>🎉 Setup Complete!</h3>
<p>AW Digital Guestbook siap digunakan!</p>

<div class='links'>
    <a href='login.html'>🔐 Login</a>
    <a href='register.html'>📝 Register Akun Baru</a>
</div>

<div style='margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 10px;'>
    <h5>Demo Account:</h5>
    <p style='margin: 0;'><strong>Email:</strong> admin@awdigital.com</p>
    <p style='margin: 0;'><strong>Password:</strong> admin123</p>
</div>

</div>
</body>
</html>";
?>