<?php
// Start session
session_start();

// Include database connection
require_once 'includes/db.php';
require_once 'includes/functions.php';


if (!isset($_SESSION['user_id'])) {
    echo "<p style='text-align:center; margin-top:50px;'>Silakan <a href='login.php?redirect=order'>login</a> untuk melakukan pemesanan.</p>";
    exit();
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Initialize active tab
if (!isset($_SESSION['active_tab'])) {
    $_SESSION['active_tab'] = 'order';
}

// Handle tab change
if (isset($_GET['tab'])) {
    $_SESSION['active_tab'] = $_GET['tab'];
}

// Process add to cart
if (isset($_POST['add_to_cart'])) {
    $productId = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    
    // Get product details
    $product = getProductById($conn, $productId);
    
    if ($product) {
        // Check if product already in cart
        $found = false;
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['product_id'] == $productId) {
                $_SESSION['cart'][$key]['quantity'] += $quantity;
                $_SESSION['cart'][$key]['subtotal'] = $_SESSION['cart'][$key]['quantity'] * $_SESSION['cart'][$key]['price'];
                $found = true;
                break;
            }
        }
        
        // If not found, add new item
        if (!$found) {
            $_SESSION['cart'][] = [
                'product_id' => $productId,
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'subtotal' => $product['price'] * $quantity,
                'image' => $product['image'] ?? ''
            ];
        }
        
        setFlashMessage('success', 'Produk berhasil ditambahkan ke keranjang.');
    }
    
    // Redirect to avoid form resubmission
    redirect('order.php?tab=order');
}

// Process remove from cart
if (isset($_GET['remove']) && isset($_GET['index'])) {
    $index = (int)$_GET['index'];
    if (isset($_SESSION['cart'][$index])) {
        unset($_SESSION['cart'][$index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array
        setFlashMessage('success', 'Produk berhasil dihapus dari keranjang.');
    }
    redirect('order.php?tab=order');
}

// Process update cart
if (isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $index => $quantity) {
        $index = (int)$index;
        $quantity = (int)$quantity;
        if (isset($_SESSION['cart'][$index]) && $quantity > 0) {
            $_SESSION['cart'][$index]['quantity'] = $quantity;
            $_SESSION['cart'][$index]['subtotal'] = $_SESSION['cart'][$index]['price'] * $quantity;
        }
    }
    setFlashMessage('success', 'Keranjang berhasil diperbarui.');
    redirect('order.php?tab=order');
}

// Process checkout
// Ganti bagian Process checkout di order.php dengan kode ini:

// Process checkout
if (isset($_POST['checkout'])) {
    // Validate input
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $payment_method = sanitize($_POST['payment_method']);
    $notes = sanitize($_POST['notes'] ?? '');
    $delivery_date = sanitize($_POST['delivery_date'] ?? date('Y-m-d'));
    $delivery_time = sanitize($_POST['delivery_time'] ?? '08:00 - 10:00');
    
    // Validate required fields
    if (empty($name) || empty($phone) || empty($address) || empty($payment_method)) {
        setFlashMessage('danger', 'Nama, nomor telepon, alamat, dan metode pembayaran wajib diisi.');
        redirect('order.php?tab=order');
    } 
    // Validate cart not empty
    else if (empty($_SESSION['cart'])) {
        setFlashMessage('danger', 'Keranjang belanja kosong. Silakan tambahkan produk terlebih dahulu.');
        redirect('order.php?tab=order');
    } 
    else {
        // Calculate total amount
        $totalAmount = 0;
        foreach ($_SESSION['cart'] as $item) {
            $totalAmount += $item['subtotal'];
        }
        
        // Begin transaction
        $conn->beginTransaction();
        
        try {
            // Create customer
            $customerId = createCustomer($conn, $name, $email, $phone, $address);
            
            // Calculate estimated delivery datetime
            $estimatedDelivery = $delivery_date . ' ' . explode(' - ', $delivery_time)[0] . ':00';
            
            // Create order WITHOUT courier_id first
            $sql = "INSERT INTO orders (customer_id, total_amount, payment_method, payment_status, notes, estimated_delivery, created_at) 
                    VALUES (:customer_id, :total_amount, :payment_method, :payment_status, :notes, :estimated_delivery, NOW())";
            $stmt = $conn->prepare($sql);
            
            // Set payment status based on payment method
            $paymentStatus = ($payment_method == 'cod') ? 'pending' : 'waiting';
            
            $stmt->bindParam(':customer_id', $customerId);
            $stmt->bindParam(':total_amount', $totalAmount);
            $stmt->bindParam(':payment_method', $payment_method);
            $stmt->bindParam(':payment_status', $paymentStatus);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':estimated_delivery', $estimatedDelivery);
            $stmt->execute();
            $orderId = $conn->lastInsertId();
            
            // Add order items
            foreach ($_SESSION['cart'] as $item) {
                addOrderItem(
                    $conn, 
                    $orderId, 
                    $item['product_id'], 
                    $item['quantity'], 
                    $item['price'], 
                    $item['subtotal']
                );
            }
            
            // Create initial tracking history
            $trackingSteps = [
                ['title' => 'Pesanan Dibuat', 'description' => 'Pesanan Anda telah diterima oleh sistem kami', 'completed' => true]
            ];
            
            if ($payment_method == 'cod') {
                // For COD, payment is confirmed when delivered
                $trackingSteps[] = ['title' => 'Pembayaran COD', 'description' => 'Pembayaran akan dilakukan saat pesanan tiba', 'completed' => false];
                
                // Auto assign courier for COD orders FROM DATABASE
                $courier = autoAssignCourier($conn, $orderId, $address);
                if ($courier) {
                    $trackingSteps[] = [
                        'title' => 'Kurir Ditugaskan', 
                        'description' => 'Kurir: ' . $courier['courier_name'] . ' (' . $courier['courier_phone'] . ')', 
                        'completed' => true
                    ];
                    
                    // Update order status
                    $sql = "UPDATE orders SET order_status = 'processing' WHERE order_id = :order_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':order_id', $orderId);
                    $stmt->execute();
                }
            } else {
                // For bank transfer and QRIS, need payment confirmation
                $trackingSteps[] = ['title' => 'Menunggu Pembayaran', 'description' => 'Silakan lakukan pembayaran sesuai metode yang dipilih', 'completed' => false];
            }
            
            // Insert tracking history
            foreach ($trackingSteps as $index => $step) {
                $isCurrent = ($index == count($trackingSteps) - 1) ? 1 : 0;
                $sql = "INSERT INTO order_tracking_history (order_id, title, description, is_completed, is_current, created_at) 
                        VALUES (:order_id, :title, :description, :is_completed, :is_current, NOW())";
                $stmt = $conn->prepare($sql);
                $isCompleted = $step['completed'] ? 1 : 0;
                $stmt->bindParam(':order_id', $orderId);
                $stmt->bindParam(':title', $step['title']);
                $stmt->bindParam(':description', $step['description']);
                $stmt->bindParam(':is_completed', $isCompleted);
                $stmt->bindParam(':is_current', $isCurrent);
                $stmt->execute();
            }
            
            // Commit transaction
            $conn->commit();
            
            // Clear cart
            $_SESSION['cart'] = [];
            
            // Set success message and order ID for tracking
            $_SESSION['last_order_id'] = $orderId;
            setFlashMessage('success', 'Pesanan berhasil dibuat. Nomor pesanan: #' . str_pad($orderId, 6, '0', STR_PAD_LEFT));
            
            // Redirect based on payment method
            if ($payment_method == 'cod') {
                // For COD, go directly to tracking
                redirect('order.php?tab=track&order_id=' . $orderId);
            } else {
                // For other payment methods, go to payment page
                redirect('order.php?tab=payment&order_id=' . $orderId);
            }
            
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollBack();
            error_log("Checkout error: " . $e->getMessage());
            setFlashMessage('danger', 'Terjadi kesalahan saat memproses pesanan. Silakan coba lagi.');
            redirect('order.php?tab=order');
        }
    }
}

// Dan untuk payment confirmation, update bagian auto assign courier setelah payment:
if (isset($_POST['confirm_payment'])) {
    $order_id = (int)sanitize($_POST['order_id']);
    
    // Get order info
    $sql = "SELECT payment_method FROM orders WHERE order_id = :order_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':order_id', $order_id);
    $stmt->execute();
    $order = $stmt->fetch();
    
    if ($order) {
        if ($order['payment_method'] == 'qris') {
            // For QRIS, payment is confirmed automatically
            updatePaymentStatus($conn, $order_id, 'paid');
            
            // Update tracking history
            addTrackingHistory($conn, $order_id, 'Pembayaran Berhasil', 'Pembayaran QRIS telah dikonfirmasi');
            
            // Auto assign courier after payment FROM DATABASE
            $address = ''; // Get from order if needed
            $courier = autoAssignCourier($conn, $order_id, $address);
            if ($courier) {
                addTrackingHistory($conn, $order_id, 'Kurir Ditugaskan', 
                                 'Kurir: ' . $courier['courier_name'] . ' (' . $courier['courier_phone'] . ')');
            }
            
            // Set success message and order ID for tracking
            $_SESSION['last_order_id'] = $orderId;
            setFlashMessage('success', 'Pesanan berhasil dibuat. Nomor pesanan: #' . str_pad($orderId, 6, '0', STR_PAD_LEFT));
     
            // Redirect based on payment method
            if ($payment_method == 'cod') {
                // For COD, go directly to tracking
                redirect('order.php?tab=track&order_id=' . $orderId);
            } else {
                // For other payment methods, go to payment page
                redirect('order.php?tab=payment&order_id=' . $orderId);
            }
            
        } 
    }
}

