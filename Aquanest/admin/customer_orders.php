
<?php

class CSSCleaner {
    private static $started = false;
    
    public static function start() {
        if (!self::$started) {
            ob_start();
            self::$started = true;
        }
    }
    
    public static function clean() {
        if (self::$started) {
            $content = ob_get_clean();
            self::$started = false;
            
            // Pattern untuk menghapus CSS yang tidak diinginkan
            $pattern = '~(/\* Reset and Base Styles \*/.*?\.my-swal-confirm \{[^\}]*\})~s';
            $content = preg_replace($pattern, '', $content);
            
            echo $content;
        }
    }
}

// Mulai buffering dan register cleanup function
CSSCleaner::start();
register_shutdown_function(['CSSCleaner', 'clean']);

// Start session
session_start();

// Include database connection and functions
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Set page title
$page_title = "Pesanan Pelanggan";

// Check if customer ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('danger', 'ID Pelanggan tidak valid.');
    redirect('manage_customers.php');
}

// Get customer details
$customerId = $_GET['id'];
try {
    $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = :id");
    $stmt->bindParam(':id', $customerId);
    $stmt->execute();
    $customer = $stmt->fetch();
    
    if (!$customer) {
        setFlashMessage('danger', 'Pelanggan tidak ditemukan.');
        redirect('manage_customers.php');
    }
} catch (PDOException $e) {
    setFlashMessage('danger', 'Gagal mengambil data pelanggan: ' . $e->getMessage());
    redirect('manage_customers.php');
}

// Get customer orders
try {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE customer_id = :id ORDER BY order_date DESC");
    $stmt->bindParam(':id', $customerId);
    $stmt->execute();
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    setFlashMessage('danger', 'Gagal mengambil data pesanan: ' . $e->getMessage());
    $orders = [];
}

// Get customer spending
try {
    $stmt = $conn->prepare("SELECT SUM(total_amount) as total_spent FROM orders WHERE customer_id = :id AND payment_status = 'paid'");
    $stmt->bindParam(':id', $customerId);
    $stmt->execute();
    $totalSpent = $stmt->fetch()['total_spent'] ?? 0;
} catch (PDOException $e) {
    $totalSpent = 0;
}

// Get order count
$orderCount = count($orders);

// Get latest order
$latestOrder = $orderCount > 0 ? $orders[0] : null;

// Get order status counts
try {
    // Pending orders
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE customer_id = :id AND status = 'pending'");
    $stmt->bindParam(':id', $customerId);
    $stmt->execute();
    $pendingOrders = $stmt->fetch()['count'];
    
    // Delivered orders
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE customer_id = :id AND status = 'delivered'");
    $stmt->bindParam(':id', $customerId);
    $stmt->execute();
    $deliveredOrders = $stmt->fetch()['count'];
    
} catch (PDOException $e) {
    $pendingOrders = 0;
    $deliveredOrders = 0;
}

// Include header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Pesanan Pelanggan: <?php echo $customer['name']; ?></h1>
    <a href="manage_customers.php" class="btn btn-sm btn-outline-secondary d-none d-md-inline-block">
        <i class="fas fa-arrow-left me-1"></i> Kembali
    </a>
</div>

<?php displayFlashMessage(); ?>

<!-- Mobile Back Button -->
<div class="d-md-none mb-4">
    <a href="manage_customers.php" class="btn btn-outline-secondary w-100">
        <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar Pelanggan
    </a>
</div>

