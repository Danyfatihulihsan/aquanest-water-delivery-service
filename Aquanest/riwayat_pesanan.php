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

// Pagination settings
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Filter settings
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$dateFilter = isset($_GET['date_range']) ? $_GET['date_range'] : '';
$searchFilter = isset($_GET['search']) ? $_GET['search'] : '';

// Base query
$countSql = "SELECT COUNT(*) FROM orders WHERE customer_id = :user_id";
$orderSql = "SELECT o.*, 
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count
           FROM orders o
           WHERE o.customer_id = :user_id";

// Add filters if present
$params = [":user_id" => $userId];

if (!empty($statusFilter)) {
    $countSql .= " AND status = :status";
    $orderSql .= " AND status = :status";
    $params[":status"] = $statusFilter;
}

if (!empty($dateFilter)) {
    // Date range processing
    switch($dateFilter) {
        case 'last_week':
            $countSql .= " AND order_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
            $orderSql .= " AND order_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
            break;
        case 'last_month':
            $countSql .= " AND order_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
            $orderSql .= " AND order_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
            break;
        case 'last_3months':
            $countSql .= " AND order_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
            $orderSql .= " AND order_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
            break;
        // Custom date range could be added here
    }
}

if (!empty($searchFilter)) {
    $countSql .= " AND (order_id LIKE :search OR notes LIKE :search)";
    $orderSql .= " AND (order_id LIKE :search OR notes LIKE :search)";
    $params[":search"] = "%$searchFilter%";
}

// Order by
$orderSql .= " ORDER BY order_date DESC LIMIT :offset, :limit";

// Execute count query
$countStmt = $conn->prepare($countSql);
foreach($params as $key => $val) {
    $countStmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $itemsPerPage);