// Process payment confirmation
if (isset($_POST['confirm_payment'])) {
    $order_id = (int)sanitize($_POST['order_id']);
    
    // Get order info
    $sql = "SELECT payment_method FROM orders WHERE order_id = :order_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':order_id', $order_id);
    $stmt->execute();
    $order = $stmt->fetch();
    
    if ($order) {
        if ($order['payment_method'] == 'cod') {
            // Untuk COD, tidak perlu konfirmasi pembayaran, langsung ke tracking
            redirect('order.php?tab=track&order_id=' . $order_id);
        }
        else if ($order['payment_method'] == 'bank_transfer') {
            // Handle file upload untuk transfer receipt
            if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = handlePaymentProofUpload($_FILES['payment_proof'], $order_id);
                
                if ($uploadResult['success']) {
                    // Update payment proof in database
                    $sql = "UPDATE orders SET payment_proof = :payment_proof, payment_status = 'waiting' 
                            WHERE order_id = :order_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':payment_proof', $uploadResult['filename']);
                    $stmt->bindParam(':order_id', $order_id);
                    $stmt->execute();
                    
                    addTrackingHistory($conn, $order_id, 'Pembayaran Menunggu Konfirmasi', 
                                      'Bukti pembayaran telah diterima, menunggu verifikasi admin');
                    
                    setFlashMessage('success', 'Bukti pembayaran berhasil diunggah. Kami akan memverifikasi pembayaran Anda.');
                } else {
                    setFlashMessage('danger', $uploadResult['message']);
                }
            } else {
                setFlashMessage('danger', 'Harap upload bukti pembayaran.');
            }
        }
        else if ($order['payment_method'] == 'qris') {
            // For QRIS, payment is confirmed automatically
            updatePaymentStatus($conn, $order_id, 'paid');
            
            // Update tracking history
            addTrackingHistory($conn, $order_id, 'Pembayaran Berhasil', 'Pembayaran QRIS telah dikonfirmasi');
            
            // Auto assign courier after payment
            $courier = autoAssignCourier($conn, $order_id, '');
            if ($courier) {
                addTrackingHistory($conn, $order_id, 'Kurir Ditugaskan', 
                                 'Kurir: ' . $courier['courier_name'] . ' (' . $courier['courier_phone'] . ')');
            }
            
            setFlashMessage('success', 'Pembayaran berhasil dikonfirmasi.');
        }
    }
    
    redirect('order.php?tab=track&order_id=' . $order_id);
}

// Process subscription
if (isset($_POST['subscribe'])) {
    // Validate input
    $plan_id = (int)sanitize($_POST['plan_id']);
    $name = sanitize($_POST['sub_name']);
    $email = sanitize($_POST['sub_email']);
    $phone = sanitize($_POST['sub_phone']);
    $address = sanitize($_POST['sub_address']);
    $delivery_day = sanitize($_POST['delivery_day']);
    
    // Validate required fields
    if (empty($name) || empty($phone) || empty($address) || empty($delivery_day)) {
        setFlashMessage('danger', 'Semua field wajib harus diisi.');
        redirect('order.php?tab=subscription');
    }
    
    // Create customer
    $customerId = createCustomer($conn, $name, $email, $phone, $address);
    
    // Get subscription plan details
    $plans = getAllSubscriptionPlans($conn);
    $selectedPlan = null;
    
    foreach ($plans as $plan) {
        if ($plan['id'] == $plan_id) {
            $selectedPlan = $plan;
            break;
        }
    }
    
    if (!$selectedPlan) {
        setFlashMessage('danger', 'Paket berlangganan tidak valid.');
        redirect('order.php?tab=subscription');
    }
    
    // Create subscription order
    $sql = "INSERT INTO orders (customer_id, total_amount, payment_method, payment_status, notes, estimated_delivery, created_at) 
            VALUES (:customer_id, :total_amount, 'bank_transfer', 'pending', :notes, :estimated_delivery, NOW())";
    $stmt = $conn->prepare($sql);
    
    $notes = "Berlangganan: " . $selectedPlan['name'] . " - Pengiriman setiap " . $delivery_day;
    $estimatedDelivery = date('Y-m-d H:i:s', strtotime('+1 day'));
    
    $stmt->bindParam(':customer_id', $customerId);
    $stmt->bindParam(':total_amount', $selectedPlan['price']);
    $stmt->bindParam(':notes', $notes);
    $stmt->bindParam(':estimated_delivery', $estimatedDelivery);
    $stmt->execute();
    
    $orderId = $conn->lastInsertId();
    
    // Create initial tracking for subscription
    addTrackingHistory($conn, $orderId, 'Berlangganan Dibuat', 
                      'Paket ' . $selectedPlan['name'] . ' telah berhasil dibuat');
    
    setFlashMessage('success', 'Berlangganan berhasil dibuat. Silakan lakukan pembayaran untuk mengaktifkan.');
    redirect('order.php?tab=payment&order_id=' . $orderId);
}

// Process order tracking
$trackingData = null;
$trackingError = null;

if ($_SESSION['active_tab'] === 'track' && isset($_GET['order_id'])) {
    $orderId = $_GET['order_id'];
    
    // Remove # if present
    $orderId = str_replace('#', '', $orderId);
    
    // Convert to integer
    $orderId = (int)$orderId;
    
    if ($orderId > 0) {
        $trackingData = getOrderTracking($conn, $orderId);
        
        if (!$trackingData) {
            $trackingError = "Pesanan dengan nomor #" . str_pad($orderId, 6, '0', STR_PAD_LEFT) . " tidak ditemukan. Silakan periksa kembali nomor pesanan Anda.";
        }
    } else {
        $trackingError = "Nomor pesanan tidak valid. Silakan masukkan nomor pesanan yang benar.";
    }
}

// Get product list for selection
$products = getAllProducts($conn);

// Get selected product if any
$selectedProduct = null;
if (isset($_GET['product']) && !empty($_GET['product'])) {
    $selectedProduct = getProductById($conn, (int)$_GET['product']);
}

// Calculate cart total
$cartTotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartTotal += $item['subtotal'];
}

// Get subscription plans
$subscriptionPlans = getAllSubscriptionPlans($conn);

// Get active subscriptions for user
$activeSubscriptions = [];
if (isset($_SESSION['user_id'])) {
    $activeSubscriptions = getUserSubscriptions($conn, $_SESSION['user_id']);
}

// Get order history for user
$orderHistory = [];
if (isset($_SESSION['user_id'])) {
    $orderHistory = getUserOrderHistory($conn, $_SESSION['user_id']);
} else {
    // For non-logged in users, get recent orders
    $orderHistory = getUserOrderHistory($conn, null);
}

