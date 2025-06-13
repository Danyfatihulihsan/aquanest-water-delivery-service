<?php
session_start();
require_once 'includes/functions.php';

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Initialize active tab
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'order';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        switch ($_POST['action']) {
            case 'add_to_cart':
                $productId = (int)$_POST['product_id'];
                $quantity = (int)$_POST['quantity'];
                $product = getProductById($conn, $productId);

                if ($product) {
                    if (!isset($_SESSION['cart'][$productId])) {
                        $_SESSION['cart'][$productId] = [
                            'name' => $product['name'],
                            'price' => $product['price'],
                            'quantity' => $quantity,
                            'subtotal' => $product['price'] * $quantity
                        ];
                    } else {
                        $_SESSION['cart'][$productId]['quantity'] += $quantity;
                        $_SESSION['cart'][$productId]['subtotal'] = 
                            $_SESSION['cart'][$productId]['price'] * $_SESSION['cart'][$productId]['quantity'];
                    }
                    setFlashMessage('success', 'Product added to cart');
                }
                break;

            case 'update_cart':
                $productId = (int)$_POST['product_id'];
                $quantity = (int)$_POST['quantity'];

                if ($quantity > 0) {
                    $_SESSION['cart'][$productId]['quantity'] = $quantity;
                    $_SESSION['cart'][$productId]['subtotal'] = 
                        $_SESSION['cart'][$productId]['price'] * $quantity;
                } else {
                    unset($_SESSION['cart'][$productId]);
                }
                setFlashMessage('success', 'Cart updated');
                break;

            case 'place_order':
                // Validate cart is not empty
                if (empty($_SESSION['cart'])) {
                    throw new Exception('Cart is empty');
                }

                // Create or update customer
                $customerId = createCustomer(
                    $conn,
                    sanitize($_POST['name']),
                    sanitize($_POST['email']),
                    sanitize($_POST['phone']),
                    sanitize($_POST['address'])
                );

                // Calculate total amount
                $totalAmount = 0;
                foreach ($_SESSION['cart'] as $item) {
                    $totalAmount += $item['subtotal'];
                }

                // Create order
                $sql = "INSERT INTO orders (customer_id, total_amount, payment_method, payment_status, 
                        order_status, notes, estimated_delivery) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $customerId,
                    $totalAmount,
                    $_POST['payment_method'],
                    'pending',
                    'pending',
                    sanitize($_POST['notes'] ?? ''),
                    $_POST['delivery_date'] . ' ' . $_POST['time_slot']
                ]);
                $orderId = $conn->lastInsertId();

                // Add order items
                foreach ($_SESSION['cart'] as $productId => $item) {
                    addOrderItem(
                        $conn,
                        $orderId,
                        $productId,
                        $item['quantity'],
                        $item['price'],
                        $item['subtotal']
                    );
                }

                // Handle payment method specific logic
                switch ($_POST['payment_method']) {
                    case 'cod':
                        updatePaymentStatus($conn, $orderId, 'pending');
                        // Auto assign courier for COD orders
                        $courier = autoAssignCourier($conn, $orderId, $_POST['address']);
                        break;

                    case 'bank_transfer':
                        updatePaymentStatus($conn, $orderId, 'waiting');
                        break;

                    case 'qris':
                        // In real implementation, integrate with QRIS payment gateway
                        updatePaymentStatus($conn, $orderId, 'waiting');
                        break;
                }

                // Handle payment proof upload if exists
                if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
                    $upload = handlePaymentProofUpload($_FILES['payment_proof'], $orderId);
                    if ($upload['success']) {
                        $sql = "UPDATE orders SET payment_proof = ? WHERE order_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$upload['filename'], $orderId]);
                    }
                }

                $conn->commit();
                
                // Clear cart after successful order
                $_SESSION['cart'] = [];
                
                // Redirect to tracking page
                redirect("order.php?tab=track&order_id=" . $orderId);

            case 'confirm_payment':
                $orderId = (int)$_POST['order_id'];
                
                // Handle payment proof upload
                if (isset($_FILES['payment_proof'])) {
                    $upload = handlePaymentProofUpload($_FILES['payment_proof'], $orderId);
                    if ($upload['success']) {
                        $sql = "UPDATE orders SET payment_proof = ?, payment_status = 'waiting' 
                                WHERE order_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$upload['filename'], $orderId]);
                        
                        addTrackingHistory(
                            $conn,
                            $orderId,
                            'Bukti Pembayaran Diunggah',
                            'Menunggu konfirmasi pembayaran dari admin'
                        );
                        
                        setFlashMessage('success', 'Payment proof uploaded successfully');
                    } else {
                        throw new Exception($upload['message']);
                    }
                }
                break;

            case 'subscribe':
                $planId = (int)$_POST['plan_id'];
                $deliveryDay = sanitize($_POST['delivery_day']);
                
                // Create customer if not exists
                $customerId = createCustomer(
                    $conn,
                    sanitize($_POST['name']),
                    sanitize($_POST['email']),
                    sanitize($_POST['phone']),
                    sanitize($_POST['address'])
                );
                
                // Get plan details
                $stmt = $conn->prepare("SELECT * FROM subscription_plans WHERE id = ?");
                $stmt->execute([$planId]);
                $plan = $stmt->fetch();
                
                if (!$plan) {
                    throw new Exception('Invalid subscription plan');
                }
                
                // Create subscription
                $sql = "INSERT INTO subscriptions (user_id, plan_id, delivery_day, next_delivery) 
                        VALUES (?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 1 DAY))";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$customerId, $planId, $deliveryDay]);
                
                setFlashMessage('success', 'Subscription created successfully');
                break;
        }
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error in order.php: " . $e->getMessage());
        setFlashMessage('error', $e->getMessage());
    }
    
    // Redirect to prevent form resubmission
    redirect($_SERVER['HTTP_REFERER'] ?? 'order.php');
}

