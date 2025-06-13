<?php
// Start session
session_start();

// Include database connection and functions
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if admin is logged in
// requireAdminLogin();

// Process notification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_notification'])) {
        $order_id = $_POST['order_id'];
        $status = $_POST['status'];
        $phone = $_POST['phone'];
        
        // Get order details
        $order = getOrderById($conn, $order_id);
        if (!$order) {
            setFlashMessage('danger', 'Pesanan tidak ditemukan.');
            redirect('manage_orders.php');
            exit;
        }
        
        // Format phone number (ensure it starts with country code)
        $phone = formatPhoneNumber($phone);
        
        // Prepare message based on status
        $message = '';
        $order_number = str_pad($order_id, 6, '0', STR_PAD_LEFT);
        
        switch ($status) {
            case 'confirmed':
                $message = "Halo {$order['customer_name']}, pesanan Anda #$order_number di Aquanest telah dikonfirmasi dan sedang diproses. Terima kasih.";
                break;
            case 'processing':
                $message = "Halo {$order['customer_name']}, pesanan Anda #$order_number di Aquanest sedang dalam proses pengiriman. Mohon menunggu, kurir kami akan segera tiba.";
                break;
            case 'delivered':
                $message = "Halo {$order['customer_name']}, pesanan Anda #$order_number di Aquanest telah dikirim dan telah sampai! Kami harap Anda puas dengan produk kami. Terima kasih.";
                break;
            case 'payment_confirmed':
                $message = "Halo {$order['customer_name']}, pembayaran untuk pesanan #$order_number di Aquanest telah kami terima. Pesanan Anda sedang diproses. Terima kasih.";
                break;
            default:
                $message = "Halo {$order['customer_name']}, ada pembaruan untuk pesanan #$order_number di Aquanest. Silakan cek status pesanan Anda melalui website kami atau hubungi customer service.";
        }
        
        // Send WhatsApp notification (using provided API)
        $result = sendWhatsAppNotification($phone, $message);
        
        if ($result['success']) {
            // Update notification status in database
            try {
                $stmt = $conn->prepare("UPDATE orders SET notification_sent = 1 WHERE order_id = :id");
                $stmt->bindParam(':id', $order_id);
                $stmt->execute();
                
                setFlashMessage('success', 'Notifikasi WhatsApp berhasil dikirim.');
            } catch (PDOException $e) {
                setFlashMessage('warning', 'Notifikasi terkirim, tetapi gagal memperbarui status di database: ' . $e->getMessage());
            }
        } else {
            setFlashMessage('danger', 'Gagal mengirim notifikasi WhatsApp: ' . $result['message']);
        }
        
        // Redirect back to order
        redirect('view_order.php?id=' . $order_id);
    }
} else {
    // If accessed directly without POST, redirect to dashboard
    redirect('dashboard.php');
}

/**
 * Format phone number to ensure it has country code
 */
function formatPhoneNumber($phone) {
    // Remove any non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if it already has country code
    if (substr($phone, 0, 2) == '62') {
        return $phone;
    }
    
    // If it starts with 0, replace with 62
    if (substr($phone, 0, 1) == '0') {
        return '62' . substr($phone, 1);
    }
    
    // Otherwise, add 62 prefix
    return '62' . $phone;
}

/**
 * Send WhatsApp notification using a third-party API
 * Note: You will need to replace this with actual API integration
 */
function sendWhatsAppNotification($phone, $message) {
    // This is a placeholder function
    // In a real implementation, you would integrate with a WhatsApp API service
    // such as Twilio, MessageBird, WhatsMate, etc.
    
    // Example with cURL for a hypothetical API:
    $apiKey = 'YOUR_API_KEY'; // Replace with your actual API key
    $apiUrl = 'https://api.whatsapp-service.com/send'; // Replace with the actual API URL
    
    $postData = [
        'phone' => $phone,
        'message' => $message,
        'api_key' => $apiKey
    ];
    
    /*
    // Uncomment and modify this section when you have actual API integration
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => $error];
    }
    
    $result = json_decode($response, true);
    return $result;
    */
    
    // For now, return a mock success response
    return ['success' => true, 'message' => 'Notification sent successfully'];
}
?>