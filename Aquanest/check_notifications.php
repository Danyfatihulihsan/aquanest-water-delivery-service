<?php
// FILE: check_notifications.php
// API untuk memeriksa notifikasi baru via AJAX

session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/notification_functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$response = [
    'has_updates' => false,
    'message' => '',
    'type' => 'info',
    'notification_count' => 0,
    'data' => null
];

try {
    // Check for customer notifications
    if (isset($_GET['order_id']) && !empty($_GET['order_id'])) {
        $orderId = sanitize($_GET['order_id']);
        
        // Get latest notifications for this order
        $notifications = getCustomerNotifications($conn, $orderId);
        
        // Check if there are unread notifications
        $unreadNotifications = array_filter($notifications, function($n) {
            return $n['status'] === 'active';
        });
        
        if (!empty($unreadNotifications)) {
            $latestNotification = $unreadNotifications[0];
            
            // Check if notification is newer than 1 minute (to avoid spam)
            $notificationTime = strtotime($latestNotification['created_at']);
            $oneMinuteAgo = time() - 60;
            
            if ($notificationTime > $oneMinuteAgo) {
                $response['has_updates'] = true;
                $response['message'] = $latestNotification['message'];
                $response['data'] = $latestNotification;
                
                if ($latestNotification['type'] === 'payment_approved') {
                    $response['type'] = 'success';
                } elseif ($latestNotification['type'] === 'payment_rejected') {
                    $response['type'] = 'error';
                } else {
                    $response['type'] = 'info';
                }
                
                // Mark as read
                markNotificationAsRead($conn, $latestNotification['id']);
            }
        }
    }
    
    // Check for admin notifications (new payment uploads)
    if (isset($_GET['admin']) && $_GET['admin'] === '1') {
        // Verify admin session
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            $response['error'] = 'Unauthorized';
            echo json_encode($response);
            exit;
        }
        
        // Get pending notifications count
        $pendingNotifications = getPaymentNotifications($conn, 'pending');
        $response['notification_count'] = count($pendingNotifications);
        
        // Check for new notifications in last 2 minutes
        $recentNotifications = array_filter($pendingNotifications, function($n) {
            $notificationTime = strtotime($n['created_at']);
            return $notificationTime > (time() - 120); // 2 minutes
        });
        
        if (!empty($recentNotifications)) {
            $response['has_updates'] = true;
            $response['message'] = count($recentNotifications) . ' pembayaran baru menunggu verifikasi';
            $response['type'] = 'warning';
            $response['data'] = $recentNotifications;
        }
    }
    
    // Get general statistics
    if (isset($_GET['stats']) && $_GET['stats'] === '1') {
        $stats = getAdminStatistics($conn);
        $response['stats'] = $stats;
    }
    
} catch (Exception $e) {
    error_log("Error in check_notifications.php: " . $e->getMessage());
    $response['error'] = 'Internal server error';
}

echo json_encode($response);
exit;
?>