// Get payment information if coming from checkout
$paymentInfo = null;
if ($_SESSION['active_tab'] === 'payment' && isset($_GET['order_id'])) {
    $orderId = (int)$_GET['order_id'];
    if ($orderId > 0) {
        $paymentInfo = getOrderPaymentInfo($conn, $orderId);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan Air Minum - Aquanest</title>
    <!-- Bootstrap CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
   
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Tambahkan Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom CSS -->
    <link href="css/order.css" rel="stylesheet">
    <link href="css/navbar.css" rel="stylesheet">
   
</head>
<body class="bg-blue-50 min-h-screen pb-12">
    <!-- Navbar -->
    <?php 
        include 'includes/navbar.php';
    ?>
    
    <!-- Banner/Header -->
    <div class="relative bg-gradient-to-r from-blue-600 to-blue-900 text-white py-16 overflow-hidden">
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute -bottom-16 left-1/4 w-64 h-64 bg-blue-500 rounded-full opacity-20"></div>
            <div class="absolute -top-20 right-1/4 w-40 h-40 bg-blue-300 rounded-full opacity-20"></div>
            <div class="absolute top-1/2 left-3/4 w-24 h-24 bg-blue-200 rounded-full opacity-30"></div>
        </div>
        <div class="container mx-auto px-4 relative z-10 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">Pesan Air Minum Aquanest</h1>
            <p class="text-xl opacity-80 max-w-2xl mx-auto">Isi keranjang belanja Anda dan lakukan pemesanan dengan mudah. Air minum berkualitas diantar langsung ke rumah Anda.</p>
        </div>
    </div>
    
    <!-- Flash Messages -->
    <div class="container mx-auto px-4 mt-8">
        <?php if(isset($_SESSION['flash']) && $_SESSION['flash']['type'] == 'success'): ?>
        <div class="bg-green-50 border-l-4 border-green-500 rounded-lg p-4 flex items-center shadow-md">
            <div class="bg-green-500 rounded-full p-2 mr-4">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <div>
                <h4 class="font-semibold text-green-800">Berhasil!</h4>
                <p class="text-green-700"><?php echo htmlspecialchars($_SESSION['flash']['message']); ?></p>
            </div>
        </div>
        <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['flash']) && $_SESSION['flash']['type'] == 'danger'): ?>
        <div class="bg-red-50 border-l-4 border-red-500 rounded-lg p-4 flex items-center shadow-md">
            <div class="bg-red-500 rounded-full p-2 mr-4">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>
            <div>
                <h4 class="font-semibold text-red-800">Error!</h4>
                <p class="text-red-700"><?php echo htmlspecialchars($_SESSION['flash']['message']); ?></p>
            </div>
        </div>
        <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>
    </div>
    
    <!-- Navigation Tabs -->
    <div class="container mx-auto px-4 mt-8">
        <div class="bg-white rounded-t-xl shadow-lg overflow-hidden">
            <div class="flex flex-wrap border-b">
                <a href="order.php?tab=order" class="px-6 py-4 font-semibold text-center transition-colors <?php echo $_SESSION['active_tab'] === 'order' ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : 'text-gray-500 hover:text-blue-600 hover:bg-blue-50'; ?>">
                    <svg class="w-5 h-5 mr-2 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                    Pesan Produk
                </a>
                <a href="order.php?tab=track" class="px-6 py-4 font-semibold text-center transition-colors <?php echo $_SESSION['active_tab'] === 'track' ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : 'text-gray-500 hover:text-blue-600 hover:bg-blue-50'; ?>">
                    <svg class="w-5 h-5 mr-2 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                    </svg>
                    Lacak Pesanan
                </a>
                <a href="order.php?tab=payment" class="px-6 py-4 font-semibold text-center transition-colors <?php echo $_SESSION['active_tab'] === 'payment' ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : 'text-gray-500 hover:text-blue-600 hover:bg-blue-50'; ?>">
                    <svg class="w-5 h-5 mr-2 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                    Pembayaran
                </a>
                <a href="order.php?tab=subscription" class="px-6 py-4 font-semibold text-center transition-colors <?php echo $_SESSION['active_tab'] === 'subscription' ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : 'text-gray-500 hover:text-blue-600 hover:bg-blue-50'; ?>">
                    <svg class="w-5 h-5 mr-2 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Berlangganan
                </a>
                <a href="order.php?tab=history" class="px-6 py-4 font-semibold text-center transition-colors <?php echo $_SESSION['active_tab'] === 'history' ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : 'text-gray-500 hover:text-blue-600 hover:bg-blue-50'; ?>">
                    <svg class="w-5 h-5 mr-2 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Riwayat
                </a>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container mx-auto px-4 pb-8">
        
        <?php if ($_SESSION['active_tab'] === 'order'): ?>
        <!-- ORDER TAB CONTENT -->
        <div class="flex flex-col lg:flex-row gap-8 mt-6">
            <!-- Left Column -->
            <div class="w-full lg:w-1/3">
                <!-- Product Selection Card -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden transition-transform duration-300 hover:shadow-xl relative mb-8">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-6 py-4 text-white">
                        <h2 class="text-xl font-bold flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Tambah Produk
                        </h2>
                    </div>
                    <div class="p-6">
                        <form method="post" action="order.php" id="addToCartForm">
                            <div class="mb-4">
                                <label class="block text-gray-700 font-semibold mb-2" for="product_id">
                                    Pilih Produk
                                </label>
                                <select 
                                    class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:outline-none transition-all"
                                    id="product_id" 
                                    name="product_id" 
                                    required
                                >
                                    <option value="">-- Pilih Produk --</option>
                                    <?php foreach ($products as $product): ?>
                                        <option 
                                            value="<?php echo $product['product_id']; ?>" 
                                            data-price="<?php echo $product['price']; ?>"
                                            data-max="<?php echo $product['stock'] ?? 100; ?>"
                                            <?php echo ($selectedProduct && $selectedProduct['product_id'] == $product['product_id']) ? 'selected' : ''; ?>
                                        >
                                            <?php echo htmlspecialchars($product['name']); ?> - <?php echo formatRupiah($product['price']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-gray-700 font-semibold mb-2" for="quantity">
                                    Jumlah
                                </label>
                                <input 
                                    type="number" 
                                    class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:outline-none transition-all" 
                                    id="quantity" 
                                    name="quantity" 
                                    value="1" 
                                    min="1" 
                                    max="100" 
                                    required
                                >
                            </div>
                            
                            <div class="mb-6 p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg flex justify-between items-center">
                                <span class="font-semibold text-gray-700">Subtotal:</span>
                                <span id="subtotal" class="text-lg font-bold text-blue-800">Rp 0</span>
                            </div>
                            
                            <button 
                                type="submit" 
                                name="add_to_cart" 
                                class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold text-lg py-4 px-8 rounded-lg transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg flex items-center justify-center"
                            >
                                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                Tambah ke Keranjang
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Delivery Schedule Card -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8">
                    <div class="bg-gradient-to-r from-purple-600 to-purple-800 px-6 py-4 text-white">
                        <h2 class="text-xl font-bold flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Pilih Jadwal Pengiriman
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="mb-4">
                            <label class="block text-gray-700 font-semibold mb-2">
                                Tanggal Pengiriman
                            </label>
                            <input 
                                type="date" 
                                class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:outline-none transition-all"
                                id="delivery_date"
                                name="delivery_date"
                                min="<?php echo date('Y-m-d'); ?>"
                                value="<?php echo date('Y-m-d'); ?>"
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-semibold mb-2">
                                Waktu Pengiriman
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="p-3 border-2 rounded-lg text-center cursor-pointer transition-colors border-purple-500 bg-purple-50 text-purple-700" onclick="selectTimeSlot(this, '08:00 - 10:00')">
                                    <span class="font-medium">08:00 - 10:00</span>
                                </div>
                                <div class="p-3 border-2 rounded-lg text-center cursor-pointer transition-colors border-gray-200 hover:border-purple-200 hover:bg-purple-50" onclick="selectTimeSlot(this, '10:00 - 12:00')">
                                    <span class="font-medium">10:00 - 12:00</span>
                                </div>
                                <div class="p-3 border-2 rounded-lg text-center cursor-pointer transition-colors border-gray-200 hover:border-purple-200 hover:bg-purple-50" onclick="selectTimeSlot(this, '13:00 - 15:00')">
                                    <span class="font-medium">13:00 - 15:00</span>
                                </div>
                                <div class="p-3 border-2 rounded-lg text-center cursor-pointer transition-colors border-gray-200 hover:border-purple-200 hover:bg-purple-50" onclick="selectTimeSlot(this, '15:00 - 17:00')">
                                    <span class="font-medium">15:00 - 17:00</span>
                                </div>
                            </div>
                            <input type="hidden" id="delivery_time" name="delivery_time" value="08:00 - 10:00">
                        </div>
                        
                        <div class="p-3 bg-purple-50 border border-purple-100 rounded-lg mb-2">
                            <p class="text-sm text-purple-700">
                                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Pesanan sebelum pukul 15.00 akan dikirim di hari yang sama sesuai slot waktu yang tersedia.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Order Information Card -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-teal-500 to-teal-600 px-6 py-4 text-white">
                        <h2 class="text-xl font-bold flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Informasi Pemesanan
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="mb-4 flex">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 mr-4 flex-shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                                </svg>
                            </div>
                            <p class="text-gray-700">Pengiriman ke seluruh wilayah Bekasi</p>
                        </div>
                        
                        <div class="mb-4 flex">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 mr-4 flex-shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <p class="text-gray-700">Pemesanan sebelum pukul 15.00 akan dikirim di hari yang sama</p>
                        </div>
                        
                        <div class="mb-4 flex">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 mr-4 flex-shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <p class="text-gray-700">Pembayaran tunai saat pengiriman atau transfer bank</p>
                        </div>
                        
                        <div class="flex">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 mr-4 flex-shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                            </div>
                            <p class="text-gray-700">Bantuan pemesanan: 0812-3456-7890</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Cart & Checkout -->
            <div class="w-full lg:w-2/3">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4 text-white">
                        <h2 class="text-xl font-bold flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            Keranjang Belanja
                        </h2>
                    </div>
                    <div class="p-6">
                        <?php if (empty($_SESSION['cart'])): ?>
                            <div class="py-16 text-center">
                                <div class="w-24 h-24 bg-gray-100 rounded-full mx-auto flex items-center justify-center text-gray-400 mb-6">
                                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-xl font-semibold text-gray-700 mb-2">Keranjang belanja Anda kosong</h3>
                                <p class="text-gray-500 mb-6 max-w-md mx-auto">Silakan tambahkan produk ke keranjang untuk melanjutkan pemesanan</p>
                                <a href="products.php" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                                    </svg>
                                    Lihat Produk
                                </a>
                            </div>
                        <?php else: ?>
                            <form method="post" action="order.php">
                                <div class="overflow-x-auto">
                                    <table class="w-full">
                                        <thead>
                                            <tr class="bg-gray-50 text-left">
                                                <th class="px-4 py-4 font-semibold text-gray-700 rounded-tl-lg">Produk</th>
                                                <th class="px-4 py-4 font-semibold text-gray-700">Harga</th>
                                                <th class="px-4 py-4 font-semibold text-gray-700">Jumlah</th>
                                                <th class="px-4 py-4 font-semibold text-gray-700">Subtotal</th>
                                                <th class="px-4 py-4 font-semibold text-gray-700 rounded-tr-lg">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                                            <tr class="border-b border-gray-100">
                                                <td class="px-4 py-4">
                                                    <div class="flex items-center">
                                                        <div class="w-16 h-16 bg-gray-100 rounded-lg mr-3 flex items-center justify-center overflow-hidden">
                                                            <?php if (!empty($item['image']) && file_exists('img/products/' . $item['image'])): ?>
                                                                <img src="img/products/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="object-cover w-full h-full">
                                                            <?php else: ?>
                                                                <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white">
                                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                                                                    </svg>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <span class="font-medium text-gray-800"><?php echo htmlspecialchars($item['name']); ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-blue-600 font-medium">
                                                    <?php echo formatRupiah($item['price']); ?>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="flex items-center">
                                                        <button type="button" class="p-1 rounded-full bg-gray-200 text-gray-600 hover:bg-blue-500 hover:text-white" onclick="decrementQuantity(<?php echo $index; ?>)">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                                            </svg>
                                                        </button>
                                                        <input 
                                                            type="number" 
                                                            name="quantity[<?php echo $index; ?>]" 
                                                            id="cart_quantity_<?php echo $index; ?>"
                                                            class="w-14 mx-2 px-2 py-1 rounded border-2 border-gray-200 focus:border-blue-500 focus:outline-none transition-all text-center" 
                                                            value="<?php echo $item['quantity']; ?>" 
                                                            min="1" 
                                                            max="100"
                                                        >
                                                        <button type="button" class="p-1 rounded-full bg-gray-200 text-gray-600 hover:bg-blue-500 hover:text-white" onclick="incrementQuantity(<?php echo $index; ?>)">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 font-semibold text-blue-700">
                                                    <?php echo formatRupiah($item['subtotal']); ?>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <a href="order.php?remove=1&index=<?php echo $index; ?>" class="p-2 rounded-full bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition-colors inline-flex" onclick="return confirm('Apakah Anda yakin ingin menghapus produk ini?')">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="3" class="px-4 py-4 text-right font-bold text-gray-700">Total:</td>
                                                <td colspan="2" class="px-4 py-4 font-bold text-lg text-blue-700"><?php echo formatRupiah($cartTotal); ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                
                                <div class="flex flex-col sm:flex-row justify-between mt-6 gap-4">
                                    <a href="products.php" class="px-6 py-3 bg-white border-2 border-blue-600 text-blue-600 font-semibold rounded-lg hover:bg-blue-50 transition-colors flex items-center justify-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                        </svg>
                                        Lanjut Belanja
                                    </a>
                                    <button type="submit" name="update_cart" class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                        Perbarui Keranjang
                                    </button>
                                </div>
                            </form>
                            
                            <!-- Checkout Form -->
                            <form method="post" action="order.php" id="checkoutForm">
                                <div class="mt-12 pt-8 border-t-2 border-dashed border-gray-200 relative">
                                    <div class="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white px-4 text-gray-400 font-medium">
                                        INFORMASI PENGIRIMAN
                                    </div>
                                    
                                    <h3 class="text-2xl font-bold text-gray-800 mb-6">Data Pemesanan</h3>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-gray-700 font-semibold mb-2" for="name">
                                                Nama Lengkap <span class="text-red-500">*</span>
                                            </label>
                                            <input 
                                                type="text" 
                                                class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:outline-none transition-all" 
                                                id="name" 
                                                name="name" 
                                                placeholder="Masukkan nama lengkap" 
                                                required
                                            >
                                        </div>
                                        
                                        <div>
                                            <label class="block text-gray-700 font-semibold mb-2" for="email">
                                                Email
                                            </label>
                                            <input 
                                                type="email" 
                                                class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:outline-none transition-all" 
                                                id="email" 
                                                name="email" 
                                                placeholder="email@contoh.com"
                                            >
                                        </div>
                                        
                                        <div>
                                            <label class="block text-gray-700 font-semibold mb-2" for="phone">
                                                Nomor Telepon <span class="text-red-500">*</span>
                                            </label>
                                            <input
                                                type="tel" 
                                                class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:outline-none transition-all" 
                                                id="phone" 
                                                name="phone" 
                                                placeholder="081xxxxxxxxx" 
                                                pattern="[0-9]*" 
                                                title="Nomor telepon hanya boleh berisi angka"
                                                oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                                                required
                                            >
                                            <div class="text-sm text-gray-500 mt-1">Hanya masukkan angka (0-9)</div>
                                        </div>
                                    
                                        <div>
                                            <label class="block text-gray-700 font-semibold mb-2" for="payment_method">
                                                Metode Pembayaran <span class="text-red-500">*</span>
                                            </label>
                                            <select 
                                                class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:outline-none transition-all" 
                                                id="payment_method" 
                                                name="payment_method" 
                                                required
                                            >
                                                <option value="">-- Pilih Metode Pembayaran --</option>
                                                <option value="cod">💵 Bayar Tunai Saat Pengiriman (COD)</option>
                                                <option value="bank_transfer">🏦 Transfer Bank</option>
                                                <option value="qris">📱 QRIS (Semua E-wallet & Mobile Banking)</option>
                                            </select>
                                            <p class="text-sm text-gray-500 mt-1">Pilih metode pembayaran yang Anda inginkan</p>
                                        </div>

                                        <div class="md:col-span-2">
                                            <label class="block text-gray-700 font-semibold mb-2" for="address">
                                                Alamat Lengkap <span class="text-red-500">*</span>
                                            </label>
                                            <textarea 
                                                class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:outline-none transition-all" 
                                                id="address" 
                                                name="address" 
                                                rows="3" 
                                                placeholder="Masukkan alamat lengkap beserta kode pos" 
                                                required
                                            ></textarea>
                                            <p class="text-sm text-gray-500 mt-1">Mohon masukkan alamat lengkap beserta kode pos untuk mempermudah pengiriman.</p>
                                        </div>
                                        
                                        <div class="md:col-span-2">
                                            <label class="block text-gray-700 font-semibold mb-2" for="notes">
                                                Catatan Tambahan
                                            </label>
                                            <textarea 
                                                class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:outline-none transition-all" 
                                                id="notes" 
                                                name="notes" 
                                                rows="2" 
                                                placeholder="Catatan tambahan untuk pengiriman (opsional)"
                                            ></textarea>
                                        </div>
                                    </div>
                                    
                                    <!-- Hidden inputs for delivery info -->
                                    <input type="hidden" name="delivery_date" id="checkout_delivery_date">
                                    <input type="hidden" name="delivery_time" id="checkout_delivery_time">
                                    
                                    <div class="mt-8">
                                        <div class="flex items-center mb-6">
                                            <input 
                                                type="checkbox" 
                                                id="terms" 
                                                class="w-5 h-5 text-blue-600 rounded border-gray-300" 
                                                required
                                            >
                                            <label for="terms" class="ml-2 text-gray-700">
                                                Saya menyetujui <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal" class="text-blue-600 font-semibold hover:underline">syarat dan ketentuan</a> yang berlaku
                                            </label>
                                        </div>
                                        
                                        <button 
                                            type="submit" 
                                            name="checkout" 
                                            class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-bold text-lg py-4 px-8 rounded-lg transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg flex items-center justify-center"
                                        >
                                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Selesaikan Pemesanan
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($_SESSION['active_tab'] === 'track'): ?>
        <!-- TRACK ORDER TAB CONTENT -->
        <div class="mt-6">
            <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-6 py-4 text-white">
                    <h2 class="text-xl font-bold flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                        </svg>
                        Lacak Pesanan
                    </h2>
                </div>
                
                <div class="p-6">
                    <!-- Tracking Lookup Form -->
                    <?php if (!$trackingData): ?>
                    <div class="mb-8">
                        <p class="text-gray-600 mb-4">Masukkan nomor pesanan Anda untuk melacak status pengiriman.</p>
                        <form method="get" action="order.php" class="flex flex-col sm:flex-row gap-3">
                            <input type="hidden" name="tab" value="track">
                            <input 
                                type="text" 
                                name="order_id"
                                class="flex-1 px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:outline-none transition-all" 
                                placeholder="Contoh: 1"
                                required
                            >
                            <button 
                                type="submit" 
                                class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center whitespace-nowrap"
                            >
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                Lacak Pesanan
                            </button>
                        </form>
                    </div>
                    
                    <!-- Order History QuickView -->
                    <div class="mt-8">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Pesanan Terakhir</h3>
                        <?php if (empty($orderHistory)): ?>
                        <p class="text-gray-500">Belum ada riwayat pesanan.</p>
                        <?php else: ?>
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">No. Pesanan</th>
                                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Tanggal</th>
                                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Total</th>
                                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Status</th>
                                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orderHistory as $order): ?>
                                        <tr class="border-t border-gray-200">
                                            <td class="px-4 py-3 text-sm font-medium text-gray-700"><?php echo htmlspecialchars($order['id']); ?></td>
                                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($order['date']); ?></td>
                                            <td class="px-4 py-3 text-sm text-gray-900 font-medium"><?php echo formatRupiah($order['total']); ?></td>
                                            <td class="px-4 py-3">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php 
                                                    if ($order['status'] === 'Selesai') echo 'bg-green-100 text-green-800';
                                                    else if ($order['status'] === 'Dalam Pengiriman') echo 'bg-blue-100 text-blue-800';
                                                    else echo 'bg-yellow-100 text-yellow-800';
                                                ?>">
                                                    <?php echo htmlspecialchars($order['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <a 
                                                    href="order.php?tab=track&order_id=<?php echo $order['order_id'] ?? $order['id']; ?>" 
                                                    class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                                                >
                                                    Lacak
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <!-- Tracking Results -->
                    <div class="bg-blue-50 rounded-xl p-6 mb-8">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                            <div>
                                <p class="text-gray-500 text-sm">Nomor Pesanan</p>
                                <h3 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($trackingData['order_id']); ?></h3>
                            </div>
                            <div class="mt-4 md:mt-0">
                                <span class="px-4 py-2 bg-blue-100 text-blue-800 rounded-full font-semibold inline-flex items-center">
                                    <span class="w-2 h-2 bg-blue-600 rounded-full mr-2"></span>
                                    <?php echo htmlspecialchars($trackingData['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="flex flex-col md:flex-row gap-6 mb-6">
                            <div class="flex-1">
                                <p class="text-gray-500 text-sm">Waktu Pemesanan</p>
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($trackingData['order_date']); ?></p>
                            </div>
                            <div class="flex-1">
                                <p class="text-gray-500 text-sm">Estimasi Tiba</p>
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($trackingData['estimated_arrival']); ?></p>
                            </div>
                            <div class="flex-1">
                                <p class="text-gray-500 text-sm">Status Pembayaran</p>
                                <p class="font-semibold text-green-600"><?php echo htmlspecialchars($trackingData['payment_status']); ?></p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div>
                                <p class="text-gray-500 text-sm mb-1">Informasi Penerima</p>
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($trackingData['customer_name']); ?></p>
                                <p class="text-gray-600"><?php echo htmlspecialchars($trackingData['address']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm mb-1">Rincian Pesanan</p>
                                <ul class="space-y-2">
                                    <?php foreach ($trackingData['items'] as $item): ?>
                                    <li class="flex justify-between text-gray-600">
                                        <span><?php echo htmlspecialchars($item['name']); ?> x<?php echo (int)$item['quantity']; ?></span>
                                        <span><?php echo formatRupiah($item['price'] * $item['quantity']); ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                    <li class="flex justify-between font-bold text-gray-800 pt-2 border-t border-gray-200">
                                        <span>Total</span>
                                        <span><?php echo formatRupiah($trackingData['total_amount']); ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Courier Info - Live Tracking -->
                        <div class="bg-white rounded-xl p-5 shadow-sm border border-blue-100 mb-8">
                            <h4 class="text-lg font-bold text-gray-800 mb-4">Informasi Pengiriman</h4>
                            <div class="flex flex-col md:flex-row gap-6">
                                <div class="flex md:w-1/3">
                                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 mr-4 flex-shrink-0">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-sm">Kurir</p>
                                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($trackingData['courier_info']['name']); ?></p>
                                        <p class="text-blue-600 font-medium text-sm"><?php echo htmlspecialchars($trackingData['courier_info']['phone']); ?></p>
                                    </div>
                                </div>
                                <div class="md:w-2/3">
                                    <p class="text-gray-500 text-sm">Lokasi Saat Ini</p>
                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($trackingData['courier_info']['current_location']); ?></p>
                                    <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($trackingData['courier_info']['vehicle_info']); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tracking Timeline -->
                        <h4 class="text-lg font-bold text-gray-800 mb-4">Status Pengiriman</h4>
                        <div class="space-y-3 relative">
                            <div class="absolute top-0 bottom-0 left-4 w-0.5 bg-gray-200 transform translate-x-0.5"></div>
                            
                            <?php foreach ($trackingData['tracking_steps'] as $index => $step): ?>
                            <div class="relative flex <?php echo $step['completed'] ? '' : 'opacity-60'; ?>">
                                <div class="w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center z-10 
                                            <?php if (isset($step['current']) && $step['current']): ?>
                                                bg-blue-600 text-white ring-2 ring-blue-200 ring-offset-2
                                            <?php elseif ($step['completed']): ?>
                                                bg-green-500 text-white
                                            <?php else: ?>
                                                bg-gray-200 text-gray-500
                                            <?php endif; ?>">
                                    <?php if ($step['completed']): ?>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    <?php else: ?>
                                        <span class="text-sm font-bold"><?php echo $index + 1; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="ml-4 pb-5">
                                    <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($step['title']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($step['time']); ?></div>
                                    <div class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($step['description']); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Call to Action -->
                    <div class="flex flex-col md:flex-row gap-4 justify-between items-center">
                        <a href="#" class="px-6 py-3 bg-white border-2 border-blue-600 text-blue-600 font-semibold rounded-lg hover:bg-blue-50 transition-colors flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                            </svg>
                            Unduh Rincian Pesanan
                        </a>
                        
                        <a href="tel:<?php echo htmlspecialchars($trackingData['courier_info']['phone']); ?>" class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                            </svg>
                            Hubungi Kurir
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($_SESSION['active_tab'] === 'payment'): ?>
        <!-- PAYMENT TAB CONTENT -->
        <div class="mt-6">
            <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-6 py-4 text-white">
                    <h2 class="text-xl font-bold flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                        Pembayaran Pesanan
                    </h2>
                </div>
                
                <div class="p-6">
                    <?php if ($paymentInfo): ?>
                    <!-- Order Summary -->
                    <div class="mb-8">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Ringkasan Pesanan</h3>
                        <div class="bg-blue-50 rounded-xl p-5">
                            <div class="flex justify-between items-center py-3 border-b border-blue-100">
                                <span class="text-gray-600">Nomor Pesanan</span>
                                <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($paymentInfo['id']); ?></span>
                            </div>
                            <div class="flex justify-between items-center py-3 border-b border-blue-100">
                                <span class="text-gray-600">Nama Pemesan</span>
                                <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($paymentInfo['customer']); ?></span>
                            </div>
                            <div class="flex justify-between items-center py-3 border-b border-blue-100">
                                <span class="text-gray-600">Waktu Pemesanan</span>
                                <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($paymentInfo['created_at']); ?></span>
                            </div>
                            <div class="flex justify-between items-center py-3 border-b border-blue-100">
                                <span class="text-gray-600">Metode Pembayaran</span>
                                <span class="font-semibold text-gray-800">
                                    <?php 
                                    if ($paymentInfo['payment_method'] == 'bank_transfer') echo 'Transfer Bank';
                                    else if ($paymentInfo['payment_method'] == 'qris') echo 'QRIS';
                                    else if ($paymentInfo['payment_method'] == 'cod') echo 'Bayar Saat Pengiriman (COD)';
                                    ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center py-3 border-b border-blue-100">
                                <span class="text-gray-600">Status Pembayaran</span>
                                <span class="font-semibold text-orange-600"><?php echo htmlspecialchars($paymentInfo['payment_status']); ?></span>
                            </div>
                            <div class="flex justify-between items-center py-3 border-b border-blue-100">
                                <span class="text-gray-600">Batas Waktu Pembayaran</span>
                                <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($paymentInfo['expiry_time']); ?></span>
                            </div>
                            <div class="flex justify-between items-center py-3">
                                <span class="font-bold text-lg text-gray-800">Total Pembayaran</span>
                                <span class="font-bold text-lg text-blue-700" id="totalAmount" data-amount="<?php echo $paymentInfo['total']; ?>"><?php echo formatRupiah($paymentInfo['total']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Instructions based on selected method -->
                    <?php if ($paymentInfo['payment_method'] == 'qris'): ?>
                    <!-- QRIS Payment -->
                    <div class="mb-8">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Instruksi Pembayaran QRIS</h3>
                        <div class="border-2 border-blue-500 bg-blue-50 rounded-xl p-6">
                            <div class="text-center mb-6">
                                <div class="inline-block p-6 bg-white rounded-xl shadow-md">
                                    <!-- QR Code dengan API QR Generator -->
                                    <div class="mb-4">
                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?php echo urlencode('00020101021126570011ID.DANA.WWW011893600914300021565302150302702030950405500015303360540' . str_replace(['Rp ', '.', ','], '', formatRupiah($paymentInfo['total'])) . '5502015802ID5920AQUANEST6004JAKARTA61051040062070703A016304B1F5'); ?>" 
                                             alt="QRIS Payment QR Code" 
                                             class="mx-auto border-2 border-gray-200 rounded-lg shadow-sm"
                                             style="width: 300px; height: 300px;">
                                    </div>
                                    <p class="text-gray-800 font-medium mb-2">Scan QR Code untuk Pembayaran</p>
                                    <div class="bg-gray-100 rounded-lg px-4 py-2">
                                        <p class="text-sm text-gray-600">Total Pembayaran</p>
                                        <p class="text-lg font-bold text-gray-800"><?php echo formatRupiah($paymentInfo['total']); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-sm text-gray-700">
                                <p class="mb-2 font-semibold">Cara Pembayaran:</p>
                                <ol class="list-decimal ml-4 space-y-1">
                                    <li>Buka aplikasi e-wallet atau mobile banking Anda</li>
                                    <li>Pilih menu Scan QR atau QRIS</li>
                                    <li>Scan QR code di atas</li>
                                    <li>Periksa detail transaksi dan lakukan pembayaran</li>
                                    <li>Pembayaran akan dikonfirmasi secara otomatis dalam 5-10 menit</li>
                                </ol>
                            </div>
                            
                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <span class="text-sm text-gray-600">Dapat dibayar menggunakan:</span>
                                <span class="px-2 py-1 bg-white rounded text-xs text-gray-700 border border-gray-200">GoPay</span>
                                <span class="px-2 py-1 bg-white rounded text-xs text-gray-700 border border-gray-200">OVO</span>
                                <span class="px-2 py-1 bg-white rounded text-xs text-gray-700 border border-gray-200">DANA</span>
                                <span class="px-2 py-1 bg-white rounded text-xs text-gray-700 border border-gray-200">LinkAja</span>
                                <span class="px-2 py-1 bg-white rounded text-xs text-gray-700 border border-gray-200">ShopeePay</span>
                                <span class="px-2 py-1 bg-white rounded text-xs text-gray-700 border border-gray-200">Mobile Banking</span>
                            </div>
                            
                            <!-- Form untuk konfirmasi pembayaran -->
                            <form method="post" action="order.php" class="mt-6">
                                <input type="hidden" name="order_id" value="<?php echo $_GET['order_id'] ?? 0; ?>">
                                
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                                    <p class="text-sm text-yellow-800">
                                        <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Setelah pembayaran berhasil, klik tombol di bawah untuk konfirmasi
                                    </p>
                                </div>
                                
                                <button 
                                    type="submit" 
                                    name="confirm_payment" 
                                    class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-bold text-lg py-4 px-8 rounded-lg transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg flex items-center justify-center"
                                >
                                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Saya Sudah Membayar
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <?php elseif ($paymentInfo['payment_method'] == 'bank_transfer'): ?>
                    <!-- Bank Transfer Payment -->
                    <div class="mb-8">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Instruksi Transfer Bank</h3>
                        <div class="border-2 border-blue-500 bg-blue-50 rounded-xl p-6">
                            <div class="bg-white rounded-lg p-4 mb-4">
                                <table class="w-full text-sm">
                                    <tbody>
                                        <tr class="border-b border-gray-100">
                                            <td class="py-3 text-gray-600">Bank</td>
                                            <td class="py-3 font-medium text-gray-800">Bank Central Asia (BCA)</td>
                                        </tr>
                                        <tr class="border-b border-gray-100">
                                            <td class="py-3 text-gray-600">Nomor Rekening</td>
                                            <td class="py-3 font-medium text-gray-800 flex items-center">
                                                <span class="font-mono">1234567890</span>
                                                <button type="button" class="ml-2 text-blue-600 hover:text-blue-800" onclick="copyToClipboard('1234567890')">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr class="border-b border-gray-100">
                                            <td class="py-3 text-gray-600">Atas Nama</td>
                                            <td class="py-3 font-medium text-gray-800">PT Aquanest Indonesia</td>
                                        </tr>
                                        <tr>
                                            <td class="py-3 text-gray-600">Total Transfer</td>
                                            <td class="py-3 font-bold text-lg text-blue-700"><?php echo formatRupiah($paymentInfo['total']); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="text-sm text-gray-700 mb-4">
                                <p class="mb-2 font-semibold">Cara Pembayaran:</p>
                                <ol class="list-decimal ml-4 space-y-1">
                                    <li>Transfer sesuai nominal di atas ke rekening yang tertera</li>
                                    <li>Simpan bukti transfer</li>
                                    <li>Upload bukti transfer di form di bawah</li>
                                    <li>Tunggu konfirmasi dari kami (maks. 24 jam)</li>
                                </ol>
                            </div>
                            
                            <form method="post" action="order.php" enctype="multipart/form-data">
                                <input type="hidden" name="order_id" value="<?php echo $_GET['order_id'] ?? 0; ?>">
                                
                                <div class="mb-4">
                                    <label class="block text-gray-700 font-semibold mb-2">Unggah Bukti Transfer <span class="text-red-500">*</span></label>
                                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-blue-400 transition-colors">
                                        <input type="file" name="payment_proof" id="payment_proof" class="hidden" accept="image/jpeg,image/png,image/jpg,application/pdf" required>
                                        <label for="payment_proof" class="cursor-pointer block">
                                            <svg class="w-10 h-10 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                            </svg>
                                            <p class="text-sm text-gray-600 mb-1">Seret dan lepas file di sini atau</p>
                                            <span class="text-blue-600 font-medium text-sm">Pilih File</span>
                                            <p class="text-xs text-gray-500 mt-1">JPG, PNG, atau PDF (maks. 2MB)</p>
                                        </label>
                                    </div>
                                    <div id="file-selected" class="hidden mt-2 p-2 bg-blue-50 rounded text-sm"></div>
                                </div>
                                
                                <button 
                                    type="submit" 
                                    name="confirm_payment" 
                                    class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-bold text-lg py-4 px-8 rounded-lg transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg flex items-center justify-center"
                                >
                                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                    Konfirmasi Pembayaran
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <?php elseif ($paymentInfo['payment_method'] == 'cod'): ?>
                    <!-- Cash on Delivery Payment -->
                    <div class="mb-8">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Pembayaran Tunai (COD)</h3>
                        <div class="border-2 border-green-500 bg-green-50 rounded-xl p-6">
                            <div class="text-center mb-6">
                                <div class="inline-block p-6 bg-white rounded-xl shadow-md">
                                    <div class="w-32 h-32 bg-green-100 rounded-full flex items-center justify-center text-green-600 mx-auto mb-4">
                                        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                    </div>
                                    <p class="text-gray-800 font-medium mb-2">Siapkan Uang Tunai</p>
                                    <div class="bg-green-100 rounded-lg px-4 py-2">
                                        <p class="text-sm text-gray-600">Total yang harus dibayar</p>
                                        <p class="text-2xl font-bold text-green-700"><?php echo formatRupiah($paymentInfo['total']); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-white rounded-lg p-4 mb-4">
                                <h4 class="font-semibold text-gray-800 mb-3">Kalkulator Kembalian</h4>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm text-gray-600 mb-1">Uang yang akan dibayarkan</label>
                                        <input 
                                            type="text" 
                                            id="cashAmount" 
                                            class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-green-500 focus:outline-none transition-all text-lg font-semibold"
                                            placeholder="Contoh: 100000"
                                            oninput="calculateChange()"
                                        >
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-gray-600">Total Belanja</span>
                                            <span class="font-medium"><?php echo formatRupiah($paymentInfo['total']); ?></span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-600">Kembalian</span>
                                            <span class="font-bold text-lg text-green-600" id="changeAmount">Rp 0</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-sm text-gray-700">
                                <p class="mb-2 font-semibold">Informasi Penting:</p>
                                <ul class="list-disc ml-4 space-y-1">
                                    <li>Mohon siapkan uang pas atau sedekat mungkin dengan nominal</li>
                                    <li>Kurir membawa kembalian maksimal Rp 50.000</li>
                                    <li>Pembayaran dilakukan saat kurir tiba di lokasi Anda</li>
                                    <li>Pastikan ada orang di rumah saat waktu pengiriman</li>
                                    <li>Kurir akan menghubungi Anda 30 menit sebelum tiba</li>
                                </ul>
                            </div>
                            
                            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <div>
                                        <p class="font-medium text-yellow-800">Tips untuk Pembayaran COD:</p>
                                        <ul class="text-sm text-yellow-700 mt-1 list-disc ml-4">
                                            <li>Periksa produk sebelum membayar</li>
                                            <li>Minta struk/nota dari kurir</li>
                                            <li>Simpan bukti pembayaran</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Button ke tracking -->
                            <div class="mt-6">
                                <a href="order.php?tab=track&order_id=<?php echo $_GET['order_id'] ?? 0; ?>" 
                                   class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold text-lg py-4 px-8 rounded-lg transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                                    </svg>
                                    Lacak Pesanan Saya
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="text-center">
                        <p class="text-sm text-gray-500">Dengan melakukan pembayaran, Anda menyetujui <a href="#" class="text-blue-600 hover:underline">Syarat dan Ketentuan</a> serta <a href="#" class="text-blue-600 hover:underline">Kebijakan Privasi</a> Aquanest.</p>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-16">
                        <div class="w-24 h-24 bg-gray-100 rounded-full mx-auto flex items-center justify-center text-gray-400 mb-6">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-700 mb-2">Tidak Ada Pembayaran Tertunda</h3>
                        <p class="text-gray-500 mb-6 max-w-md mx-auto">Silakan lakukan pemesanan terlebih dahulu atau lacak status pesanan Anda di tab Lacak Pesanan.</p>
                        <div class="flex flex-col sm:flex-row justify-center gap-4">
                            <a href="order.php?tab=order" class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                                Buat Pesanan Baru
                            </a>
                            <a href="order.php?tab=track" class="px-6 py-3 bg-white border-2 border-blue-600 text-blue-600 font-semibold rounded-lg hover:bg-blue-50 transition-colors flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                                </svg>
                                Lacak Pesanan
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($_SESSION['active_tab'] === 'subscription'): ?>
        <!-- SUBSCRIPTION TAB CONTENT -->
        <div class="mt-6">
            <div class="max-w-4xl mx-auto">
                <!-- Subscription Plans -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8">
                    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 text-white">
                        <h2 class="text-xl font-bold flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Paket Berlangganan
                        </h2>
                    </div>
                    
                    <div class="p-6">
                        <p class="text-gray-600 mb-8">Berlangganan air minum Aquanest dan nikmati pengiriman rutin dengan harga lebih hemat. Kami akan mengirimkan produk secara berkala sesuai jadwal yang Anda pilih.</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <?php foreach ($subscriptionPlans as $plan): ?>
                            <div class="border-2 rounded-xl overflow-hidden transition-all <?php echo $plan['popular'] ? 'border-purple-500 shadow-lg transform md:-translate-y-2' : 'border-gray-200 hover:border-purple-200'; ?>">
                                <?php if ($plan['popular']): ?>
                                <div class="bg-purple-500 text-white text-center py-1 text-sm font-medium">
                                    Paling Populer
                                </div>
                                <?php endif; ?>
                                <div class="p-5">
                                    <h3 class="text-xl font-bold text-gray-800 mb-1"><?php echo htmlspecialchars($plan['name']); ?></h3>
                                    <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($plan['description']); ?></p>
                                    <div class="mb-4">
                                        <span class="text-3xl font-bold text-gray-900"><?php echo formatRupiah($plan['price']); ?></span>
                                        <span class="text-gray-500">/<?php echo strtolower($plan['duration']); ?></span>
                                    </div>
                                    <div class="space-y-2 mb-6">
                                        <div class="flex items-center text-sm text-gray-600">
                                            <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Pengiriman terjadwal
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <?php echo $plan['delivery_count']; ?> kali pengiriman
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Hemat <?php echo $plan['discount']; ?>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Bisa dibatalkan kapan saja
                                        </div>
                                    </div>
                                    <button 
                                        class="w-full py-3 px-4 rounded-lg font-semibold transition-colors <?php echo $plan['popular'] ? 'bg-purple-600 hover:bg-purple-700 text-white' : 'bg-white border-2 border-purple-500 text-purple-600 hover:bg-purple-50'; ?>"
                                        onclick="selectSubscriptionPlan(<?php echo $plan['id']; ?>, '<?php echo htmlspecialchars($plan['name']); ?>')"
                                    >
                                        Pilih Paket
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-8 p-4 bg-blue-50 rounded-lg border border-blue-100">
                            <h4 class="font-semibold text-blue-800 mb-2 flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Keuntungan Berlangganan
                            </h4>
                            <ul class="space-y-2 text-sm text-blue-700">
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span>Tak perlu repot memesan berulang-ulang, air minum akan datang secara otomatis</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span>Hemat hingga 20% dari harga normal</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span>Prioritas pengiriman, pelanggan berlangganan akan didahulukan</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span>Fleksibel - Anda dapat mengubah jadwal atau membatalkan kapan saja</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Subscription Form -->
                <div id="subscriptionForm" class="bg-white rounded-xl shadow-lg overflow-hidden mb-8 hidden">
                    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 text-white">
                        <h2 class="text-xl font-bold flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span id="subscriptionFormTitle">Berlangganan Paket</span>
                        </h2>
                    </div>
                    
                    <div class="p-6">
                        <form method="post" action="order.php">
                            <input type="hidden" name="plan_id" id="plan_id" value="">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2" for="sub_name">
                                        Nama Lengkap <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:outline-none transition-all" 
                                        id="sub_name" 
                                        name="sub_name" 
                                        placeholder="Masukkan nama lengkap" 
                                        required
                                    >
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2" for="sub_email">
                                        Email
                                    </label>
                                    <input 
                                        type="email" 
                                        class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:outline-none transition-all" 
                                        id="sub_email" 
                                        name="sub_email" 
                                        placeholder="email@contoh.com"
                                    >
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2" for="sub_phone">
                                        Nomor Telepon <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="tel" 
                                        class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:outline-none transition-all" 
                                        id="sub_phone" 
                                        name="sub_phone" 
                                        placeholder="081xxxxxxxxx" 
                                        required
                                    >
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2" for="delivery_day">
                                        Pilih Hari Pengiriman <span class="text-red-500">*</span>
                                    </label>
                                    <select 
                                        class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:outline-none transition-all" 
                                        id="delivery_day" 
                                        name="delivery_day" 
                                        required
                                    >
                                        <option value="">-- Pilih Hari --</option>
                                        <option value="Senin">Senin</option>
                                        <option value="Selasa">Selasa</option>
                                        <option value="Rabu">Rabu</option>
                                        <option value="Kamis">Kamis</option>
                                        <option value="Jumat">Jumat</option>
                                        <option value="Sabtu">Sabtu</option>
                                    </select>
                                </div>
                                
                                <div class="md:col-span-2">
                                    <label class="block text-gray-700 font-semibold mb-2" for="sub_address">
                                        Alamat Lengkap <span class="text-red-500">*</span>
                                    </label>
                                    <textarea 
                                        class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:outline-none transition-all" 
                                        id="sub_address" 
                                        name="sub_address" 
                                        rows="3" 
                                        placeholder="Masukkan alamat lengkap beserta kode pos" 
                                        required
                                    ></textarea>
                                    <p class="text-sm text-gray-500 mt-1">Mohon masukkan alamat lengkap beserta kode pos untuk mempermudah pengiriman.</p>
                                </div>
                            </div>
                            
                            <div class="mt-8">
                                <div class="p-4 bg-purple-50 border border-purple-100 rounded-lg mb-6">
                                    <h4 class="font-semibold text-purple-800 mb-2">Ringkasan Berlangganan</h4>
                                    <div class="flex justify-between mb-2">
                                        <span class="text-gray-600">Paket</span>
                                        <span class="font-medium text-gray-800" id="summary_package">-</span>
                                    </div>
                                    <div class="flex justify-between mb-2">
                                        <span class="text-gray-600">Frekuensi Pengiriman</span>
                                        <span class="font-medium text-gray-800" id="summary_frequency">-</span>
                                    </div>
                                    <div class="flex justify-between mb-2">
                                        <span class="text-gray-600">Hari Pengiriman</span>
                                        <span class="font-medium text-gray-800" id="summary_day">-</span>
                                    </div>
                                    <div class="flex justify-between mt-4 pt-4 border-t border-purple-100">
                                        <span class="font-bold text-gray-800">Total Biaya</span>
                                        <span class="font-bold text-purple-700" id="summary_total">-</span>
                                    </div>
                                </div>
                                
                                <div class="flex items-center mb-6">
                                    <input 
                                        type="checkbox" 
                                        id="sub_terms" 
                                        class="w-5 h-5 text-blue-600 rounded border-gray-300" 
                                        required
                                    >
                                    <label for="sub_terms" class="ml-2 text-gray-700">
                                        Saya menyetujui <a href="#" class="text-blue-600 font-semibold hover:underline">syarat dan ketentuan berlangganan</a> yang berlaku
                                    </label>
                                </div>
                                
                                <button 
                                    type="submit" 
                                    name="subscribe" 
                                    class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-bold text-lg py-4 px-8 rounded-lg transition-all duration-300 transform hover:-translate-y-1 hover:shadow>
                                    <button 
                                    type="submit" 
                                    name="subscribe" 
                                    class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-bold text-lg py-4 px-8 rounded-lg transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg flex items-center justify-center"
                                >
                                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Mulai Berlangganan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Active Subscriptions -->
                <?php if (!empty($activeSubscriptions)): ?>
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4 text-white">
                        <h2 class="text-xl font-bold flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Berlangganan Aktif
                        </h2>
                    </div>
                    
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Paket</th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Jadwal</th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Pengiriman Berikutnya</th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Status</th>
                                        <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeSubscriptions as $subscription): ?>
                                    <tr class="border-t border-gray-200">
                                        <td class="px-4 py-3 text-sm text-gray-700"><?php echo $subscription['plan_name']; ?></td>
                                        <td class="px-4 py-3 text-sm text-gray-600">Setiap <?php echo $subscription['delivery_day']; ?></td>
                                        <td class="px-4 py-3 text-sm text-gray-600"><?php echo $subscription['next_delivery']; ?></td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                                Aktif
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button class="text-red-600 hover:text-red-800 text-sm font-medium">
                                                Batalkan
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
         <?php if ($_SESSION['active_tab'] === 'history'): ?>
        <!-- ORDER HISTORY TAB CONTENT -->
        <div class="mt-6">
            <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-6 py-4 text-white">
                    <h2 class="text-xl font-bold flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Riwayat Pesanan
                    </h2>
                </div>
                
                <div class="p-6">
                    <?php if (empty($orderHistory)): ?>
                    <div class="py-16 text-center">
                        <div class="w-24 h-24 bg-gray-100 rounded-full mx-auto flex items-center justify-center text-gray-400 mb-6">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-700 mb-2">Belum ada riwayat pesanan</h3>
                        <p class="text-gray-500 mb-6 max-w-md mx-auto">Anda belum pernah melakukan pemesanan. Silakan pesan produk untuk melihat riwayat pesanan Anda di sini.</p>
                        <a href="order.php?tab=order" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                            </svg>
                            Pesan Sekarang
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="mb-4">
                        <div class="flex justify-between items-center">
                            <h3 class="text-xl font-bold text-gray-800">Pesanan Terbaru</h3>
                            <div class="flex gap-2">
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        class="pl-9 pr-4 py-2 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:outline-none transition-all" 
                                        placeholder="Cari pesanan..."
                                    >
                                    <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <select class="px-4 py-2 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:outline-none transition-all text-sm">
                                    <option value="all">Semua Status</option>
                                    <option value="pending">Menunggu Pembayaran</option>
                                    <option value="processing">Diproses</option>
                                    <option value="shipping">Dalam Pengiriman</option>
                                    <option value="completed">Selesai</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-6">
                        <?php foreach ($orderHistory as $order): ?>
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <div class="flex justify-between items-center bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <div>
                                    <span class="font-medium text-gray-600">Nomor Pesanan:</span>
                                    <span class="font-bold text-gray-800 ml-1"><?php echo $order['id']; ?></span>
                                </div>
                                <div class="flex gap-3 items-center">
                                    <span class="text-sm text-gray-500"><?php echo $order['date']; ?></span>
                                    <span class="px-3 py-1 text-xs font-medium rounded-full <?php 
                                        if ($order['status'] === 'Selesai') echo 'bg-green-100 text-green-800';
                                        else if ($order['status'] === 'Dalam Pengiriman') echo 'bg-blue-100 text-blue-800';
                                        else echo 'bg-yellow-100 text-yellow-800';
                                    ?>">
                                        <?php echo $order['status']; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="p-4">
                                <div class="border-b border-gray-100 pb-4 mb-4">
                                    <?php foreach ($order['items'] as $item): ?>
                                    <div class="flex justify-between items-center py-2">
                                        <div class="flex items-center">
                                            <div class="w-12 h-12 bg-gray-100 rounded-lg mr-3 flex items-center justify-center text-gray-400">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-800"><?php echo $item['name']; ?></p>
                                                <p class="text-sm text-gray-500"><?php echo $item['quantity']; ?> x <?php echo formatRupiah($item['price']); ?></p>
                                            </div>
                                        </div>
                                        <div class="font-semibold text-gray-800">
                                            <?php echo formatRupiah($item['price'] * $item['quantity']); ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="flex justify-between items-center">
                                    <div>
                                        <span class="font-medium text-gray-600">Total:</span>
                                        <span class="font-bold text-lg text-blue-700 ml-1"><?php echo formatRupiah($order['total']); ?></span>
                                    </div>
                                    <div class="flex gap-2">
                                        <a href="order.php?tab=track&order_id=<?php echo $order['id']; ?>" class="px-4 py-2 bg-blue-600 text-white font-medium text-sm rounded-lg hover:bg-blue-700 transition-colors">
                                            Lacak Pesanan
                                        </a>
                                        <button class="px-4 py-2 bg-white border-2 border-blue-600 text-blue-600 font-medium text-sm rounded-lg hover:bg-blue-50 transition-colors">
                                            Detail Pesanan
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

   
    
    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="termsModalLabel">Syarat dan Ketentuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Ketentuan Umum</h6>
                    <p>Dengan melakukan pemesanan di Aquanest, Anda menyetujui untuk terikat dengan syarat dan ketentuan yang berlaku.</p>
                    
                    <h6>2. Pemesanan dan Pembayaran</h6>
                    <p>Pemesanan dianggap sah setelah pembayaran dikonfirmasi. Pembayaran dapat dilakukan melalui transfer bank atau bayar di tempat.</p>
                    
                    <h6>3. Pengiriman</h6>
                    <p>Pengiriman dilakukan sesuai jadwal yang telah disepakati. Keterlambatan pengiriman dapat terjadi karena kondisi force majeure.</p>
                    
                    <h6>4. Pembatalan</h6>
                    <p>Pembatalan pesanan hanya dapat dilakukan maksimal 2 jam setelah pemesanan.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
     <?php include 'includes/footer.php'; ?>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Tambahkan di bagian JavaScript order.php
        // //Sync delivery date and time to checkout form
        document.getElementById('delivery_date')?.addEventListener('change', function() {
            document.getElementById('checkout_delivery_date').value = this.value;
        });

        // Update checkout delivery time when time slot is selected
        function selectTimeSlot(element, time) {
            // Remove active state from all slots
            document.querySelectorAll('[onclick^="selectTimeSlot"]').forEach(slot => {
                slot.classList.remove('border-purple-500', 'bg-purple-50', 'text-purple-700');
                slot.classList.add('border-gray-200');
            });
            
            // Add active state to selected slot
            element.classList.remove('border-gray-200');
            element.classList.add('border-purple-500', 'bg-purple-50', 'text-purple-700');
            
            // Update hidden inputs
            document.getElementById('delivery_time').value = time;
            document.getElementById('checkout_delivery_time').value = time;
        }

    // Initialize delivery info on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Set default delivery date to today
        const today = new Date().toISOString().split('T')[0];
        if (document.getElementById('delivery_date')) {
            document.getElementById('delivery_date').value = today;
            document.getElementById('checkout_delivery_date').value = today;
        }
        
        // Set default delivery time
        document.getElementById('checkout_delivery_time').value = '08:00 - 10:00';
    });

    // Validate order ID input
    document.querySelector('input[name="order_id"]')?.addEventListener('input', function(e) {
        // Remove non-numeric characters except #
        let value = e.target.value;
        value = value.replace(/[^0-9#]/g, '');
        e.target.value = value;
    });
    // Update subtotal when product or quantity changes
    document.getElementById('product_id').addEventListener('change', updateSubtotal);
    document.getElementById('quantity').addEventListener('input', updateSubtotal);
    
    function updateSubtotal() {
        const productSelect = document.getElementById('product_id');
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
        const quantity = parseInt(document.getElementById('quantity').value) || 0;
        const subtotal = price * quantity;
        
        document.getElementById('subtotal').textContent = formatRupiah(subtotal);
    }
    
    function formatRupiah(amount) {
        return 'Rp ' + amount.toLocaleString('id-ID');
    }
    
    // Cart quantity controls
    function incrementQuantity(index) {
        const input = document.getElementById('cart_quantity_' + index);
        input.value = parseInt(input.value) + 1;
        document.querySelector('button[name="update_cart"]').click();
    }
    
    function decrementQuantity(index) {
        const input = document.getElementById('cart_quantity_' + index);
        if (parseInt(input.value) > 1) {
            input.value = parseInt(input.value) - 1;
            document.querySelector('button[name="update_cart"]').click();
        }
    }
    
    // Delivery time slot selection
    function selectTimeSlot(element, time) {
        // Remove active state from all slots
        document.querySelectorAll('[onclick^="selectTimeSlot"]').forEach(slot => {
            slot.classList.remove('border-purple-500', 'bg-purple-50', 'text-purple-700');
            slot.classList.add('border-gray-200');
        });
        
        // Add active state to selected slot
        element.classList.remove('border-gray-200');
        element.classList.add('border-purple-500', 'bg-purple-50', 'text-purple-700');
        
        // Update hidden input
        document.getElementById('delivery_time').value = time;
    }
    
    // File upload preview
    document.getElementById('payment_proof')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        const fileSelected = document.getElementById('file-selected');
        
        if (file) {
            fileSelected.innerHTML = `
                <div class="flex items-center justify-between">
                    <span class="text-blue-600">
                        <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        ${file.name}
                    </span>
                    <span class="text-gray-500 text-sm">${(file.size / 1024).toFixed(2)} KB</span>
                </div>
            `;
            fileSelected.classList.remove('hidden');
        } else {
            fileSelected.classList.add('hidden');
        }
    });
    
    // Copy to clipboard
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: 'Nomor pembayaran berhasil disalin',
                timer: 1500,
                showConfirmButton: false
            });
        });
    }
    
    // Subscription plan selection
    function selectSubscriptionPlan(planId, planName) {
        document.getElementById('plan_id').value = planId;
        document.getElementById('subscriptionFormTitle').textContent = 'Berlangganan ' + planName;
        document.getElementById('subscriptionForm').classList.remove('hidden');
        document.getElementById('subscriptionForm').scrollIntoView({ behavior: 'smooth' });
        
        // Update summary
        updateSubscriptionSummary();
    }
    
    // Update subscription summary
    document.getElementById('delivery_day')?.addEventListener('change', updateSubscriptionSummary);
    
    function updateSubscriptionSummary() {
        const planId = document.getElementById('plan_id').value;
        const deliveryDay = document.getElementById('delivery_day').value;
        
        if (planId) {
            // Update summary based on selected plan
            const plans = <?php echo json_encode($subscriptionPlans); ?>;
            const selectedPlan = plans.find(p => p.id == planId);
            
            if (selectedPlan) {
                document.getElementById('summary_package').textContent = selectedPlan.name;
                document.getElementById('summary_frequency').textContent = selectedPlan.duration;
                document.getElementById('summary_day').textContent = deliveryDay || '-';
                document.getElementById('summary_total').textContent = formatRupiah(selectedPlan.price);
            }
        }
    }
    
    // Form validation
    document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
        const phone = document.getElementById('phone').value;
        if (!/^[0-9]+$/.test(phone)) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Nomor telepon hanya boleh berisi angka'
            });
        }
    });
    
    // Initialize on page load
    updateSubtotal();
    </script>
    <!-- JavaScript untuk kalkulator kembalian -->