// Get products for order tab
$products = getAllProducts($conn);

// Get subscription plans
$subscriptionPlans = getAllSubscriptionPlans($conn);

// Get tracking info if order_id is provided
$trackingInfo = null;
if (isset($_GET['order_id'])) {
    $trackingInfo = getOrderTracking($conn, $_GET['order_id']);
}

// Calculate cart total
$cartTotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartTotal += $item['subtotal'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aquanest - Water Delivery Service</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-900">Aquanest</h1>
                <nav>
                    <ul class="flex space-x-8">
                        <li>
                            <a href="?tab=order" 
                               class="<?= $activeTab === 'order' ? 'text-blue-600' : 'text-gray-500' ?> 
                                      hover:text-blue-600">
                                Order
                            </a>
                        </li>
                        <li>
                            <a href="?tab=track" 
                               class="<?= $activeTab === 'track' ? 'text-blue-600' : 'text-gray-500' ?> 
                                      hover:text-blue-600">
                                Track
                            </a>
                        </li>
                        <li>
                            <a href="?tab=subscription" 
                               class="<?= $activeTab === 'subscription' ? 'text-blue-600' : 'text-gray-500' ?> 
                                      hover:text-blue-600">
                                Subscription
                            </a>
                        </li>
                        <li>
                            <a href="?tab=history" 
                               class="<?= $activeTab === 'history' ? 'text-blue-600' : 'text-gray-500' ?> 
                                      hover:text-blue-600">
                                History
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash'])): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="rounded-md p-4 <?= $_SESSION['flash']['type'] === 'success' ? 'bg-green-50' : 'bg-red-50' ?>">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <?php if ($_SESSION['flash']['type'] === 'success'): ?>
                            <i class="fas fa-check-circle text-green-400"></i>
                        <?php else: ?>
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        <?php endif; ?>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm <?= $_SESSION['flash']['type'] === 'success' ? 'text-green-800' : 'text-red-800' ?>">
                            <?= $_SESSION['flash']['message'] ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if ($activeTab === 'order'): ?>
            <!-- Order Tab -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Products List -->
                <div class="md:col-span-2">
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-lg font-semibold mb-4">Products</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <?php foreach ($products as $product): ?>
                                <div class="border rounded-lg p-4">
                                    <h3 class="font-medium"><?= $product['name'] ?></h3>
                                    <p class="text-gray-500 text-sm mt-1"><?= $product['description'] ?></p>
                                    <p class="text-lg font-semibold mt-2"><?= formatRupiah($product['price']) ?></p>
                                    
                                    <form action="order.php" method="POST" class="mt-4">
                                        <input type="hidden" name="action" value="add_to_cart">
                                        <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                                        
                                        <div class="flex items-center space-x-4">
                                            <input type="number" 
                                                   name="quantity" 
                                                   value="1" 
                                                   min="1" 
                                                   max="<?= $product['stock'] ?>"
                                                   class="w-20 rounded-md border-gray-300 shadow-sm focus:border-blue-500 
                                                          focus:ring-blue-500">
                                            
                                            <button type="submit" 
                                                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 
                                                           focus:outline-none focus:ring-2 focus:ring-blue-500 
                                                           focus:ring-offset-2">
                                                Add to Cart
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Cart -->
                <div class="md:col-span-1">
                    <div class="bg-white rounded-lg shadow p-6 sticky top-4">
                        <h2 class="text-lg font-semibold mb-4">Shopping Cart</h2>
                        
                        <?php if (empty($_SESSION['cart'])): ?>
                            <p class="text-gray-500">Your cart is empty</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($_SESSION['cart'] as $productId => $item): ?>
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium"><?= $item['name'] ?></h4>
                                            <p class="text-sm text-gray-500">
                                                <?= formatRupiah($item['price']) ?> x <?= $item['quantity'] ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-medium"><?= formatRupiah($item['subtotal']) ?></p>
                                            
                                            <form action="order.php" method="POST" class="mt-1">
                                                <input type="hidden" name="action" value="update_cart">
                                                <input type="hidden" name="product_id" value="<?= $productId ?>">
                                                <button type="submit" 
                                                        name="quantity" 
                                                        value="0"
                                                        class="text-sm text-red-600 hover:text-red-800">
                                                    Remove
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <div class="border-t pt-4 mt-4">
                                    <div class="flex justify-between">
                                        <p class="font-semibold">Total:</p>
                                        <p class="font-semibold"><?= formatRupiah($cartTotal) ?></p>
                                    </div>
                                </div>

                                <form action="order.php" method="POST" enctype="multipart/form-data" class="mt-6">
                                    <input type="hidden" name="action" value="place_order">
                                    
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Name</label>
                                            <input type="text" 
                                                   name="name" 
                                                   required 
                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                          focus:border-blue-500 focus:ring-blue-500">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Email</label>
                                            <input type="email" 
                                                   name="email" 
                                                   required 
                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                          focus:border-blue-500 focus:ring-blue-500">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Phone</label>
                                            <input type="tel" 
                                                   name="phone" 
                                                   required 
                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                          focus:border-blue-500 focus:ring-blue-500">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Address</label>
                                            <textarea name="address" 
                                                      required 
                                                      rows="3" 
                                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                             focus:border-blue-500 focus:ring-blue-500"></textarea>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Delivery Date</label>
                                            <input type="date" 
                                                   name="delivery_date" 
                                                   required 
                                                   min="<?= date('Y-m-d') ?>"
                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                          focus:border-blue-500 focus:ring-blue-500">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Time Slot</label>
                                            <select name="time_slot" 
                                                    required 
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                           focus:border-blue-500 focus:ring-blue-500">
                                                <option value="09:00:00">09:00 - 12:00</option>
                                                <option value="13:00:00">13:00 - 16:00</option>
                                                <option value="16:00:00">16:00 - 19:00</option>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Payment Method</label>
                                            <select name="payment_method" 
                                                    required 
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                           focus:border-blue-500 focus:ring-blue-500">
                                                <option value="cod">Cash on Delivery</option>
                                                <option value="bank_transfer">Bank Transfer</option>
                                                <option value="qris">QRIS</option>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Notes</label>
                                            <textarea name="notes" 
                                                      rows="2" 
                                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                             focus:border-blue-500 focus:ring-blue-500"></textarea>
                                        </div>
                                    </div>

                                    <button type="submit" 
                                            class="mt-6 w-full bg-blue-600 text-white px-4 py-2 rounded-md 
                                                   hover:bg-blue-700 focus:outline-none focus:ring-2 
                                                   focus:ring-blue-500 focus:ring-offset-2">
                                        Place Order
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php elseif ($activeTab === 'track'): ?>
            <!-- Track Tab -->
            <div class="bg-white rounded-lg shadow p-6">
                <?php if (!$trackingInfo): ?>
                    <form action="order.php" method="GET" class="max-w-md mx-auto">
                        <input type="hidden" name="tab" value="track">
                        
                        <div class="flex space-x-4">
                            <input type="text" 
                                   name="order_id" 
                                   placeholder="Enter your order ID" 
                                   required 
                                   class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 
                                          focus:ring-blue-500">
                            
                            <button type="submit" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 
                                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Track Order
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="max-w-3xl mx-auto">
                        <div class="flex justify-between items-start">
                            <div>
                                <h2 class="text-lg font-semibold">Order #<?= $trackingInfo['order_id'] ?></h2>
                                <p class="text-gray-500">
                                    Ordered on <?= date('d M Y', strtotime($trackingInfo['order_date'])) ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-medium">Total Amount:</p>
                                <p class="text-lg font-semibold"><?= formatRupiah($trackingInfo['total_amount']) ?></p>
                            </div>
                        </div>

                        <div class="mt-8">
                            <h3 class="text-lg font-medium">Delivery Status</h3>
                            
                            <div class="mt-4 space-y-8">
                                <?php foreach ($trackingInfo['tracking_steps'] as $step): ?>
                                    <div class="relative">
                                        <?php if (!$step['is_current']): ?>
                                            <div class="absolute top-0 left-5 h-full w-0.5 bg-gray-200"></div>
                                        <?php endif; ?>
                                        
                                        <div class="relative flex items-start">
                                            <span class="flex h-10 w-10 items-center justify-center rounded-full 
                                                         <?= $step['is_completed'] ? 'bg-blue-600' : 'bg-gray-200' ?>">
                                                <i class="fas fa-check text-white"></i>
                                            </span>
                                            
                                            <div class="ml-4">
                                                <h4 class="font-medium"><?= $step['title'] ?></h4>
                                                <p class="text-gray-500"><?= $step['description'] ?></p>
                                                <p class="text-sm text-gray-400">
                                                    <?= date('d M Y H:i', strtotime($step['created_at'])) ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php if ($trackingInfo['courier_info']): ?>
                            <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                                <h3 class="text-lg font-medium">Courier Information</h3>
                                <div class="mt-2 space-y-2">
                                    <p>
                                        <span class="font-medium">Name:</span> 
                                        <?= $trackingInfo['courier_info']['name'] ?>
                                    </p>
                                    <p>
                                        <span class="font-medium">Phone:</span> 
                                        <?= $trackingInfo['courier_info']['phone'] ?>
                                    </p>
                                    <p>
                                        <span class="font-medium">Current Location:</span> 
                                        <?= $trackingInfo['courier_info']['current_location'] ?>
                                    </p>
                                    <p>
                                        <span class="font-medium">Vehicle:</span> 
                                        <?= $trackingInfo['courier_info']['vehicle_info'] ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($activeTab === 'subscription'): ?>
            <!-- Subscription Tab -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-6">Subscription Plans</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($subscriptionPlans as $plan): ?>
                        <div class="border rounded-lg p-6 <?= $plan['popular'] ? 'ring-2 ring-blue-600' : '' ?>">
                            <?php if ($plan['popular']): ?>
                                <span class="inline-block px-3 py-1 text-xs font-semibold text-blue-600 
                                           bg-blue-100 rounded-full mb-4">
                                    Paling Populer
                                </span>
                            <?php endif; ?>
                            
                            <h3 class="text-xl font-semibold"><?= $plan['name'] ?></h3>
                            <p class="text-gray-500 mt-2"><?= $plan['description'] ?></p>
                            
                            <div class="mt-4">
                                <span class="text-2xl font-bold"><?= formatRupiah($plan['price']) ?></span>
                                <span class="text-gray-500">/<?= strtolower($plan['duration']) ?></span>
                            </div>
                            
                            <?php if ($plan['discount']): ?>
                                <p class="text-green-600 mt-2">
                                    <i class="fas fa-tag"></i> 
                                    Hemat <?= $plan['discount'] ?>
                                </p>
                            <?php endif; ?>
                            
                            <ul class="mt-6 space-y-4">
                                <li class="flex items-center">
                                    <i class="fas fa-check text-green-500 mr-2"></i>
                                    <?= $plan['delivery_count'] ?>x pengiriman
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-check text-green-500 mr-2"></i>
                                    Jadwal fleksibel
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-check text-green-500 mr-2"></i>
                                    Bisa dibatalkan kapan saja
                                </li>
                            </ul>
                            
                            <button onclick="showSubscribeForm(<?= $plan['id'] ?>)" 
                                    class="mt-8 w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 
                                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Subscribe Now
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Subscribe Form Modal -->
            <div id="subscribeModal" 
                 class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden"
                 onclick="hideSubscribeForm()">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6" 
                         onclick="event.stopPropagation()">
                        <h3 class="text-lg font-semibold mb-4">Subscribe to Plan</h3>
                        
                        <form action="order.php" method="POST">
                            <input type="hidden" name="action" value="subscribe">
                            <input type="hidden" name="plan_id" id="selectedPlanId">
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Name</label>
                                    <input type="text" 
                                           name="name" 
                                           required 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                  focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Email</label>
                                    <input type="email" 
                                           name="email" 
                                           required 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                  focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Phone</label>
                                    <input type="tel" 
                                           name="phone" 
                                           required 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                  focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Address</label>
                                    <textarea name="address" 
                                              required 
                                              rows="3" 
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                     focus:border-blue-500 focus:ring-blue-500"></textarea>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Delivery Day</label>
                                    <select name="delivery_day" 
                                            required 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                   focus:border-blue-500 focus:ring-blue-500">
                                        <option value="monday">Monday</option>
                                        <option value="tuesday">Tuesday</option>
                                        <option value="wednesday">Wednesday</option>
                                        <option value="thursday">Thursday</option>
                                        <option value="friday">Friday</option>
                                        <option value="saturday">Saturday</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end space-x-3">
                                <button type="button" 
                                        onclick="hideSubscribeForm()"
                                        class="px-4 py-2 border rounded-md text-gray-700 hover:bg-gray-50 
                                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    Cancel
                                </button>
                                <button type="submit" 
                                        class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 
                                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    Subscribe
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        <?php elseif ($activeTab === 'history'): ?>
            <!-- History Tab -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-6">Order History</h2>
                
                <?php 
                $orderHistory = getUserOrderHistory($conn);
                if (empty($orderHistory)): 
                ?>
                    <p class="text-gray-500">No order history found</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Order ID
                                    </th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date
                                    </th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Customer
                                    </th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Amount
                                    </th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 bg-gray-50"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($orderHistory as $order): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            #<?= str_pad($order['order_id'], 6, '0', STR_PAD_LEFT) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('d M Y', strtotime($order['created_at'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?= $order['customer_name'] ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?= formatRupiah($order['total_amount']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                       <?php
                                                       switch ($order['order_status']) {
                                                           case 'delivered':
                                                               echo 'bg-green-100 text-green-800';
                                                               break;
                                                           case 'processing':
                                                           case 'on_delivery':
                                                               echo 'bg-blue-100 text-blue-800';
                                                               break;
                                                           case 'cancelled':
                                                               echo 'bg-red-100 text-red-800';
                                                               break;
                                                           default:
                                                               echo 'bg-gray-100 text-gray-800';
                                                       }
                                                       ?>">
                                                <?= ucfirst($order['order_status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                            <a href="?tab=track&order_id=<?= $order['order_id'] ?>" 
                                               class="text-blue-600 hover:text-blue-900">
                                                Track Order
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function showSubscribeForm(planId) {
            document.getElementById('selectedPlanId').value = planId;
            document.getElementById('subscribeModal').classList.remove('hidden');
        }
        
        function hideSubscribeForm() {
            document.getElementById('subscribeModal').classList.add('hidden');
        }
    </script>
</body>
</html>
