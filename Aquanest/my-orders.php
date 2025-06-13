<?php
// Start session
session_start();

// Include database connection
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

// Get user ID
$userId = $_SESSION['user_id'];

// Set active tab from URL parameter
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'orders';

// Get user data
try {
    $userStmt = $conn->prepare("SELECT * FROM users WHERE user_id = :user_id");
    $userStmt->bindParam(':user_id', $userId);
    $userStmt->execute();
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        redirect('login.php');
    }
} catch (PDOException $e) {
    echo "<div class='error-message'>Error getting user data: " . $e->getMessage() . "</div>";
    $user = array('name' => 'User', 'email' => '', 'phone' => '');
}

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Process form submissions
$password_success = '';
$password_error = '';
$current_password_err = '';
$new_password_err = '';
$confirm_password_err = '';

// Process change password form
if (isset($_POST['change_password'])) {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate input
    if (empty($current_password)) {
        $current_password_err = "Masukkan password saat ini";
    }
    
    if (empty($new_password)) {
        $new_password_err = "Masukkan password baru";
    } elseif (strlen($new_password) < 6) {
        $new_password_err = "Password harus memiliki minimal 6 karakter";
    }
    
    if (empty($confirm_password)) {
        $confirm_password_err = "Konfirmasi password baru";
    } elseif ($new_password != $confirm_password) {
        $confirm_password_err = "Password tidak cocok";
    }
    
    // Check if current password is correct
    if (empty($current_password_err)) {
        $sql = "SELECT password FROM users WHERE user_id = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":user_id", $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($current_password, $row['password'])) {
            $current_password_err = "Password saat ini salah";
        }
    }
    
    // Update password if no errors
    if (empty($current_password_err) && empty($new_password_err) && empty($confirm_password_err)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $sql = "UPDATE users SET password = :password WHERE user_id = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":password", $hashed_password, PDO::PARAM_STR);
        $stmt->bindParam(":user_id", $userId, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $password_success = "Password berhasil diubah!";
        } else {
            $password_error = "Terjadi kesalahan. Silakan coba lagi.";
        }
    }
    
    // Ensure we stay on the security tab
    $activeTab = 'security';
}

