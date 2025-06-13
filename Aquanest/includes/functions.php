<?php
// General functions for the Aquanest website

/**
 * Sanitize input
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Display flash message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get flash message and clear it
 */
function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Display flash message as HTML
 */
function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        $type = $flash['type']; // success, warning, danger, info
        $message = $flash['message'];
        echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                {$message}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
    }
}

/**
 * Format price in Rupiah
 */
function formatRupiah($price) {
    return 'Rp ' . number_format($price, 0, ',', '.');
}

/**
 * Get all products
 */
function getAllProducts($conn) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE is_active = TRUE ORDER BY product_id DESC");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get product by ID
 */
function getProductById($conn, $productId) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = :id");
    $stmt->bindParam(':id', $productId);
    $stmt->execute();
    return $stmt->fetch();
}

/**
 * Get order by ID
 */
function getOrderById($conn, $orderId) {
    $stmt = $conn->prepare("SELECT o.*, c.name as customer_name, c.phone, c.address 
                          FROM orders o 
                          JOIN customers c ON o.customer_id = c.customer_id 
                          WHERE o.order_id = :id");
    $stmt->bindParam(':id', $orderId);
    $stmt->execute();
    return $stmt->fetch();
}

/**
 * Get order items by order ID
 */
function getOrderItems($conn, $orderId) {
    $stmt = $conn->prepare("SELECT oi.*, p.name as product_name 
                          FROM order_items oi
                          JOIN products p ON oi.product_id = p.product_id
                          WHERE oi.order_id = :id");
    $stmt->bindParam(':id', $orderId);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Create a customer
 */
/**
 * Create or get existing customer
 */
function createCustomer($conn, $name, $email, $phone, $address) {
    // Check if customer exists by phone number
    $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE phone = :phone");
    $stmt->bindParam(':phone', $phone);
    $stmt->execute();
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing customer data
        $stmt = $conn->prepare("UPDATE customers SET name = :name, email = :email, address = :address WHERE customer_id = :id");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':id', $existing['customer_id']);
        $stmt->execute();
        return $existing['customer_id'];
    } else {
        // Create new customer
        $stmt = $conn->prepare("INSERT INTO customers (name, email, phone, address) 
                              VALUES (:name, :email, :phone, :address)");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':address', $address);
        $stmt->execute();
        return $conn->lastInsertId();
    }
}

/**
 * Create an order
 */
function createOrder($conn, $customerId, $totalAmount, $paymentMethod, $notes, $estimatedDelivery = null, $paymentStatus = 'pending') {
    // Pastikan payment_method tidak null
    if (empty($paymentMethod)) {
        $paymentMethod = 'cod'; // default
    }
    
    $sql = "INSERT INTO orders (customer_id, total_amount, payment_method, payment_status, notes, estimated_delivery, created_at) 
            VALUES (:customer_id, :total_amount, :payment_method, :payment_status, :notes, :estimated_delivery, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':customer_id', $customerId);
    $stmt->bindParam(':total_amount', $totalAmount);
    $stmt->bindParam(':payment_method', $paymentMethod);
    $stmt->bindParam(':payment_status', $paymentStatus);
    $stmt->bindParam(':notes', $notes);
    $stmt->bindParam(':estimated_delivery', $estimatedDelivery);
    $stmt->execute();
    
    return $conn->lastInsertId();
}

/**
 * Add order item
 */
function addOrderItem($conn, $orderId, $productId, $quantity, $price, $subtotal) {
    $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) 
                          VALUES (:order_id, :product_id, :quantity, :price, :subtotal)");
    $stmt->bindParam(':order_id', $orderId);
    $stmt->bindParam(':product_id', $productId);
    $stmt->bindParam(':quantity', $quantity);
    $stmt->bindParam(':price', $price);
    $stmt->bindParam(':subtotal', $subtotal);
    $stmt->execute();
    
    // Update stock
    updateProductStock($conn, $productId, $quantity, 'decrease');
}

/**
 * Update product stock
 */
function updateProductStock($conn, $productId, $quantity, $action = 'decrease') {
    if ($action == 'decrease') {
        $stmt = $conn->prepare("UPDATE products SET stock = stock - :quantity WHERE product_id = :id");
    } else {
        $stmt = $conn->prepare("UPDATE products SET stock = stock + :quantity WHERE product_id = :id");
    }
    $stmt->bindParam(':quantity', $quantity);
    $stmt->bindParam(':id', $productId);
    $stmt->execute();
}

/**
 * Upload file
 */
function uploadFile($file, $targetDir) {
    // Check if directory exists
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = basename($file["name"]);
    $targetFile = $targetDir . time() . '_' . $fileName;
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    
    // Check if file already exists
    if (file_exists($targetFile)) {
        return ["success" => false, "message" => "File already exists."];
    }
    
    // Check file size (5MB max)
    if ($file["size"] > 5000000) {
        return ["success" => false, "message" => "File is too large. Max 5MB."];
    }
    
    // Allow certain file formats
    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
        return ["success" => false, "message" => "Only JPG, JPEG, PNG files are allowed."];
    }
    
    // Upload file
    if (move_uploaded_file($file["tmp_name"], $targetFile)) {
        return ["success" => true, "message" => "File uploaded successfully.", "file_name" => time() . '_' . $fileName];
    } else {
        return ["success" => false, "message" => "Error uploading file."];
    }
}

