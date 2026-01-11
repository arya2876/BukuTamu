<?php
/**
 * AW Digital Guestbook - Export API
 * Export data to CSV, Excel, and PDF
 */

require_once 'config.php';

// Set headers - not JSON for this endpoint
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session
startSecureSession();

// Check auth
if (!isAuthenticated()) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get format
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'csv';
$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : getCurrentEventId();

if (!$eventId) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Event tidak dipilih']);
    exit();
}

// Validate event ownership
$conn = getConnection();
$userId = getCurrentUserId();

$stmt = $conn->prepare("SELECT name FROM events WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $eventId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Event tidak ditemukan']);
    exit();
}

$event = $result->fetch_assoc();
$eventName = $event['name'];
$stmt->close();

// Get guests
$guests = [];
$guestsResult = $conn->query("SELECT * FROM guests WHERE event_id = $eventId ORDER BY created_at DESC");

while ($row = $guestsResult->fetch_assoc()) {
    $guests[] = $row;
}

$conn->close();

// Export based on format
switch ($format) {
    case 'csv':
        exportCSV($guests, $eventName);
        break;
    case 'xlsx':
    case 'excel':
        exportExcel($guests, $eventName);
        break;
    case 'pdf':
        exportPDF($guests, $eventName);
        break;
    default:
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Format tidak didukung']);
}

/**
 * Export to CSV
 */
function exportCSV($guests, $eventName)
{
    $filename = 'guests_' . sanitizeFilename($eventName) . '_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header row
    fputcsv($output, ['No', 'Nama', 'Email', 'Telepon', 'Meja', 'Status', 'Pesan', 'Check-in', 'Tanggal Daftar']);

    // Data rows
    $no = 1;
    foreach ($guests as $guest) {
        $status = $guest['status'] === 'checked_in' ? 'Hadir' : 'Belum Hadir';
        $checkinAt = $guest['checked_in_at'] ? date('d/m/Y H:i', strtotime($guest['checked_in_at'])) : '-';
        $tanggal = date('d/m/Y H:i', strtotime($guest['created_at']));

        fputcsv($output, [
            $no++,
            $guest['nama'],
            $guest['email'],
            $guest['telepon'],
            $guest['table_number'] ?? '-',
            $status,
            $guest['pesan'],
            $checkinAt,
            $tanggal
        ]);
    }

    fclose($output);
    exit();
}

/**
 * Export to Excel (using HTML table - no external library needed)
 */
function exportExcel($guests, $eventName)
{
    $filename = 'guests_' . sanitizeFilename($eventName) . '_' . date('Y-m-d') . '.xls';

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="utf-8"></head>';
    echo '<body>';
    echo '<h2>' . htmlspecialchars($eventName) . ' - Daftar Tamu</h2>';
    echo '<p>Tanggal Export: ' . date('d F Y H:i') . '</p>';
    echo '<table border="1" cellpadding="5">';
    echo '<tr style="background-color: #667eea; color: white; font-weight: bold;">';
    echo '<th>No</th><th>Nama</th><th>Email</th><th>Telepon</th><th>Meja</th><th>Status</th><th>Pesan</th><th>Check-in</th><th>Tanggal Daftar</th>';
    echo '</tr>';

    $no = 1;
    foreach ($guests as $guest) {
        $status = $guest['status'] === 'checked_in' ? 'Hadir' : 'Belum Hadir';
        $statusColor = $guest['status'] === 'checked_in' ? '#d4edda' : '#fff3cd';
        $checkinAt = $guest['checked_in_at'] ? date('d/m/Y H:i', strtotime($guest['checked_in_at'])) : '-';
        $tanggal = date('d/m/Y H:i', strtotime($guest['created_at']));

        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($guest['nama']) . '</td>';
        echo '<td>' . htmlspecialchars($guest['email']) . '</td>';
        echo '<td>' . htmlspecialchars($guest['telepon']) . '</td>';
        echo '<td>' . htmlspecialchars($guest['table_number'] ?? '-') . '</td>';
        echo '<td style="background-color: ' . $statusColor . ';">' . $status . '</td>';
        echo '<td>' . htmlspecialchars($guest['pesan']) . '</td>';
        echo '<td>' . $checkinAt . '</td>';
        echo '<td>' . $tanggal . '</td>';
        echo '</tr>';
    }

    echo '</table>';

    // Summary
    $total = count($guests);
    $checkedIn = count(array_filter($guests, fn($g) => $g['status'] === 'checked_in'));
    $pending = $total - $checkedIn;

    echo '<br><table border="1" cellpadding="5">';
    echo '<tr><th>Total Tamu</th><td>' . $total . '</td></tr>';
    echo '<tr><th>Sudah Hadir</th><td>' . $checkedIn . '</td></tr>';
    echo '<tr><th>Belum Hadir</th><td>' . $pending . '</td></tr>';
    echo '<tr><th>Persentase Kehadiran</th><td>' . ($total > 0 ? round(($checkedIn / $total) * 100, 1) : 0) . '%</td></tr>';
    echo '</table>';

    echo '</body></html>';
    exit();
}

