<?php
// ajax_tracking.php
session_start();

require_once 'includes/db.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit();
}

$orderId = (int)$_GET['order_id'];

try {
    // Get current delivery status
    $sql = "SELECT cd.*, c.name as courier_name, c.phone as courier_phone,
            o.address, o.estimated_delivery,
            TIMESTAMPDIFF(MINUTE, cd.started_at, NOW()) as minutes_elapsed
            FROM courier_deliveries cd
            JOIN couriers c ON cd.courier_id = c.courier_id
            JOIN orders o ON cd.order_id = o.order_id
            JOIN customers cust ON o.customer_id = cust.customer_id
            WHERE cd.order_id = :order_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$delivery) {
        echo json_encode(['success' => false, 'message' => 'Delivery not found']);
        exit();
    }
    
    // Simulate courier location based on time elapsed
    $locations = [
        ['time' => 0, 'location' => 'Kurir sedang bersiap di depot', 'progress' => 0],
        ['time' => 5, 'location' => 'Kurir telah berangkat dari depot', 'progress' => 10],
        ['time' => 10, 'location' => 'Kurir di Jalan Raya Bekasi Timur', 'progress' => 25],
        ['time' => 15, 'location' => 'Kurir melewati Perempatan Harapan Indah', 'progress' => 40],
        ['time' => 20, 'location' => 'Kurir mendekati area perumahan Anda', 'progress' => 60],
        ['time' => 25, 'location' => 'Kurir sudah di area perumahan', 'progress' => 80],
        ['time' => 30, 'location' => 'Kurir sedang mencari alamat Anda', 'progress' => 90],
        ['time' => 35, 'location' => 'Kurir tiba di lokasi Anda', 'progress' => 100]
    ];
    
    $currentLocation = 'Menunggu update lokasi';
    $progress = 0;
    $estimatedTime = '';
    
    if ($delivery['status'] == 'on_the_way' && $delivery['minutes_elapsed'] !== null) {
        $elapsed = (int)$delivery['minutes_elapsed'];
        
        // Find appropriate location based on elapsed time
        foreach ($locations as $loc) {
            if ($elapsed >= $loc['time']) {
                $currentLocation = $loc['location'];
                $progress = $loc['progress'];
            }
        }
        
        // Calculate estimated time
        $remainingMinutes = max(0, 35 - $elapsed);
        if ($remainingMinutes > 0) {
            $estimatedTime = $remainingMinutes . ' menit lagi';
        } else {
            $currentLocation = 'Kurir sudah tiba di lokasi Anda';
            $progress = 100;
        }
        
        // Auto-update to delivered if time exceeded
        if ($elapsed >= 35 && $delivery['status'] !== 'delivered') {
            $sql = "UPDATE courier_deliveries SET status = 'delivered', completed_at = NOW() 
                    WHERE order_id = :order_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':order_id', $orderId);
            $stmt->execute();
            
            // Update courier status to available
            $sql = "UPDATE couriers SET status = 'available' WHERE courier_id = :courier_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':courier_id', $delivery['courier_id']);
            $stmt->execute();
            
            // Update order status
            $sql = "UPDATE orders SET order_status = 'completed' WHERE order_id = :order_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':order_id', $orderId);
            $stmt->execute();
            
            // Add tracking history
            addTrackingHistory($conn, $orderId, 'Pesanan Selesai', 'Pesanan telah diterima pelanggan');
            
            $delivery['status'] = 'delivered';
        }
    } else if ($delivery['status'] == 'delivered') {
        $currentLocation = 'Pesanan telah diterima';
        $progress = 100;
    } else if ($delivery['status'] == 'assigned') {
        $currentLocation = 'Kurir sedang bersiap untuk pengiriman';
        $progress = 0;
        
        // Simulate auto-start delivery after 5 minutes
        $assignedMinutes = time() - strtotime($delivery['assigned_at']);
        if ($assignedMinutes >= 300) { // 5 minutes
            $sql = "UPDATE courier_deliveries SET status = 'on_the_way', started_at = NOW() 
                    WHERE order_id = :order_id AND status = 'assigned'";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':order_id', $orderId);
            $stmt->execute();
            
            addTrackingHistory($conn, $orderId, 'Kurir Berangkat', 'Kurir telah berangkat menuju lokasi Anda');
        }
    }
    
    // Calculate distance (simulated)
    $distance = $progress < 100 ? max(100, 5000 - ($progress * 50)) : 0; // in meters
    
    $response = [
        'success' => true,
        'courier_name' => $delivery['courier_name'],
        'courier_phone' => $delivery['courier_phone'],
        'courier_location' => $currentLocation,
        'delivery_status' => $delivery['status'],
        'progress' => $progress,
        'estimated_time' => $estimatedTime,
        'distance' => $distance,
        'minutes_elapsed' => $delivery['minutes_elapsed'] ?? 0
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Ajax tracking error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error retrieving tracking data']);
}
?>