/**
 * Handle payment proof upload - FUNGSI YANG MENGATASI ERROR UTAMA
 * 
 * @param array $file $_FILES array for the uploaded file
 * @param int $orderId Order ID for naming the file
 * @return array Result array with success status and message
 */
function handlePaymentProofUpload($file, $orderId) {
    $result = [
        'success' => false,
        'message' => '',
        'filename' => ''
    ];
    
    // Check if upload directory exists
    $uploadDir = 'uploads/payment_proofs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['message'] = 'Terjadi kesalahan saat upload file.';
        return $result;
    }
    
    // Validate file size (max 2MB)
    $maxSize = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $maxSize) {
        $result['message'] = 'Ukuran file terlalu besar. Maksimal 2MB.';
        return $result;
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    $fileType = mime_content_type($file['tmp_name']);
    
    if (!in_array($fileType, $allowedTypes)) {
        $result['message'] = 'Tipe file tidak diizinkan. Hanya JPG, PNG, atau PDF.';
        return $result;
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'payment_' . $orderId . '_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $result['success'] = true;
        $result['message'] = 'File berhasil diupload.';
        $result['filename'] = $filename;
    } else {
        $result['message'] = 'Gagal menyimpan file.';
    }
    
    return $result;
}

/**
 * Update order payment status
 */
function updatePaymentStatus($conn, $orderId, $status, $paymentProof = null) {
    $sql = "UPDATE orders SET payment_status = :status";
    
    if ($paymentProof) {
        $sql .= ", payment_proof = :payment_proof";
    }
    
    $sql .= " WHERE order_id = :order_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':order_id', $orderId);
    
    if ($paymentProof) {
        $stmt->bindParam(':payment_proof', $paymentProof);
    }
    
    $stmt->execute();
}



/**
 * Add tracking history
 */
function addTrackingHistory($conn, $orderId, $title, $description) {
    // Set all previous steps as not current
    $sql = "UPDATE order_tracking_history SET is_current = 0 WHERE order_id = :order_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    
    // Add new tracking history
    $sql = "INSERT INTO order_tracking_history (order_id, title, description, is_completed, is_current) 
            VALUES (:order_id, :title, :description, 1, 1)";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':description', $description);
    $stmt->execute();
}

/**
 * Get order status text
 */
function getOrderStatusText($status) {
    $statusMap = [
        'pending' => 'Menunggu Konfirmasi',
        'processing' => 'Sedang Diproses',
        'shipping' => 'Dalam Pengiriman',
        'delivered' => 'Selesai',
        'cancelled' => 'Dibatalkan'
    ];
    
    return $statusMap[$status] ?? $status;
}