// Execute order query with pagination
$orderStmt = $conn->prepare($orderSql);
foreach($params as $key => $val) {
    $orderStmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$orderStmt->bindValue(":offset", $offset, PDO::PARAM_INT);
$orderStmt->bindValue(":limit", $itemsPerPage, PDO::PARAM_INT);
$orderStmt->execute();
$orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

// Get order statuses for filter dropdown
$statusStmt = $conn->prepare("SELECT DISTINCT status FROM orders WHERE customer_id = :user_id");
$statusStmt->bindParam(":user_id", $userId, PDO::PARAM_INT);
$statusStmt->execute();
$availableStatuses = $statusStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - Aquanest</title>
    <link rel="stylesheet" href="css/profile.css">
    <link rel="stylesheet" href="css/riwayat_pesanan.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
   
</head>
<body>
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>
    
    <div class="order-history-container">
        <div class="order-history-header">
            <h1>Riwayat Pesanan</h1>
            <a href="profile.php" class="btn btn-outline"><i class="fas fa-user"></i> Kembali ke Profil</a>
            <link rel="stylesheet" href="css/riwayatpesanan.css">
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <form action="" method="get" class="filter-form">
                <div class="form-group">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">Semua Status</option>
                        <?php foreach($availableStatuses as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo ($statusFilter == $status) ? 'selected' : ''; ?>>
                                <?php 
                                if ($status == 'pending') echo "Menunggu Konfirmasi";
                                elseif ($status == 'processing') echo "Sedang Diproses";
                                elseif ($status == 'shipped') echo "Dalam Pengiriman";
                                elseif ($status == 'delivered') echo "Telah Diterima";
                                elseif ($status == 'cancelled') echo "Dibatalkan";
                                else echo ucfirst($status);
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date_range" class="form-label">Rentang Waktu</label>
                    <select id="date_range" name="date_range" class="form-control">
                        <option value="">Semua Waktu</option>
                        <option value="last_week" <?php echo ($dateFilter == 'last_week') ? 'selected' : ''; ?>>1 Minggu Terakhir</option>
                        <option value="last_month" <?php echo ($dateFilter == 'last_month') ? 'selected' : ''; ?>>1 Bulan Terakhir</option>
                        <option value="last_3months" <?php echo ($dateFilter == 'last_3months') ? 'selected' : ''; ?>>3 Bulan Terakhir</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="search" class="form-label">Cari</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Nomor pesanan atau catatan" value="<?php echo htmlspecialchars($searchFilter); ?>">
                </div>
                
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                    <a href="order-history.php" class="btn btn-outline" style="margin-left: 10px;"><i class="fas fa-times"></i> Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Export Links -->
        <div class="export-links">
            <a href="export-orders.php?format=pdf<?php echo !empty($_SERVER['QUERY_STRING']) ? '&'.$_SERVER['QUERY_STRING'] : ''; ?>" class="export-btn">
                <i class="fas fa-file-pdf"></i> Export PDF
            </a>
            <a href="export-orders.php?format=csv<?php echo !empty($_SERVER['QUERY_STRING']) ? '&'.$_SERVER['QUERY_STRING'] : ''; ?>" class="export-btn">
                <i class="fas fa-file-csv"></i> Export CSV
            </a>
        </div>
        
        <!-- Orders List -->
        <div class="order-list">
            <?php if (empty($orders)): ?>
                <div class="no-orders">
                    <i class="fas fa-shopping-bag fa-3x mb-4"></i>
                    <h3>Belum ada pesanan</h3>
                    <p>Anda belum melakukan pemesanan atau tidak ada pesanan yang sesuai dengan filter yang dipilih.</p>
                    <a href="order.php" class="btn btn-primary mt-3">Mulai Belanja</a>
                </div>
            <?php else: ?>
                <?php foreach($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <strong>Pesanan #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></strong>
                            </div>
                            <div class="order-date">
                                <i class="fas fa-calendar-alt"></i> <?php echo date('d-m-Y H:i', strtotime($order['order_date'])); ?>
                            </div>
                        </div>
                        <div class="order-body">
                            <div class="order-details">
                                <div class="order-status-badges">
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
                                    <span class="fw-bold fs-5"><?php echo formatRupiah($order['total_amount']); ?></span>
                                </div>
                            </div>
                            
                            <?php 
                            // Get order items
                            $itemStmt = $conn->prepare("
                                SELECT oi.*, p.name as product_name 
                                FROM order_items oi 
                                JOIN products p ON oi.product_id = p.product_id 
                                WHERE oi.order_id = :order_id
                            ");
                            $itemStmt->bindParam(":order_id", $order['order_id'], PDO::PARAM_INT);
                            $itemStmt->execute();
                            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            
                            <div class="items-preview">
                                <?php foreach($items as $index => $item): ?>
                                    <?php echo ($index > 0 ? ', ' : '') . $item['product_name'] . ' x' . $item['quantity']; ?>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="order-meta">
                                <div>
                                    <?php if ($order['payment_method'] == 'transfer'): ?>
                                        <small><i class="fas fa-money-bill-wave"></i> Transfer Bank</small>
                                    <?php elseif ($order['payment_method'] == 'cash'): ?>
                                        <small><i class="fas fa-money-bill"></i> Tunai (COD)</small>
                                    <?php elseif ($order['payment_method'] == 'credit_card'): ?>
                                        <small><i class="fas fa-credit-card"></i> Kartu Kredit</small>
                                    <?php elseif ($order['payment_method'] == 'e_wallet'): ?>
                                        <small><i class="fas fa-wallet"></i> E-Wallet</small>
                                    <?php else: ?>
                                        <small><i class="fas fa-money-check"></i> <?php echo ucfirst($order['payment_method']); ?></small>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($order['tracking_number'])): ?>
                                        <small class="ms-3"><i class="fas fa-truck"></i> <?php echo $order['tracking_number']; ?></small>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <a href="order-success.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-primary">Detail</a>
                                    
                                    <?php if ($order['status'] == 'shipped'): ?>
                                        <a href="confirm-receipt.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-success ms-2">Konfirmasi Penerimaan</a>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['payment_status'] == 'unpaid'): ?>
                                        <a href="payment.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-warning ms-2">Bayar Sekarang</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($currentPage > 1): ?>
                        <a href="?page=1<?php echo !empty($_SERVER['QUERY_STRING']) ? '&'.str_replace('page='.$currentPage, '', $_SERVER['QUERY_STRING']) : ''; ?>">«</a>
                        <a href="?page=<?php echo $currentPage-1; ?><?php echo !empty($_SERVER['QUERY_STRING']) ? '&'.str_replace('page='.$currentPage, '', $_SERVER['QUERY_STRING']) : ''; ?>">‹</a>
                    <?php else: ?>
                        <span class="disabled">«</span>
                        <span class="disabled">‹</span>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $startPage + 4);
                    if ($endPage - $startPage < 4) {
                        $startPage = max(1, $endPage - 4);
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <?php if ($i == $currentPage): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($_SERVER['QUERY_STRING']) ? '&'.str_replace('page='.$currentPage, '', $_SERVER['QUERY_STRING']) : ''; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?page=<?php echo $currentPage+1; ?><?php echo !empty($_SERVER['QUERY_STRING']) ? '&'.str_replace('page='.$currentPage, '', $_SERVER['QUERY_STRING']) : ''; ?>">›</a>
                        <a href="?page=<?php echo $totalPages; ?><?php echo !empty($_SERVER['QUERY_STRING']) ? '&'.str_replace('page='.$currentPage, '', $_SERVER['QUERY_STRING']) : ''; ?>">»</a>
                    <?php else: ?>
                        <span class="disabled">›</span>
                        <span class="disabled">»</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    
    <script>
        // Toggle mobile menu
        document.getElementById('menu-icon').addEventListener('click', function() {
            const navList = document.getElementById('nav-list');
            navList.classList.toggle('active');
        });
        
        // Form auto-submit on filter change
        document.querySelectorAll('.filter-form select').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script> -->
    <!--  Footer -->
    <?php include 'includes/footer.php'; ?>
    
    
    <script src="js/navbar.js"></script>
</body>
</html>