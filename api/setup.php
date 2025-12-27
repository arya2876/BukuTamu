<?php
/**
 * Database Setup Script
 * Handles both fresh install and migration from old schema
 */

require_once 'config.php';

echo "<h2>Buku Tamu - Database Setup</h2>";

// Connect to MySQL server (without selecting database)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

if ($conn->connect_error) {
    die("<p style='color: red;'>❌ Connection failed: " . $conn->connect_error . "</p>");
}

echo "<p style='color: green;'>✅ Connected to MySQL server</p>";

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql)) {
    echo "<p style='color: green;'>✅ Database '" . DB_NAME . "' ready</p>";
} else {
    die("<p style='color: red;'>❌ Error creating database: " . $conn->error . "</p>");
}

// Select the database
$conn->select_db(DB_NAME);

// Check if table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'guests'")->num_rows > 0;

if ($tableExists) {
    echo "<p style='color: blue;'>ℹ️ Table 'guests' exists, checking for updates...</p>";

    // Check if qr_token column exists
    $qrTokenExists = $conn->query("SHOW COLUMNS FROM guests LIKE 'qr_token'")->num_rows > 0;

    if (!$qrTokenExists) {
        echo "<p style='color: orange;'>⚡ Adding QR token columns...</p>";

        // Add qr_token column (nullable first to avoid issues)
        $conn->query("ALTER TABLE guests ADD COLUMN qr_token VARCHAR(32) NULL AFTER pesan");
        $conn->query("ALTER TABLE guests ADD COLUMN status ENUM('pending', 'checked_in') DEFAULT 'pending' AFTER qr_token");
        $conn->query("ALTER TABLE guests ADD COLUMN checked_in_at DATETIME NULL AFTER status");

        // Generate unique tokens for existing guests
        $existingGuests = $conn->query("SELECT id FROM guests");
        while ($row = $existingGuests->fetch_assoc()) {
            $token = bin2hex(random_bytes(8));
            $stmt = $conn->prepare("UPDATE guests SET qr_token = ? WHERE id = ?");
            $stmt->bind_param("si", $token, $row['id']);
            $stmt->execute();
            $stmt->close();
        }

        // Now make qr_token NOT NULL and add unique index
        $conn->query("ALTER TABLE guests MODIFY qr_token VARCHAR(32) NOT NULL");
        $conn->query("ALTER TABLE guests ADD UNIQUE INDEX idx_qr_token (qr_token)");

        echo "<p style='color: green;'>✅ Migration complete - QR tokens added to existing guests</p>";
    } else {
        // Check for guests with empty qr_token and fix them
        $emptyTokens = $conn->query("SELECT id FROM guests WHERE qr_token = '' OR qr_token IS NULL");
        if ($emptyTokens && $emptyTokens->num_rows > 0) {
            echo "<p style='color: orange;'>⚡ Fixing guests with missing QR tokens...</p>";
            while ($row = $emptyTokens->fetch_assoc()) {
                $token = bin2hex(random_bytes(8));
                $stmt = $conn->prepare("UPDATE guests SET qr_token = ? WHERE id = ?");
                $stmt->bind_param("si", $token, $row['id']);
                $stmt->execute();
                $stmt->close();
            }
            echo "<p style='color: green;'>✅ Fixed " . $emptyTokens->num_rows . " guests with missing tokens</p>";
        } else {
            echo "<p style='color: green;'>✅ All guests have valid QR tokens</p>";
        }
    }

    // Check if status column exists
    $statusExists = $conn->query("SHOW COLUMNS FROM guests LIKE 'status'")->num_rows > 0;
    if (!$statusExists) {
        $conn->query("ALTER TABLE guests ADD COLUMN status ENUM('pending', 'checked_in') DEFAULT 'pending' AFTER qr_token");
        $conn->query("ALTER TABLE guests ADD COLUMN checked_in_at DATETIME NULL AFTER status");
        echo "<p style='color: green;'>✅ Added status columns</p>";
    }

} else {
    // Create fresh table
    $sql = "CREATE TABLE guests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        telepon VARCHAR(15) NOT NULL,
        pesan TEXT NOT NULL,
        qr_token VARCHAR(32) NOT NULL,
        status ENUM('pending', 'checked_in') DEFAULT 'pending',
        checked_in_at DATETIME NULL,
        tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tanggal (tanggal),
        INDEX idx_email (email),
        UNIQUE INDEX idx_qr_token (qr_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        echo "<p style='color: green;'>✅ Table 'guests' created</p>";

        // Insert sample data
        $sampleData = [
            ['Ahmad Wijaya', 'ahmad@email.com', '081234567890', 'Terima kasih atas pelayanannya yang sangat baik!'],
            ['Siti Rahayu', 'siti@email.com', '082345678901', 'Website yang sangat informatif dan mudah digunakan.'],
            ['Budi Santoso', 'budi@email.com', '083456789012', 'Semoga sukses selalu dan terus berkembang.']
        ];

        foreach ($sampleData as $data) {
            $token = bin2hex(random_bytes(8));
            $stmt = $conn->prepare("INSERT INTO guests (nama, email, telepon, pesan, qr_token) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $data[0], $data[1], $data[2], $data[3], $token);
            $stmt->execute();
            $stmt->close();
        }

        echo "<p style='color: green;'>✅ Sample data inserted (3 guests)</p>";
    } else {
        die("<p style='color: red;'>❌ Error creating table: " . $conn->error . "</p>");
    }
}

$conn->close();

echo "<hr>";
echo "<h3>Setup Complete! 🎉</h3>";
echo "<p>You can now access the application:</p>";
echo "<ul>";
echo "<li><a href='../index.html'>🏠 Home - Landing Page</a></li>";
echo "<li><a href='../input.html'>➕ Input - Tambah Tamu</a></li>";
echo "<li><a href='../manage.html'>📋 Manage - Kelola Tamu</a></li>";
echo "<li><a href='../scan.html'>📷 Scan - Scan QR Code</a></li>";
echo "</ul>";
?>