/**
 * Get payment status text
 */
function getPaymentStatusText($status) {
    $statusMap = [
        'pending' => 'Menunggu Pembayaran',
        'waiting' => 'Menunggu Konfirmasi',
        'paid' => 'Sudah Dibayar',
        'failed' => 'Pembayaran Gagal'
    ];
    
    return $statusMap[$status] ?? $status;
}

/**
 * Get order payment info
 */
/**
 * Get order payment info - FIXED VERSION
 */
function getOrderPaymentInfo($conn, $orderId) {
    $sql = "SELECT o.*, c.name as customer_name 
            FROM orders o
            JOIN customers c ON o.customer_id = c.customer_id
            WHERE o.order_id = :order_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        return null;
    }
    
    // Format order ID properly
    $formattedId = 'AQN' . date('ymd', strtotime($order['created_at'])) . str_pad($orderId, 6, '0', STR_PAD_LEFT);
    
    // Pastikan payment_method dan payment_status ada
    $paymentMethod = $order['payment_method'] ?? 'cod';
    $paymentStatus = $order['payment_status'] ?? 'pending';
    
    return [
        'id' => $formattedId,
        'customer' => $order['customer_name'],
        'created_at' => date('d/m/Y H:i', strtotime($order['created_at'])),
        'payment_method' => $paymentMethod,
        'payment_status' => getPaymentStatusText($paymentStatus),
        'expiry_time' => date('d/m/Y H:i', strtotime($order['created_at'] . ' +24 hours')),
        'total' => $order['total_amount']
    ];
}
/**
 * Get all subscription plans
 */
function getAllSubscriptionPlans($conn) {
    return [
        [
            'id' => 1,
            'name' => 'Paket Mingguan',
            'description' => 'Pengiriman setiap minggu',
            'price' => 150000,
            'duration' => 'Minggu',
            'delivery_count' => 4,
            'discount' => '10%',
            'popular' => false
        ],
        [
            'id' => 2,
            'name' => 'Paket Bulanan',
            'description' => 'Pengiriman 2x seminggu',
            'price' => 500000,
            'duration' => 'Bulan',
            'delivery_count' => 8,
            'discount' => '15%',
            'popular' => true
        ],
        [
            'id' => 3,
            'name' => 'Paket Premium',
            'description' => 'Pengiriman 3x seminggu',
            'price' => 900000,
            'duration' => 'Bulan',
            'delivery_count' => 12,
            'discount' => '20%',
            'popular' => false
        ]
    ];
}

/**
 * Get user subscriptions
 */
function getUserSubscriptions($conn, $userId) {
    // Dummy implementation - in real app would query database
    return [];
}

/**
 * Get user order history
 */
function getUserOrderHistory($conn, $userId) {
    // Get recent orders
    $sql = "SELECT o.*, c.name as customer_name 
            FROM orders o
            JOIN customers c ON o.customer_id = c.customer_id
            ORDER BY o.created_at DESC
            LIMIT 10";
    
    $stmt = $conn->query($sql);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $orderHistory = [];
    foreach ($orders as $order) {
        // Get order items
        $sql = "SELECT oi.*, p.name 
                FROM order_items oi
                JOIN products p ON oi.product_id = p.product_id
                WHERE oi.order_id = :order_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':order_id', $order['order_id']);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $orderHistory[] = [
            'id' => 'AQN' . date('ymd', strtotime($order['created_at'])) . str_pad($order['order_id'], 6, '0', STR_PAD_LEFT),
            'date' => date('d/m/Y', strtotime($order['created_at'])),
            'items' => $items,
            'total' => $order['total_amount'],
            'status' => getOrderStatusText($order['status'] ?? 'pending')
        ];
    }
    
    return $orderHistory;
}


/**
 * Get subscription plan by ID
 */
