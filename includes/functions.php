<?php
require_once 'db.php';

/**
 * Sanitize user input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Set flash message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Format amount to Rupiah
 */
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Create or update customer record
 */
function createCustomer($conn, $name, $email, $phone, $address) {
    try {
        // Check if customer exists
        $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE phone = ?");
        $stmt->execute([$phone]);
        $customer = $stmt->fetch();
        
        if ($customer) {
            // Update existing customer
            $sql = "UPDATE customers SET name = ?, email = ?, address = ? WHERE customer_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$name, $email, $address, $customer['customer_id']]);
            return $customer['customer_id'];
        } else {
            // Create new customer
            $sql = "INSERT INTO customers (name, email, phone, address, created_at) VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$name, $email, $phone, $address]);
            return $conn->lastInsertId();
        }
    } catch (PDOException $e) {
        error_log("Error in createCustomer: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Add item to order
 */
function addOrderItem($conn, $orderId, $productId, $quantity, $price, $subtotal) {
    try {
        // Verify product exists and has sufficient stock
        $stmt = $conn->prepare("SELECT stock FROM products WHERE product_id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            throw new Exception("Product not found");
        }
        
        // Add order item
        $sql = "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$orderId, $productId, $quantity, $price, $subtotal]);
        
        // Update product stock
        $sql = "UPDATE products SET stock = stock - ? WHERE product_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$quantity, $productId]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error in addOrderItem: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Get product by ID
 */
function getProductById($conn, $productId) {
    try {
        $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
        $stmt->execute([$productId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error in getProductById: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all products
 */
function getAllProducts($conn) {
    try {
        $stmt = $conn->query("SELECT * FROM products WHERE active = 1 ORDER BY name");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error in getAllProducts: " . $e->getMessage());
        return [];
    }
}

/**
 * Auto assign courier to order
 */
function autoAssignCourier($conn, $orderId, $address) {
    try {
        // Get available courier
        $sql = "SELECT courier_id, name as courier_name, phone as courier_phone 
                FROM couriers 
                WHERE status = 'available' 
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $courier = $stmt->fetch();
        
        if ($courier) {
            // Assign courier to order
            $sql = "UPDATE orders SET courier_id = ?, order_status = 'processing' 
                    WHERE order_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$courier['courier_id'], $orderId]);
            
            // Update courier status
            $sql = "UPDATE couriers SET status = 'assigned' WHERE courier_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$courier['courier_id']]);
            
            return $courier;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Error in autoAssignCourier: " . $e->getMessage());
        return false;
    }
}

/**
 * Update payment status
 */
function updatePaymentStatus($conn, $orderId, $status) {
    try {
        $sql = "UPDATE orders SET payment_status = ? WHERE order_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$status, $orderId]);
        return true;
    } catch (PDOException $e) {
        error_log("Error in updatePaymentStatus: " . $e->getMessage());
        return false;
    }
}

/**
 * Add tracking history
 */
function addTrackingHistory($conn, $orderId, $title, $description) {
    try {
        // Set all current flags to 0 first
        $sql = "UPDATE order_tracking_history SET is_current = 0 
                WHERE order_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$orderId]);
        
        // Add new tracking entry
        $sql = "INSERT INTO order_tracking_history 
                (order_id, title, description, is_completed, is_current, created_at) 
                VALUES (?, ?, ?, 1, 1, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$orderId, $title, $description]);
        return true;
    } catch (PDOException $e) {
        error_log("Error in addTrackingHistory: " . $e->getMessage());
        return false;
    }
}

/**
 * Get order tracking info
 */
function getOrderTracking($conn, $orderId) {
    try {
        // Get order details
        $sql = "SELECT o.*, c.name as customer_name, c.address, c.phone,
                       cr.name as courier_name, cr.phone as courier_phone
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.customer_id
                LEFT JOIN couriers cr ON o.courier_id = cr.courier_id
                WHERE o.order_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            return false;
        }
        
        // Get order items
        $sql = "SELECT oi.*, p.name 
                FROM order_items oi
                JOIN products p ON oi.product_id = p.product_id
                WHERE oi.order_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll();
        
        // Get tracking history
        $sql = "SELECT * FROM order_tracking_history 
                WHERE order_id = ? 
                ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$orderId]);
        $tracking = $stmt->fetchAll();
        
        return [
            'order_id' => str_pad($order['order_id'], 6, '0', STR_PAD_LEFT),
            'status' => $order['order_status'],
            'payment_status' => $order['payment_status'],
            'order_date' => $order['created_at'],
            'estimated_arrival' => $order['estimated_delivery'],
            'customer_name' => $order['customer_name'],
            'address' => $order['address'],
            'items' => $items,
            'total_amount' => $order['total_amount'],
            'courier_info' => [
                'name' => $order['courier_name'],
                'phone' => $order['courier_phone'],
                'current_location' => 'En route to delivery address', // This should be dynamic in real implementation
                'vehicle_info' => 'Motor Delivery'
            ],
            'tracking_steps' => $tracking
        ];
    } catch (PDOException $e) {
        error_log("Error in getOrderTracking: " . $e->getMessage());
        return false;
    }
}

/**
 * Handle payment proof upload
 */
function handlePaymentProofUpload($file, $orderId) {
    try {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Upload failed with error code: " . $file['error']);
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception("File too large. Maximum size is " . (MAX_FILE_SIZE / 1024 / 1024) . "MB");
        }
        
        if (!in_array($file['type'], ALLOWED_FILE_TYPES)) {
            throw new Exception("Invalid file type. Allowed types: JPG, PNG, PDF");
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'payment_' . $orderId . '_' . time() . '.' . $extension;
        $uploadPath = UPLOAD_PATH . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception("Failed to move uploaded file");
        }
        
        return [
            'success' => true,
            'filename' => $filename
        ];
    } catch (Exception $e) {
        error_log("Error in handlePaymentProofUpload: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Get subscription plans
 */
function getAllSubscriptionPlans($conn) {
    try {
        $stmt = $conn->query("SELECT * FROM subscription_plans WHERE active = 1");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error in getAllSubscriptionPlans: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user subscriptions
 */
function getUserSubscriptions($conn, $userId) {
    try {
        $sql = "SELECT s.*, sp.name as plan_name 
                FROM subscriptions s
                JOIN subscription_plans sp ON s.plan_id = sp.id
                WHERE s.user_id = ? AND s.status = 'active'";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error in getUserSubscriptions: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user order history
 */
function getUserOrderHistory($conn, $userId = null) {
    try {
        $sql = "SELECT o.*, c.name as customer_name
                FROM orders o
                JOIN customers c ON o.customer_id = c.customer_id ";
        
        if ($userId) {
            $sql .= "WHERE c.user_id = ? ";
            $params = [$userId];
        } else {
            $params = [];
        }
        
        $sql .= "ORDER BY o.created_at DESC LIMIT 10";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error in getUserOrderHistory: " . $e->getMessage());
        return [];
    }
}
