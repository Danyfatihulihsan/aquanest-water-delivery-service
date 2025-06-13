<?php
// Start session
session_start();

// Include database connection and functions
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if admin is logged in
// requireAdminLogin();

// Get export format
$format = isset($_GET['format']) ? sanitize($_GET['format']) : 'csv';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$exportCurrent = isset($_GET['export_current']) ? true : false;

// Build query
$query = "SELECT customer_id, name, email, phone, address, created_at FROM customers";
$params = [];

// Apply search filter if provided
if ($exportCurrent && !empty($search)) {
    $query .= " WHERE name LIKE :search OR email LIKE :search OR phone LIKE :search OR address LIKE :search";
    $params[':search'] = "%$search%";
}

// Order by latest first
$query .= " ORDER BY created_at DESC";

// Get customers
try {
    $stmt = $conn->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Set filename with date
$filename = 'aquanest_customers_' . date('Y-m-d') . '.' . $format;

// CSV Export
if ($format === 'csv') {
    // Set headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add header row
    fputcsv($output, ['ID', 'Nama', 'Email', 'Telepon', 'Alamat', 'Tanggal Bergabung']);
    
    // Add data rows
    foreach ($customers as $customer) {
        fputcsv($output, [
            $customer['customer_id'],
            $customer['name'],
            $customer['email'],
            $customer['phone'],
            $customer['address'],
            date('d-m-Y H:i', strtotime($customer['created_at']))
        ]);
    }
    
    // Close output stream
    fclose($output);
    exit;
}
// Excel Export
else if ($format === 'excel') {
    // For Excel, you would need a library like PhpSpreadsheet
    // This is a simplified version
    
    // Set headers
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
    echo '<table border="1">';
    echo '<tr><th>ID</th><th>Nama</th><th>Email</th><th>Telepon</th><th>Alamat</th><th>Tanggal Bergabung</th></tr>';
    
    foreach ($customers as $customer) {
        echo '<tr>';
        echo '<td>' . $customer['customer_id'] . '</td>';
        echo '<td>' . $customer['name'] . '</td>';
        echo '<td>' . $customer['email'] . '</td>';
        echo '<td>' . $customer['phone'] . '</td>';
        echo '<td>' . $customer['address'] . '</td>';
        echo '<td>' . date('d-m-Y H:i', strtotime($customer['created_at'])) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    exit;
}
// PDF Export (basic version)
else if ($format === 'pdf') {
    // For PDF, you would need a library like FPDF or TCPDF
    // This is just a placeholder - in a real implementation, you'd use a PDF library
    
    // Redirect back with a message
    setFlashMessage('info', 'PDF export memerlukan library tambahan. Gunakan format CSV atau Excel untuk saat ini.');
    redirect('manage_customers.php');
}
else {
    // Invalid format
    setFlashMessage('danger', 'Format ekspor tidak valid.');
    redirect('manage_customers.php');
}
?>