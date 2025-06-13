<?php
// Start session
session_start();

// Include database connection
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if order_id is provided
    if (!isset($_POST['order_id']) || empty($_POST['order_id'])) {
        setFlashMessage('danger', 'ID Pesanan tidak valid.');
        redirect('index.php');
    }
    
    // Get order ID
    $orderId = $_POST['order_id'];
    
    // Check if file is uploaded
    if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] != 0) {
        setFlashMessage('danger', 'Gagal mengupload file. Silakan coba lagi.');
        redirect('order-success.php?id=' . $orderId);
    }
    
    // Upload file
    $targetDir = "uploads/bukti_pembayaran/";
    $uploadResult = uploadFile($_FILES['payment_proof'], $targetDir);
    
    if ($uploadResult['success']) {
        // Update order payment status
        try {
            $stmt = $conn->prepare("UPDATE orders SET payment_status = 'pending', payment_proof = :proof WHERE order_id = :id");
            $stmt->bindParam(':proof', $uploadResult['file_name']);
            $stmt->bindParam(':id', $orderId);
            $stmt->execute();
            
            setFlashMessage('success', 'Bukti pembayaran berhasil diupload. Tim kami akan memverifikasi pembayaran Anda.');
        } catch (PDOException $e) {
            setFlashMessage('danger', 'Terjadi kesalahan saat memperbarui status pembayaran. Silakan coba lagi.');
        }
    } else {
        setFlashMessage('danger', $uploadResult['message']);
    }
    
    // Redirect back to order success page
    redirect('order-success.php?id=' . $orderId);
} else {
    // If accessed directly without POST, redirect to homepage
    redirect('index.php');
}
?>