/**
 * Export to PDF (using HTML)
 */
function exportPDF($guests, $eventName)
{
    $filename = 'guests_' . sanitizeFilename($eventName) . '_' . date('Y-m-d') . '.html';

    // For now, export as printable HTML
    // In production, you'd use a library like TCPDF or Dompdf
    header('Content-Type: text/html; charset=utf-8');

    $total = count($guests);
    $checkedIn = count(array_filter($guests, fn($g) => $g['status'] === 'checked_in'));
    $pending = $total - $checkedIn;

    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Tamu - ' . htmlspecialchars($eventName) . '</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; color: #333; }
        h1 { color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .info { margin-bottom: 20px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #667eea; color: white; padding: 10px; text-align: left; }
        td { padding: 8px; border: 1px solid #ddd; }
        tr:nth-child(even) { background: #f9f9f9; }
        .status-hadir { background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 4px; }
        .status-pending { background: #fff3cd; color: #856404; padding: 2px 8px; border-radius: 4px; }
        .summary { margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; }
        .summary h3 { margin-top: 0; color: #667eea; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .summary-item { text-align: center; }
        .summary-value { font-size: 2rem; font-weight: bold; color: #667eea; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer;">
            🖨️ Print / Save as PDF
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
            ✕ Tutup
        </button>
    </div>
    
    <h1>' . htmlspecialchars($eventName) . '</h1>
    <p class="info">Laporan Daftar Tamu | Tanggal: ' . date('d F Y H:i') . '</p>
    
    <div class="summary">
        <h3>Ringkasan</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-value">' . $total . '</div>
                <div>Total Tamu</div>
            </div>
            <div class="summary-item">
                <div class="summary-value" style="color: #28a745;">' . $checkedIn . '</div>
                <div>Sudah Hadir</div>
            </div>
            <div class="summary-item">
                <div class="summary-value" style="color: #ffc107;">' . $pending . '</div>
                <div>Belum Hadir</div>
            </div>
            <div class="summary-item">
                <div class="summary-value">' . ($total > 0 ? round(($checkedIn / $total) * 100, 1) : 0) . '%</div>
                <div>Kehadiran</div>
            </div>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama</th>
                <th>Email</th>
                <th>Telepon</th>
                <th>Meja</th>
                <th>Status</th>
                <th>Check-in</th>
            </tr>
        </thead>
        <tbody>';

    $no = 1;
    foreach ($guests as $guest) {
        $statusClass = $guest['status'] === 'checked_in' ? 'status-hadir' : 'status-pending';
        $statusText = $guest['status'] === 'checked_in' ? 'Hadir' : 'Belum Hadir';
        $checkinAt = $guest['checked_in_at'] ? date('d/m/Y H:i', strtotime($guest['checked_in_at'])) : '-';

        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($guest['nama']) . '</td>';
        echo '<td>' . htmlspecialchars($guest['email']) . '</td>';
        echo '<td>' . htmlspecialchars($guest['telepon']) . '</td>';
        echo '<td>' . htmlspecialchars($guest['table_number'] ?? '-') . '</td>';
        echo '<td><span class="' . $statusClass . '">' . $statusText . '</span></td>';
        echo '<td>' . $checkinAt . '</td>';
        echo '</tr>';
    }

    echo '</tbody>
    </table>
    
    <p style="margin-top: 30px; color: #666; font-size: 0.9rem; text-align: center;">
        Generated by AW Digital Guestbook
    </p>
</body>
</html>';
    exit();
}

/**
 * Sanitize filename
 */
function sanitizeFilename($name)
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
}
?>