function getSubscriptionPlan($conn, $planId) {
    $plans = getAllSubscriptionPlans($conn);
    foreach ($plans as $plan) {
        if ($plan['id'] == $planId) {
            return $plan;
        }
    }
    return null;
}

/**
 * Get or create customer
 */
function getOrCreateCustomer($conn, $name, $email, $phone, $address) {
    // Check if customer exists by phone
    $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE phone = :phone");
    $stmt->bindParam(':phone', $phone);
    $stmt->execute();
    $customer = $stmt->fetch();
    
    if ($customer) {
        // Update existing customer
        $stmt = $conn->prepare("UPDATE customers SET name = :name, email = :email, address = :address WHERE customer_id = :id");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':id', $customer['customer_id']);
        $stmt->execute();
        return $customer['customer_id'];
    } else {
        // Create new customer
        return createCustomer($conn, $name, $email, $phone, $address);
    }
}

/**
 * Create subscription
 */
function createSubscription($conn, $customerId, $planId, $deliveryDay) {
    // Temporary implementation - should be replaced with actual database insertion
    return rand(10000, 99999);
}

// TAMBAHAN FUNGSI HELPER YANG MUNGKIN DIPERLUKAN

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get logged in user ID
 * 
 * @return int|null User ID or null
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Validate email
 * 
 * @param string $email Email to validate
 * @return bool True if valid
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Indonesian format)
 * 
 * @param string $phone Phone number to validate
 * @return bool True if valid
 */
function isValidPhone($phone) {
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if it's a valid Indonesian phone number
    // Should start with 08 or 628 and be 10-13 digits long
    return preg_match('/^(08|628)[0-9]{8,11}$/', $phone);
}

/**
 * Generate order number
 * 
 * @param int $orderId Order ID
 * @return string Formatted order number
 */
function generateOrderNumber($orderId) {
    return 'AQN' . date('Ymd') . str_pad($orderId, 6, '0', STR_PAD_LEFT);
}

/**
 * Calculate delivery fee based on distance
 * 
 * @param string $address Delivery address
 * @return float Delivery fee
 */
function calculateDeliveryFee($address) {
    // Simple flat rate for now
    // You can implement more complex logic based on actual distance
    return 5000; // Rp 5,000 flat rate
}

/**
 * Check product availability
 * 
 * @param PDO $conn Database connection
 * @param int $productId Product ID
 * @param int $quantity Requested quantity
 * @return bool True if available
 */