<!-- Customer Info -->
<div class="row mb-4">
    <div class="col-md-4 mb-4">
        <div class="card data-card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Informasi Pelanggan</h5>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-5 col-lg-4 fw-bold">ID:</div>
                    <div class="col-7 col-lg-8"><?php echo $customer['customer_id']; ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 col-lg-4 fw-bold">Nama:</div>
                    <div class="col-7 col-lg-8"><?php echo $customer['name']; ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 col-lg-4 fw-bold">Email:</div>
                    <div class="col-7 col-lg-8"><?php echo $customer['email'] ? $customer['email'] : '-'; ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 col-lg-4 fw-bold">Telepon:</div>
                    <div class="col-7 col-lg-8">
                        <a href="tel:<?php echo $customer['phone']; ?>" class="text-decoration-none">
                            <?php echo $customer['phone']; ?>
                        </a>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 col-lg-4 fw-bold">Alamat:</div>
                    <div class="col-7 col-lg-8"><?php echo $customer['address']; ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 col-lg-4 fw-bold">Bergabung:</div>
                    <div class="col-7 col-lg-8"><?php echo date('d-m-Y', strtotime($customer['created_at'])); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8 mb-4">
        <div class="row">
            <div class="col-md-4 col-sm-6 mb-4">
                <div class="stats-card bg-info text-white">
                    <div class="stats-icon bg-white text-info">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h5>Total Pesanan</h5>
                    <h2><?php echo $orderCount; ?></h2>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-4">
                <div class="stats-card bg-success text-white">
                    <div class="stats-icon bg-white text-success">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <h5>Total Belanja</h5>
                    <h2><?php echo formatRupiah($totalSpent); ?></h2>
                </div>
            </div>
            <div class="col-md-4 col-sm-12 mb-4">
                <div class="stats-card bg-warning text-white">
                    <div class="stats-icon bg-white text-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h5>Pesanan Tertunda</h5>
                    <h2><?php echo $pendingOrders; ?></h2>
                </div>
            </div>
        </div>
        
        <!-- Latest Order -->
        <?php if ($latestOrder): ?>
            <div class="card data-card">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Pesanan Terakhir</h5>
                    <span class="badge bg-white text-success"><?php echo date('d-m-Y', strtotime($latestOrder['order_date'])); ?></span>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="row mb-2">
                                <div class="col-5 fw-bold">ID Pesanan:</div>
                                <div class="col-7">#<?php echo str_pad($latestOrder['order_id'], 6, '0', STR_PAD_LEFT); ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold">Tanggal:</div>
                                <div class="col-7"><?php echo date('d-m-Y H:i', strtotime($latestOrder['order_date'])); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="row mb-2">
                                <div class="col-5 fw-bold">Total:</div>
                                <div class="col-7"><?php echo formatRupiah($latestOrder['total_amount']); ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold">Status:</div>
                                <div class="col-7">
                                    <?php
                                    switch ($latestOrder['status']) {
                                        case 'pending':
                                            echo '<span class="badge bg-warning">Menunggu</span>';
                                            break;
                                        case 'confirmed':
                                            echo '<span class="badge bg-info">Terkonfirmasi</span>';
                                            break;
                                        case 'processing':
                                            echo '<span class="badge bg-primary">Diproses</span>';
                                            break;
                                        case 'delivered':
                                            echo '<span class="badge bg-success">Terkirim</span>';
                                            break;
                                        case 'cancelled':
                                            echo '<span class="badge bg-danger">Dibatalkan</span>';
                                            break;
                                        default:
                                            echo '<span class="badge bg-secondary">Unknown</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-grid d-md-flex justify-content-md-end">
                        <a href="view_order.php?id=<?php echo $latestOrder['order_id']; ?>" class="btn btn-primary">
                            <i class="fas fa-eye me-1"></i> Lihat Detail
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Order History -->
<div class="card data-card mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Riwayat Pesanan</h5>
        <span class="badge bg-white text-primary"><?php echo $orderCount; ?> Pesanan</span>
    </div>
    <div class="card-body">
        <?php if (count($orders) > 0): ?>
            <div class="table-container">
                <table class="table table-striped admin-table">
                    <thead>
                        <tr>
                            <th>ID Pesanan</th>
                            <th class="d-none d-md-table-cell">Tanggal</th>
                            <th>Total</th>
                            <th>Status Pesanan</th>
                            <th class="d-none d-md-table-cell">Status Pembayaran</th>
                            <th class="d-none d-md-table-cell">Metode Pembayaran</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td class="d-none d-md-table-cell"><?php echo date('d-m-Y H:i', strtotime($order['order_date'])); ?></td>
                                <td><?php echo formatRupiah($order['total_amount']); ?></td>
                                <td>
                                    <?php
                                    switch ($order['status']) {
                                        case 'pending':
                                            echo '<span class="badge bg-warning">Menunggu</span>';
                                            break;
                                        case 'confirmed':
                                            echo '<span class="badge bg-info">Terkonfirmasi</span>';
                                            break;
                                        case 'processing':
                                            echo '<span class="badge bg-primary">Diproses</span>';
                                            break;
                                        case 'delivered':
                                            echo '<span class="badge bg-success">Terkirim</span>';
                                            break;
                                        case 'cancelled':
                                            echo '<span class="badge bg-danger">Dibatalkan</span>';
                                            break;
                                        default:
                                            echo '<span class="badge bg-secondary">Unknown</span>';
                                    }
                                    ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php
                                    switch ($order['payment_status']) {
                                        case 'unpaid':
                                            echo '<span class="badge bg-danger">Belum Bayar</span>';
                                            break;
                                        case 'pending':
                                            echo '<span class="badge bg-warning">Menunggu Verifikasi</span>';
                                            break;
                                        case 'paid':
                                            echo '<span class="badge bg-success">Sudah Bayar</span>';
                                            break;
                                        default:
                                            echo '<span class="badge bg-secondary">Unknown</span>';
                                    }
                                    ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo ($order['payment_method'] == 'cash') ? 'Tunai' : 'Transfer'; ?>
                                </td>
                                <td>
                                    <a href="view_order.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <div class="mb-3">
                    <i class="fas fa-shopping-cart text-muted fa-4x"></i>
                </div>
                <p class="mb-0">Pelanggan belum memiliki pesanan.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Include footer -->
<?php include 'includes/footer.php'; ?>