<script>
function calculateChange() {
    const totalAmount = parseInt(document.getElementById('totalAmount').getAttribute('data-amount'));
    const cashInput = document.getElementById('cashAmount').value.replace(/[^0-9]/g, '');
    const cashAmount = parseInt(cashInput) || 0;
    
    const change = cashAmount - totalAmount;
    const changeElement = document.getElementById('changeAmount');
    
    if (change >= 0) {
        changeElement.textContent = formatRupiah(change);
        changeElement.classList.remove('text-red-600');
        changeElement.classList.add('text-green-600');
    } else {
        changeElement.textContent = 'Kurang ' + formatRupiah(Math.abs(change));
        changeElement.classList.remove('text-green-600');
        changeElement.classList.add('text-red-600');
    }
}

function formatRupiah(amount) {
    return 'Rp ' + amount.toLocaleString('id-ID');
}

// Format input saat mengetik
document.getElementById('cashAmount')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/[^0-9]/g, '');
    if (value) {
        e.target.value = parseInt(value).toLocaleString('id-ID');
    }
});

// Live tracking update setiap 30 detik
let trackingInterval;

function startLiveTracking(orderId) {
    // Update immediately
    updateTrackingInfo(orderId);
    
    // Then update every 30 seconds
    trackingInterval = setInterval(() => {
        updateTrackingInfo(orderId);
    }, 30000);
}