// Get user orders with filtering (only if orders tab is active)
$orders = array();
if ($activeTab == 'orders') {
    try {
        // Cek struktur tabel untuk menemukan kolom ID pengguna yang benar
        $checkTableStmt = $conn->prepare("DESCRIBE orders");
        $checkTableStmt->execute();
        $columns = $checkTableStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Tentukan nama kolom yang benar
        $userIdColumn = "customer_id"; // Default asumsi
        
        // Periksa kolom yang mungkin menyimpan ID pengguna
        if (in_array("customer_id", $columns)) {
            $userIdColumn = "customer_id";
        } elseif (in_array("client_id", $columns)) {
            $userIdColumn = "client_id";
        } elseif (in_array("buyer_id", $columns)) {
            $userIdColumn = "buyer_id";
        } elseif (in_array("user_id", $columns)) {
            $userIdColumn = "user_id";
        }
        
        // Get user orders with filtering
        $sql = "SELECT * FROM orders WHERE $userIdColumn = :user_id";
        $params = array(":user_id" => $userId);
        
        // Add status filter if provided
        if (!empty($status)) {
            $sql .= " AND status = :status";
            $params[":status"] = $status;
        }
        
        // Add date range filter if provided
        if (!empty($startDate)) {
            $sql .= " AND order_date >= :start_date";
            $params[":start_date"] = $startDate . " 00:00:00";
        }
        
        if (!empty($endDate)) {
            $sql .= " AND order_date <= :end_date";
            $params[":end_date"] = $endDate . " 23:59:59";
        }
        
        // Order by date descending
        $sql .= " ORDER BY order_date DESC";
        
        // Execute query
        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        // Execute and fetch results
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        // Tangani error dengan lebih elegan
        echo "<div style='background-color: #ffecec; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px; border: 1px solid #f5c6cb;'>";
        echo "<h3>Ada kesalahan dalam sistem:</h3>";
        echo "<p>Pesan error: " . $e->getMessage() . "</p>";
        echo "<p>Silakan hubungi administrator.</p>";
        echo "</div>";
        $orders = array(); // Set ke array kosong agar halaman tetap bisa ditampilkan
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Aquanest</title>
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/profile.css">
    <link rel="stylesheet" href="css/my-orders.css">
    <link rel="stylesheet" href="css/navbar.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* Styling umum */
        body {
            background-color: #f7f9fc;
        }
        
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header profile */
        .profile-header {
            background-color: #2b95e9;
            color: white;
            border-radius: 10px;
            padding: 20px;
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background-color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 30px;
            color: #2b95e9;
        }
        
        .profile-info h1 {
            margin: 0 0 5px 0;
            font-size: 24px;
        }
        
        .profile-info p {
            margin: 0;
            font-size: 14px;
            opacity: 0.9;
        }
        
        /* Content layout */
        .profile-content {
            display: flex;
            gap: 30px;
        }
        
        /* Sidebar navigation */
        .profile-sidebar {
            width: 250px;
            flex-shrink: 0;
        }
        
        .profile-nav {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .profile-nav-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: #555;
            text-decoration: none;
            border-bottom: 1px solid #f1f1f1;
            transition: all 0.3s;
        }
        
        .profile-nav-item:hover {
            background-color: #f8f9fa;
            color: #2b95e9;
        }
        
        .profile-nav-item.active {
            background-color: #e3f2fd;
            color: #2b95e9;
            border-left: 3px solid #2b95e9;
        }
        
        .profile-nav-icon {
            width: 24px;
            margin-right: 15px;
            text-align: center;
        }
        
        /* Main content area */
        .profile-tab-content {
            flex: 1;
        }
        
        .page-title {
            color: #2b95e9;
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        /* Form styling for all tabs */
        .profile-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .profile-card-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        
        .profile-card-header h2 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        
        .profile-card-body {
            padding: 20px;
        }
        
        /* Form pesanan yang lebih simetris */
        .filter-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .filter-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            width: 100%;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 15px;
        }
        
        .filter-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-filter, .btn-reset, .btn-primary {
            padding: 10px 25px;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
        }
        
        .btn-filter, .btn-primary {
            background-color: #2b95e9;
            color: white;
            border: none;
        }
        
        .btn-reset {
            background-color: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
            text-decoration: none;
            display: inline-block;
        }
        
        /* Alert messages */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .invalid-feedback {
            color: #dc3545;
            font-size: 14px;
            margin-top: 5px;
        }
        
        /* Order cards */
        .order-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        
        .order-id {
            font-weight: 600;
        }
        
        .order-date {
            color: #777;
            font-size: 14px;
            margin-left: 15px;
        }
        
        .order-status {
            display: flex;
            gap: 10px;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .badge-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-primary {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .badge-secondary {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .order-body {
            padding: 20px;
        }
        
        .order-items {
            margin-bottom: 20px;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            margin-right: 15px;
        }
        
        .item-details h4 {
            margin: 0 0 5px;
            font-size: 16px;
        }
        
        .item-price {
            color: #777;
            font-size: 14px;
        }
        
        .order-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .order-total {
            font-size: 18px;
            font-weight: 600;
        }
        
        .order-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-details {
            background-color: #2b95e9;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 15px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
        }
        
        .btn-pay {
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 15px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
        }
        
        /* No items message */
        .no-orders, .no-items {
            text-align: center;
            padding: 40px 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .no-orders i, .no-items i {
            font-size: 50px;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        /* Address card */
        .address-card {
            background-color: white;
            border: 1px solid #eee;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .address-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .btn-outline {
            background-color: transparent;
            color: #2b95e9;
            border: 1px solid #2b95e9;
            border-radius: 5px;
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <!-- Profile Content -->
    <div class="profile-container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['name']); ?></h1>
                <p>
                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                    <?php if (!empty($user['phone'])): ?>
                    <span style="margin-left: 15px;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone']); ?></span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <!-- Profile Content -->
        <div class="profile-content">
            <!-- Profile Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-nav">
                    <a href="my-orders.php?tab=profile" class="profile-nav-item <?php echo $activeTab == 'profile' ? 'active' : ''; ?>">
                        <span class="profile-nav-icon"><i class="fas fa-user"></i></span>
                        <span>Profil Saya</span>
                    </a>
                    <a href="my-orders.php?tab=orders" class="profile-nav-item <?php echo $activeTab == 'orders' ? 'active' : ''; ?>">
                        <span class="profile-nav-icon"><i class="fas fa-shopping-bag"></i></span>
                        <span>Riwayat Pesanan</span>
                    </a>
                    <a href="my-orders.php?tab=addresses" class="profile-nav-item <?php echo $activeTab == 'addresses' ? 'active' : ''; ?>">
                        <span class="profile-nav-icon"><i class="fas fa-map-marker-alt"></i></span>
                        <span>Alamat Tersimpan</span>
                    </a>
                    <a href="my-orders.php?tab=security" class="profile-nav-item <?php echo $activeTab == 'security' ? 'active' : ''; ?>">
                        <span class="profile-nav-icon"><i class="fas fa-shield-alt"></i></span>
                        <span>Keamanan</span>
                    </a>
                    <a href="logout.php" class="profile-nav-item">
                        <span class="profile-nav-icon"><i class="fas fa-sign-out-alt"></i></span>
                        <span>Logout</span>
                    </a>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="profile-tab-content">
                
                <?php if ($activeTab == 'profile'): ?>
                <!-- Profile Tab -->
                <div class="profile-card">
                    <div class="profile-card-header">
                        <h2>Informasi Profil</h2>
                    </div>
                    <div class="profile-card-body">
                        <form action="my-orders.php?tab=profile" method="post">
                            <div class="form-group">
                                <label for="name" class="form-label">Nama Lengkap</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                <small class="text-muted">Email tidak dapat diubah</small>
                            </div>
                            <div class="form-group">
                                <label for="phone" class="form-label">Nomor Telepon</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                            </div>
                            <button type="submit" name="update_profile" class="btn-filter">Simpan Perubahan</button>
                        </form>
                    </div>
                </div>
                
                <?php elseif ($activeTab == 'orders'): ?>
                <!-- Orders Tab -->
                <h1 class="page-title">Pesanan Saya</h1>
                
                <!-- Filter Card -->
                <div class="filter-card">
                    <form action="my-orders.php?tab=orders" method="get" class="filter-form">
                        <input type="hidden" name="tab" value="orders">
                        <div class="filter-row">
                            <div class="form-group">
                                <label for="status" class="form-label">Status Pesanan</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="">Semua Status</option>
                                    <option value="pending" <?php echo ($status == 'pending') ? 'selected' : ''; ?>>Menunggu Konfirmasi</option>
                                    <option value="processing" <?php echo ($status == 'processing') ? 'selected' : ''; ?>>Sedang Diproses</option>
                                    <option value="shipped" <?php echo ($status == 'shipped') ? 'selected' : ''; ?>>Dalam Pengiriman</option>
                                    <option value="delivered" <?php echo ($status == 'delivered') ? 'selected' : ''; ?>>Telah Diterima</option>
                                    <option value="cancelled" <?php echo ($status == 'cancelled') ? 'selected' : ''; ?>>Dibatalkan</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="start_date" class="form-label">Dari Tanggal</label>
                                <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="end_date" class="form-label">Sampai Tanggal</label>
                                <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                            </div>
                        </div>
                        
                        <div class="filter-buttons">
                            <button type="submit" class="btn-filter">Filter</button>
                            <a href="my-orders.php?tab=orders" class="btn-reset">Reset</a>
                        </div>
                    </form>
                </div>
                
                <!-- Orders List -->
                <?php if (empty($orders)): ?>
                    <div class="no-orders">
                        <i class="fas fa-shopping-bag"></i>
                        <h3>Tidak ada pesanan ditemukan</h3>
                        <p>Anda belum memiliki pesanan atau tidak ada pesanan yang cocok dengan filter yang dipilih.</p>
                        <a href="order.php" class="btn-details">Mulai Belanja</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div>
                                    <span class="order-id">Pesanan #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></span>
                                    <span class="order-date"><?php echo date('d-m-Y H:i', strtotime($order['order_date'])); ?></span>
                                </div>
                                
                                <div class="order-status">
                                    <span class="badge badge-<?php 
                                        if ($order['status'] == 'pending') echo 'warning';
                                        elseif ($order['status'] == 'processing') echo 'info';
                                        elseif ($order['status'] == 'shipped') echo 'primary';
                                        elseif ($order['status'] == 'delivered') echo 'success';
                                        elseif ($order['status'] == 'cancelled') echo 'danger';
                                        else echo 'secondary';
                                    ?>">
                                        <?php 
                                        if ($order['status'] == 'pending') echo "Menunggu Konfirmasi";
                                        elseif ($order['status'] == 'processing') echo "Sedang Diproses";
                                        elseif ($order['status'] == 'shipped') echo "Dalam Pengiriman";
                                        elseif ($order['status'] == 'delivered') echo "Telah Diterima";
                                        elseif ($order['status'] == 'cancelled') echo "Dibatalkan";
                                        else echo ucfirst($order['status']);
                                        ?>
                                    </span>
                                    
                                    <span class="badge badge-<?php 
                                        if ($order['payment_status'] == 'unpaid') echo 'danger';
                                        elseif ($order['payment_status'] == 'pending_verification') echo 'warning';
                                        elseif ($order['payment_status'] == 'paid') echo 'success';
                                        else echo 'secondary';
                                    ?>">
                                        <?php 
                                        if ($order['payment_status'] == 'unpaid') echo "Belum Dibayar";
                                        elseif ($order['payment_status'] == 'pending_verification') echo "Menunggu Verifikasi";
                                        elseif ($order['payment_status'] == 'paid') echo "Lunas";
                                        else echo ucfirst($order['payment_status']);
                                        ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="order-body">
                                <?php
                                // Get order items using PDO
                                $itemSql = "SELECT oi.*, p.name as product_name, p.image 
                                            FROM order_items oi 
                                            JOIN products p ON oi.product_id = p.product_id 
                                            WHERE oi.order_id = :order_id";
                                
                                $itemStmt = $conn->prepare($itemSql);
                                $itemStmt->bindParam(":order_id", $order['order_id'], PDO::PARAM_INT);
                                $itemStmt->execute();
                                $orderItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                
                                <div class="order-items">
                                    <?php foreach ($orderItems as $item): ?>
                                        <div class="order-item">
                                            <img src="img/products/<?php echo $item['image']; ?>" alt="<?php echo $item['product_name']; ?>" class="item-image">
                                            <div class="item-details">
                                                <h4><?php echo $item['product_name']; ?></h4>
                                                <span class="item-price"><?php echo $item['quantity']; ?> x <?php echo formatRupiah($item['price']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="order-summary">
                                    <div class="order-total">
                                        Total: <?php echo formatRupiah($order['total_amount']); ?>
                                    </div>
                                    
                                    <div class="order-actions">
                                        <a href="order-success.php?id=<?php echo $order['order_id']; ?>" class="btn-details">Detail Pesanan</a>
                                        
                                        <?php if ($order['payment_status'] == 'unpaid'): ?>
                                            <a href="payment.php?id=<?php echo $order['order_id']; ?>" class="btn-pay">Bayar Sekarang</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php elseif ($activeTab == 'addresses'): ?>
                <!-- Addresses Tab -->
                <h1 class="page-title">Alamat Tersimpan</h1>
                
                <div class="profile-card">
                    <div class="profile-card-body">
                        <div style="margin-bottom: 20px;">
                            <button type="button" id="add-address-btn" class="btn-filter">
                                <i class="fas fa-plus"></i> Tambah Alamat Baru
                            </button>
                        </div>
                        
                        <?php if (empty($user['address'])): ?>
                            <div class="no-items">
                                <i class="fas fa-map-marker-alt"></i>
                                <h3>Tidak ada alamat tersimpan</h3>
                                <p>Anda belum menambahkan alamat. Silakan tambahkan alamat untuk mempermudah proses pemesanan.</p>
                            </div>
                        <?php else: ?>
                            <div class="address-card">
                                <div class="address-card-header">
                                    <div>
                                        <strong>Alamat Utama</strong>
                                        <span class="badge badge-primary">Utama</span>
                                    </div>
                                    <div class="address-actions">
                                        <button type="button" class="btn-outline btn-sm edit-address-btn">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <p><?php echo nl2br(htmlspecialchars($user['address'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php elseif ($activeTab == 'security'): ?>
                <!-- Security Tab -->
                <h1 class="page-title">Keamanan</h1>
                
                <div class="profile-card">
                    <div class="profile-card-header">
                        <h2>Ubah Password</h2>
                    </div>
                    <div class="profile-card-body">
                        <?php if (!empty($password_success)): ?>
                            <div class="alert alert-success"><?php echo $password_success; ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($password_error)): ?>
                            <div class="alert alert-danger"><?php echo $password_error; ?></div>
                        <?php endif; ?>
                        
                        <form action="my-orders.php?tab=security" method="post">
                            <div class="form-group">
                                <label for="current_password" class="form-label">Password Saat Ini</label>
                                <input type="password" class="form-control <?php echo (!empty($current_password_err)) ? 'is-invalid' : ''; ?>" id="current_password" name="current_password">
                                <?php if (!empty($current_password_err)): ?>
                                    <div class="invalid-feedback"><?php echo $current_password_err; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="new_password" class="form-label">Password Baru</label>
                                <input type="password" class="form-control <?php echo (!empty($new_password_err)) ? 'is-invalid' : ''; ?>" id="new_password" name="new_password">
                                <?php if (!empty($new_password_err)): ?>
                                    <div class="invalid-feedback"><?php echo $new_password_err; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                                <input type="password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password">
                                <?php if (!empty($confirm_password_err)): ?>
                                    <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                                <?php endif; ?>
                            </div>
                            <button type="submit" name="change_password" class="btn-filter">Ubah Password</button>
                        </form>
                    </div>
                </div>
                
                <div class="profile-card">
                    <div class="profile-card-header">
                        <h2>Aktivitas Login</h2>
                    </div>
                    <div class="profile-card-body">
                        <div class="order-item">
                            <div>
                                <p>
                                    <i class="fas fa-clock"></i> <strong>Login Terakhir:</strong> <?php echo date('d-m-Y H:i'); ?><br><br>
                                    <i class="fas fa-desktop"></i> <strong>Browser:</strong> <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT']); ?><br>
                                    <i class="fas fa-map-marker-alt"></i> <strong>Lokasi:</strong> Bekasi, Indonesia
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Form validation for addresses
        document.addEventListener('DOMContentLoaded', function() {
            // Add new address form
            const addAddressBtn = document.getElementById('add-address-btn');
            if (addAddressBtn) {
                addAddressBtn.addEventListener('click', function() {
                    window.location.href = 'address-form.php';
                });
            }
            
            // Edit address buttons
            const editAddressBtns = document.querySelectorAll('.edit-address-btn');
            editAddressBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    window.location.href = 'address-form.php?edit=1';
                });
            });
            
            // Date range validation
            const endDateInput = document.getElementById('end_date');
            if (endDateInput) {
                endDateInput.addEventListener('change', function() {
                    const startDate = document.getElementById('start_date').value;
                    const endDate = this.value;
                    
                    if (startDate && endDate && new Date(endDate) < new Date(startDate)) {
                        alert('Tanggal akhir tidak boleh lebih awal dari tanggal mulai');
                        this.value = '';
                    }
                });
            }
        });
    </script>
</body>
</html>