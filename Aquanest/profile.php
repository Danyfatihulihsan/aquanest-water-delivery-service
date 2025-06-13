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

// Get user data using PDO
$userId = $_SESSION['user_id']; 
$sql = "SELECT * FROM users WHERE `user_id` = :user_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(":user_id", $userId, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    // User not found
    session_destroy();
    redirect('login.php');
}

$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Process profile update
$name_err = $email_err = $phone_err = $address_err = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    // Validate name
    if (empty(trim($_POST["name"]))) {
        $name_err = "Silakan masukkan nama.";
    } else {
        $name = trim($_POST["name"]);
    }
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Silakan masukkan email.";
    } else {
        // Check if email already exists for another user
        $checkSql = "SELECT `user_id` FROM users WHERE email = :email AND `user_id` != :user_id";
        $checkStmt = $conn->prepare($checkSql);
        $email = trim($_POST["email"]);
        $checkStmt->bindParam(":email", $email, PDO::PARAM_STR);
        $checkStmt->bindParam(":user_id", $userId, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            $email_err = "Email sudah digunakan.";
        } else {
            $email = trim($_POST["email"]);
        }
    }
    
    // Get optional fields
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]);
    
    // Check input errors before updating
    if (empty($name_err) && empty($email_err)) {
        // Prepare update statement
        $updateSql = "UPDATE users SET name = :name, email = :email, phone = :phone, address = :address WHERE `user_id` = :user_id";
        
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bindParam(":name", $name, PDO::PARAM_STR);
        $updateStmt->bindParam(":email", $email, PDO::PARAM_STR);
        $updateStmt->bindParam(":phone", $phone, PDO::PARAM_STR);
        $updateStmt->bindParam(":address", $address, PDO::PARAM_STR);
        $updateStmt->bindParam(":user_id", $userId, PDO::PARAM_INT);
        
        if ($updateStmt->execute()) {
            // Update successful
            $_SESSION["name"] = $name;
            $success_message = "Profil berhasil diperbarui!";
            
            // Refresh user data
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error_message = "Terjadi kesalahan. Silakan coba lagi.";
        }
    }
}

// Process password change
$current_password_err = $new_password_err = $confirm_password_err = "";
$password_success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    // Validate current password
    if (empty(trim($_POST["current_password"]))) {
        $current_password_err = "Silakan masukkan password saat ini.";
    } else {
        $current_password = trim($_POST["current_password"]);
        
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            $current_password_err = "Password saat ini salah.";
        }
    }
    
    // Validate new password
    if (empty(trim($_POST["new_password"]))) {
        $new_password_err = "Silakan masukkan password baru.";     
    } elseif (strlen(trim($_POST["new_password"])) < 6) {
        $new_password_err = "Password harus memiliki minimal 6 karakter.";
    } else {
        $new_password = trim($_POST["new_password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Silakan konfirmasi password baru.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($new_password_err) && ($new_password != $confirm_password)) {
            $confirm_password_err = "Password tidak cocok.";
        }
    }
    
    // Check input errors before updating
    if (empty($current_password_err) && empty($new_password_err) && empty($confirm_password_err)) {
        // Prepare update statement
        $updatePasswordSql = "UPDATE users SET password = :password WHERE `user_id` = :user_id";
        
        $updatePasswordStmt = $conn->prepare($updatePasswordSql);
        $param_password = password_hash($new_password, PASSWORD_DEFAULT);
        $updatePasswordStmt->bindParam(":password", $param_password, PDO::PARAM_STR);
        $updatePasswordStmt->bindParam(":user_id", $userId, PDO::PARAM_INT);
        
        if ($updatePasswordStmt->execute()) {
            // Password update successful
            $password_success = "Password berhasil diubah!";
        } else {
            $password_error = "Terjadi kesalahan. Silakan coba lagi.";
        }
    }
}