function stopLiveTracking() {
    if (trackingInterval) {
        clearInterval(trackingInterval);
    }
}

function updateTrackingInfo(orderId) {
    $.ajax({
        url: 'ajax_tracking.php',
        method: 'GET',
        data: { order_id: orderId },
        success: function(response) {
            if (response.success) {
                // Update courier location
                $('#courier-location').text(response.courier_location);
                
                // Update delivery status
                if (response.delivery_status === 'on_the_way') {
                    $('#courier-status').html('<span class="text-green-600 font-semibold">🏍️ Kurir sedang dalam perjalanan</span>');
                } else if (response.delivery_status === 'delivered') {
                    $('#courier-status').html('<span class="text-blue-600 font-semibold">✅ Pesanan telah diterima</span>');
                    stopLiveTracking();
                }
                
                // Update estimated time
                if (response.estimated_time) {
                    $('#estimated-time').text('Estimasi tiba: ' + response.estimated_time);
                }
                
                // Update progress bar if exists
                if (response.progress) {
                    updateProgressBar(response.progress);
                }
            }
        },
        error: function() {
            console.error('Failed to update tracking info');
        }
    });
}

function updateProgressBar(progress) {
    $('.progress-bar').css('width', progress + '%');
    $('.progress-text').text(progress + '% perjalanan selesai');
}