function checkProductAvailability($conn, $productId, $quantity) {
    try {
        $sql = "SELECT stock FROM products WHERE product_id = :product_id AND is_active = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':product_id', $productId);
        $stmt->execute();
        
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return false;
        }
        
        // If stock is null, assume unlimited
        if ($product['stock'] === null) {
            return true;
        }
        
        return $product['stock'] >= $quantity;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Send order notification email
 * 
 * @param string $email Customer email
 * @param array $orderData Order data
 * @return bool Success status
 */
function sendOrderNotification($email, $orderData) {
    // Implement email sending logic here
    // For now, just return true
    return true;
}

/**
 * Log order activity
 * 
 * @param PDO $conn Database connection
 * @param int $orderId Order ID
 * @param string $activity Activity description
 * @param int $userId User ID (optional)
 * @return bool Success status
 */
function logOrderActivity($conn, $orderId, $activity, $userId = null) {
    try {
        $sql = "INSERT INTO order_logs (order_id, activity, user_id, created_at) 
                VALUES (:order_id, :activity, :user_id, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->bindParam(':activity', $activity);
        $stmt->bindParam(':user_id', $userId);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        // If table doesn't exist, just return true
        return true;
    }
}
function getAvailableCouriers($conn) {
    try {
        $sql = "SELECT * FROM couriers 
                WHERE status = 'available' 
                AND is_active = TRUE 
                ORDER BY courier_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error getting available couriers: " . $e->getMessage());
        return [];
    }
}

// Fungsi untuk auto assign kurir berdasarkan availability
function autoAssignCourier($conn, $orderId, $address) {
    try {
        // Get available couriers
        $sql = "SELECT c.* FROM couriers c
                WHERE c.status = 'available' 
                AND c.is_active = TRUE
                AND c.courier_id NOT IN (
                    SELECT cd.courier_id 
                    FROM courier_deliveries cd 
                    WHERE cd.status IN ('assigned', 'on_the_way')
                )
                ORDER BY RAND()
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $courier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$courier) {
            // Jika tidak ada kurir available, ambil yang paling sedikit ordernya hari ini
            $sql = "SELECT c.*, COUNT(cd.delivery_id) as delivery_count
                    FROM couriers c
                    LEFT JOIN courier_deliveries cd ON c.courier_id = cd.courier_id 
                        AND DATE(cd.assigned_at) = CURDATE()
                    WHERE c.is_active = TRUE
                    GROUP BY c.courier_id
                    ORDER BY delivery_count ASC, RAND()
                    LIMIT 1";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $courier = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($courier) {
            // Update order dengan courier_id
            $sql = "UPDATE orders SET courier_id = :courier_id WHERE order_id = :order_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':courier_id', $courier['courier_id']);
            $stmt->bindParam(':order_id', $orderId);
            $stmt->execute();
            
            // Insert ke courier_deliveries
            $sql = "INSERT INTO courier_deliveries (courier_id, order_id, assigned_at, status) 
                    VALUES (:courier_id, :order_id, NOW(), 'assigned')";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':courier_id', $courier['courier_id']);
            $stmt->bindParam(':order_id', $orderId);
            $stmt->execute();
            
            // Update status kurir jadi on_delivery
            $sql = "UPDATE couriers SET status = 'on_delivery' WHERE courier_id = :courier_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':courier_id', $courier['courier_id']);
            $stmt->execute();
            
            return [
                'courier_id' => $courier['courier_id'],
                'courier_name' => $courier['name'],
                'courier_phone' => $courier['phone'],
                'courier_vehicle' => $courier['vehicle_type'] . ' - ' . $courier['vehicle_brand'] . ' ' . $courier['vehicle_model'] . ' (' . $courier['vehicle_plate'] . ')'
            ];
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Error assigning courier: " . $e->getMessage());
        return false;
    }
}

// Fungsi untuk update status pengiriman kurir
function updateDeliveryStatus($conn, $orderId, $status) {
    try {
        $sql = "UPDATE courier_deliveries 
                SET status = :status,
                    started_at = CASE WHEN :status = 'on_the_way' THEN NOW() ELSE started_at END,
                    completed_at = CASE WHEN :status IN ('delivered', 'failed') THEN NOW() ELSE completed_at END
                WHERE order_id = :order_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        
        // Jika delivered atau failed, update courier status jadi available lagi
        if ($status == 'delivered' || $status == 'failed') {
            $sql = "UPDATE couriers c
                    JOIN courier_deliveries cd ON c.courier_id = cd.courier_id
                    SET c.status = 'available'
                    WHERE cd.order_id = :order_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':order_id', $orderId);
            $stmt->execute();
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error updating delivery status: " . $e->getMessage());
        return false;
    }
}

// Fungsi untuk mendapatkan tracking pesanan (updated)
function getOrderTracking($conn, $orderId) {
    try {
        // Get order details with courier info from database
        $sql = "SELECT o.*, 
                c.name as customer_name, c.phone, c.address, c.email,
                cr.name as courier_name, cr.phone as courier_phone,
                CONCAT(cr.vehicle_type, ' - ', cr.vehicle_brand, ' ', cr.vehicle_model, ' (', cr.vehicle_plate, ')') as courier_vehicle,
                cd.status as delivery_status, cd.assigned_at, cd.started_at
                FROM orders o
                JOIN customers c ON o.customer_id = c.customer_id
                LEFT JOIN couriers cr ON o.courier_id = cr.courier_id
                LEFT JOIN courier_deliveries cd ON cd.order_id = o.order_id
                WHERE o.order_id = :order_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            return null;
        }
        
        // Get order items
        $sql = "SELECT oi.*, p.name 
                FROM order_items oi
                JOIN products p ON oi.product_id = p.product_id
                WHERE oi.order_id = :order_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get tracking history
        $sql = "SELECT * FROM order_tracking_history 
                WHERE order_id = :order_id 
                ORDER BY created_at ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        $trackingSteps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format tracking steps
        $formattedSteps = [];
        foreach ($trackingSteps as $step) {
            $formattedSteps[] = [
                'title' => $step['title'],
                'description' => $step['description'],
                'time' => date('d/m/Y H:i', strtotime($step['created_at'])),
                'completed' => (bool)$step['is_completed'],
                'current' => (bool)$step['is_current']
            ];
        }
        
        // Determine order status
        $status = 'Menunggu Pembayaran';
        if ($order['payment_status'] == 'paid' || $order['payment_method'] == 'cod') {
            if ($order['delivery_status'] == 'delivered') {
                $status = 'Selesai';
            } else if ($order['delivery_status'] == 'on_the_way') {
                $status = 'Dalam Pengiriman';
            } else if ($order['courier_id']) {
                $status = 'Kurir Ditugaskan';
            } else {
                $status = 'Diproses';
            }
        }
        
        // Format courier location based on delivery status
        $courierLocation = 'Menunggu penugasan kurir';
        if ($order['delivery_status'] == 'assigned') {
            $courierLocation = 'Kurir sedang bersiap';
        } else if ($order['delivery_status'] == 'on_the_way') {
            // Calculate estimated time
            $startTime = strtotime($order['started_at']);
            $now = time();
            $elapsed = round(($now - $startTime) / 60); // in minutes
            
            if ($elapsed < 5) {
                $courierLocation = 'Kurir baru saja berangkat';
            } else if ($elapsed < 15) {
                $courierLocation = 'Kurir sedang dalam perjalanan';
            } else if ($elapsed < 25) {
                $courierLocation = 'Kurir mendekati lokasi Anda';
            } else {
                $courierLocation = 'Kurir hampir sampai di lokasi Anda';
            }
        } else if ($order['delivery_status'] == 'delivered') {
            $courierLocation = 'Pesanan telah diterima';
        }
        
        return [
            'order_id' => '#' . str_pad($orderId, 6, '0', STR_PAD_LEFT),
            'status' => $status,
            'order_date' => date('d/m/Y H:i', strtotime($order['created_at'])),
            'estimated_arrival' => date('d/m/Y H:i', strtotime($order['estimated_delivery'])),
            'payment_status' => ucfirst($order['payment_status']),
            'customer_name' => $order['customer_name'],
            'address' => $order['address'],
            'total_amount' => $order['total_amount'],
            'items' => $items,
            'tracking_steps' => $formattedSteps,
            'courier_info' => [
                'name' => $order['courier_name'] ?: 'Belum ditugaskan',
                'phone' => $order['courier_phone'] ?: '-',
                'vehicle_info' => $order['courier_vehicle'] ?: '-',
                'current_location' => $courierLocation,
                'delivery_status' => $order['delivery_status']
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Error getting order tracking: " . $e->getMessage());
        return null;
    }
}

// Fungsi untuk mendapatkan statistik kurir
function getCourierStats($conn, $courierId) {
    try {
        $sql = "SELECT 
                COUNT(CASE WHEN status = 'delivered' THEN 1 END) as total_delivered,
                COUNT(CASE WHEN DATE(assigned_at) = CURDATE() THEN 1 END) as today_deliveries,
                COUNT(CASE WHEN status = 'on_the_way' THEN 1 END) as ongoing_deliveries
                FROM courier_deliveries
                WHERE courier_id = :courier_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':courier_id', $courierId);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error getting courier stats: " . $e->getMessage());
        return null;
    }
}
?>