// Get user orders with more details
$ordersSql = "SELECT o.*, 
              (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count 
              FROM orders o 
              WHERE o.customer_id = :user_id 
              ORDER BY o.order_date DESC LIMIT 5";
$ordersStmt = $conn->prepare($ordersSql);
$ordersStmt->bindParam(":user_id", $userId, PDO::PARAM_INT);
$ordersStmt->execute();
$recentOrders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

// Determine active tab
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Aquanest</title>
    <link rel="stylesheet" href="css/profile.css"> 
    <link rel="stylesheet" href="css/navbar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
</head>
<body>
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>
    
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
                    <div class="profile-nav-item <?php echo $activeTab == 'profile' ? 'active' : ''; ?>" data-tab="profile">
                        <span class="profile-nav-icon"><i class="fas fa-user"></i></span>
                        <span>Profil Saya</span>
                    </div>
                    <div class="profile-nav-item <?php echo $activeTab == 'orders' ? 'active' : ''; ?>" data-tab="orders">
                        <span class="profile-nav-icon"><i class="fas fa-shopping-bag"></i></span>
                        <span>Riwayat Pesanan</span>
                    </div>
                    <div class="profile-nav-item <?php echo $activeTab == 'addresses' ? 'active' : ''; ?>" data-tab="addresses">
                        <span class="profile-nav-icon"><i class="fas fa-map-marker-alt"></i></span>
                        <span>Alamat Tersimpan</span>
                    </div>
                    <div class="profile-nav-item <?php echo $activeTab == 'security' ? 'active' : ''; ?>" data-tab="security">
                        <span class="profile-nav-icon"><i class="fas fa-shield-alt"></i></span>
                        <span>Keamanan</span>
                    </div>
                    <a href="logout.php" class="profile-nav-item">
                        <span class="profile-nav-icon"><i class="fas fa-sign-out-alt"></i></span>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
            
            <!-- Profile Main Content -->
            <div class="profile-main">
                <!-- Profile Tab -->
                <div class="profile-tab" id="profile-tab" style="display: <?php echo $activeTab == 'profile' ? 'block' : 'none'; ?>">
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <h2>Informasi Profil</h2>
                        </div>
                        <div class="profile-card-body">
                            <?php if (!empty($success_message)): ?>
                                <div class="alert alert-success"><?php echo $success_message; ?></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger"><?php echo $error_message; ?></div>
                            <?php endif; ?>
                            
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                <div class="form-group">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control-plaintext" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                    <div class="form-text">Username tidak dapat diubah.</div>
                                </div>
                                <div class="form-group">
                                    <label for="name" class="form-label">Nama Lengkap</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>">
                                    <?php if (!empty($name_err)): ?>
                                        <div class="invalid-feedback"><?php echo $name_err; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                                    <?php if (!empty($email_err)): ?>
                                        <div class="invalid-feedback"><?php echo $email_err; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label for="phone" class="form-label">Nomor Telepon</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    <?php if (!empty($phone_err)): ?>
                                        <div class="invalid-feedback"><?php echo $phone_err; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label for="address" class="form-label">Alamat</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                    <?php if (!empty($address_err)): ?>
                                        <div class="invalid-feedback"><?php echo $address_err; ?></div>
                                    <?php endif; ?>
                                </div>
                                <button type="submit" name="update_profile" class="btn btn-primary">Simpan Perubahan</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Orders Tab -->
                <div class="profile-tab" id="orders-tab" style="display: <?php echo $activeTab == 'orders' ? 'block' : 'none'; ?>">
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <h2>Riwayat Pesanan</h2>
                                <a href="riwayat_pesanan.php" class="btn btn-outline btn-sm">Lihat Semua</a>
                            </div>
                        </div>
                        <div class="profile-card-body">
                            <?php if (empty($recentOrders)): ?>
                                <div class="no-items">
                                    <p>Anda belum memiliki riwayat pesanan.</p>
                                    <a href="order.php" class="btn btn-primary">Mulai Belanja</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
                                    <div class="order-item">
                                        <div class="order-item-header">
                                            <span class="order-id">Pesanan #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></span>
                                            <span class="order-date"><?php echo date('d-m-Y H:i', strtotime($order['order_date'])); ?></span>
                                        </div>
                                        <div class="order-details">
                                            <div>
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
                                            <div>
                                                <strong><?php echo formatRupiah($order['total_amount']); ?></strong>
                                            </div>
                                            <div>
                                                <a href="order-success.php?id=<?php echo $order['order_id']; ?>" class="btn btn-outline btn-sm">Detail</a>
                                                <?php if ($order['status'] == 'shipped'): ?>
                                                    <a href="confirm-receipt.php?id=<?php echo $order['order_id']; ?>" class="btn btn-success btn-sm">Konfirmasi Penerimaan</a>
                                                <?php endif; ?>
                                                <?php if ($order['payment_status'] == 'unpaid'): ?>
                                                    <a href="payment.php?id=<?php echo $order['order_id']; ?>" class="btn btn-warning btn-sm">Bayar Sekarang</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php 
                                        // Get order items preview
                                        $itemsSql = "SELECT oi.*, p.name as product_name 
                                                    FROM order_items oi 
                                                    JOIN products p ON oi.product_id = p.product_id 
                                                    WHERE oi.order_id = :order_id 
                                                    LIMIT 3";
                                        $itemsStmt = $conn->prepare($itemsSql);
                                        $itemsStmt->bindParam(":order_id", $order['order_id'], PDO::PARAM_INT);
                                        $itemsStmt->execute();
                                        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        if (!empty($items)): 
                                        ?>
                                        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #e9ecef; font-size: 0.9em; color: #6c757d;">
                                            <?php foreach($items as $index => $item): ?>
                                                <?php echo ($index > 0 ? ' • ' : '') . $item['product_name'] . ' x' . $item['quantity']; ?>
                                            <?php endforeach; ?>
                                            
                                            <?php if ($order['item_count'] > count($items)): ?>
                                                <span> • ... dan <?php echo $order['item_count'] - count($items); ?> item lainnya</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div style="margin-top: 20px; text-align: center;">
                                    <a href="riwayat_pesanan.php" class="btn btn-outline">Lihat Semua Pesanan</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Addresses Tab -->
                <div class="profile-tab" id="addresses-tab" style="display: <?php echo $activeTab == 'addresses' ? 'block' : 'none'; ?>">
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <h2>Alamat Tersimpan</h2>
                        </div>
                        <div class="profile-card-body">
                            <div style="margin-bottom: 20px;">
                                <button type="button" id="add-address-btn" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Tambah Alamat Baru
                                </button>
                            </div>
                            
                            <?php if (empty($user['address'])): ?>
                                <div class="no-items">
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
                                            <button type="button" class="btn btn-outline btn-sm edit-address-btn">
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
                </div>
                
                <!-- Security Tab -->
                <div class="profile-tab" id="security-tab" style="display: <?php echo $activeTab == 'security' ? 'block' : 'none'; ?>">
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
                            
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?tab=security" method="post">
                                <div class="form-group">
                                    <label for="current_password" class="form-label">Password Saat Ini</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                    <?php if (!empty($current_password_err)): ?>
                                        <div class="invalid-feedback"><?php echo $current_password_err; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label for="new_password" class="form-label">Password Baru</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                    <?php if (!empty($new_password_err)): ?>
                                        <div class="invalid-feedback"><?php echo $new_password_err; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    <?php if (!empty($confirm_password_err)): ?>
                                        <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                                    <?php endif; ?>
                                </div>
                                <button type="submit" name="change_password" class="btn btn-primary">Ubah Password</button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="profile-card mt-4">
                        <div class="profile-card-header">
                            <h2>Aktivitas Login</h2>
                        </div>
                        <div class="profile-card-body">
                            <div class="order-item">
                                <div class="order-item-header">
                                    <span class="order-id">Login Terakhir</span>
                                    <span class="order-date"><?php echo date('d-m-Y H:i'); ?></span>
                                </div>
                                <div>
                                    <p>
                                        <i class="fas fa-desktop me-2"></i> Browser: <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT']); ?><br>
                                        <i class="fas fa-map-marker-alt me-2"></i> Lokasi: Bekasi, Indonesia
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Address Modal Template (hidden) -->
    <div id="address-modal-template" style="display: none;">
        <div class="modal-backdrop" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; justify-content: center; align-items: center;">
            <div class="modal-content" style="background: white; border-radius: 10px; width: 90%; max-width: 500px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
                <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0;" id="modal-title">Tambah Alamat Baru</h3>
                    <button type="button" class="close-modal-btn" style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="address-form" action="update-address.php" method="post">
                        <div class="form-group">
                            <label for="address_label" class="form-label">Label Alamat</label>
                            <input type="text" class="form-control" id="address_label" name="address_label" placeholder="Rumah, Kantor, dll">
                        </div>
                        <div class="form-group">
                            <label for="recipient_name" class="form-label">Nama Penerima</label>
                            <input type="text" class="form-control" id="recipient_name" name="recipient_name">
                        </div>
                        <div class="form-group">
                            <label for="recipient_phone" class="form-label">Nomor Telepon Penerima</label>
                            <input type="tel" class="form-control" id="recipient_phone" name="recipient_phone">
                        </div>
                        <div class="form-group">
                            <label for="full_address" class="form-label">Alamat Lengkap</label>
                            <textarea class="form-control" id="full_address" name="full_address" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="postal_code" class="form-label">Kode Pos</label>
                            <input type="text" class="form-control" id="postal_code" name="postal_code">
                        </div>
                        <div class="form-check" style="margin-bottom: 20px;">
                            <input class="form-check-input" type="checkbox" id="set_as_primary" name="set_as_primary" style="margin-right: 10px;">
                            <label class="form-check-label" for="set_as_primary">
                                Jadikan sebagai alamat utama
                            </label>
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 10px;">
                            <button type="button" class="btn btn-outline close-modal-btn">Batal</button>
                            <button type="submit" class="btn btn-primary">Simpan Alamat</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
        // Toggle mobile menu (if it exists)
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('menu-icon')) {
                document.getElementById('menu-icon').addEventListener('click', function() {
                    const navList = document.getElementById('nav-list');
                    navList.classList.toggle('active');
                });
            }
            
            // Tab Navigation
            const navItems = document.querySelectorAll('.profile-nav-item:not(a)');
            const tabs = document.querySelectorAll('.profile-tab');
            
            navItems.forEach(item => {
                item.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Update URL with tab parameter
                    const url = new URL(window.location);
                    url.searchParams.set('tab', tabId);
                    window.history.pushState({}, '', url);
                    
                    // Hide all tabs
                    tabs.forEach(tab => {
                        tab.style.display = 'none';
                    });
                    
                    // Show selected tab
                    document.getElementById(tabId + '-tab').style.display = 'block';
                    
                    // Update active state
                    navItems.forEach(nav => {
                        nav.classList.remove('active');
                    });
                    this.classList.add('active');
                });
            });
            
            // Address Modal
            const addressModalTemplate = document.getElementById('address-modal-template');
            const addAddressBtn = document.getElementById('add-address-btn');
            const editAddressBtns = document.querySelectorAll('.edit-address-btn');
            
            // Show Add Address Modal
            if (addAddressBtn) {
                addAddressBtn.addEventListener('click', function() {
                    const modal = addressModalTemplate.cloneNode(true);
                    modal.id = '';
                    modal.style.display = 'block';
                    document.body.appendChild(modal);
                    
                    // Close Modal
                    const closeButtons = modal.querySelectorAll('.close-modal-btn');
                    closeButtons.forEach(btn => {
                        btn.addEventListener('click', function() {
                            document.body.removeChild(modal);
                        });
                    });
                });
            }
            
            // Show Edit Address Modal
            editAddressBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const modal = addressModalTemplate.cloneNode(true);
                    modal.id = '';
                    modal.style.display = 'block';
                    
                    // Update title and form for editing
                    modal.querySelector('#modal-title').textContent = 'Edit Alamat';
                    
                    // Pre-fill form with current address data
                    const addressText = this.closest('.address-card').querySelector('p').textContent.trim();
                    modal.querySelector('#full_address').value = addressText;
                    
                    // Add to body
                    document.body.appendChild(modal);
                    
                    // Close Modal
                    const closeButtons = modal.querySelectorAll('.close-modal-btn');
                    closeButtons.forEach(btn => {
                        btn.addEventListener('click', function() {
                            document.body.removeChild(modal);
                        });
                    });
                });
            });
        });
    </script>
</body>
</html>