// Initialize live tracking when on tracking tab with valid order
$(document).ready(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    const orderId = urlParams.get('order_id');
    
    if (tab === 'track' && orderId) {
        startLiveTracking(orderId);
    }
});

// Stop tracking when leaving page
$(window).on('beforeunload', function() {
    stopLiveTracking();
});

// Simulasi pergerakan kurir dengan animasi di peta (optional)
function animateCourierMovement() {
    const locations = [
        { lat: -6.2415, lng: 106.9927, desc: "Depot Aquanest" },
        { lat: -6.2425, lng: 106.9937, desc: "Jalan Raya Bekasi Timur" },
        { lat: -6.2435, lng: 106.9947, desc: "Memasuki area perumahan" },
        { lat: -6.2445, lng: 106.9957, desc: "500m dari lokasi Anda" },
        { lat: -6.2455, lng: 106.9967, desc: "Tiba di lokasi" }
    ];
    
    let currentLocation = 0;
    
    const moveInterval = setInterval(() => {
        if (currentLocation < locations.length) {
            updateMapMarker(locations[currentLocation]);
            updateLocationText(locations[currentLocation].desc);
            currentLocation++;
        } else {
            clearInterval(moveInterval);
        }
    }, 10000); // Update every 10 seconds
}

// Add click to call courier button
function callCourier(phoneNumber) {
    if (confirm('Hubungi kurir sekarang?')) {
        window.location.href = 'tel:' + phoneNumber;
    }
}

// Add notification when courier is near
function checkCourierProximity(distance) {
    if (distance < 1000 && !localStorage.getItem('notified_' + orderId)) {
        // Show browser notification if permitted
        if ("Notification" in window && Notification.permission === "granted") {
            new Notification("Kurir Aquanest", {
                body: "Kurir Anda sudah dekat! Persiapkan pembayaran.",
                icon: "/img/logo.png"
            });
        }
        
        // Show in-page notification
        showNotification('Kurir sudah dekat! Mohon bersiap untuk menerima pesanan.');
        
        // Mark as notified
        localStorage.setItem('notified_' + orderId, 'true');
    }
}

function showNotification(message) {
    const notification = $(`
        <div class="fixed top-4 right-4 bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 z-50">
            <div class="flex items-center">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
                <span class="font-medium">${message}</span>
            </div>
        </div>
    `);
    
    $('body').append(notification);
    
    setTimeout(() => {
        notification.removeClass('translate-x-full');
    }, 100);
    
    setTimeout(() => {
        notification.addClass('translate-x-full');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Request notification permission
if ("Notification" in window && Notification.permission === "default") {
    Notification.requestPermission();
}
</script>
<script src="js/navbar.js"></script>
</body>
</html>


