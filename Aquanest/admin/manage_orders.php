<?php
/**
 * CSS Cleaner untuk mencegah CSS ditampilkan sebagai teks
 */
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
$page_title = "Kelola Pesanan";

// Filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$payment_filter = isset($_GET['payment']) ? $_GET['payment'] : '';
$payment_method_filter = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Process order operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update order status
    if (isset($_POST['update_status'])) {
        $order_id = $_POST['order_id'];
        $status = $_POST['status'];
        
        try {
            $stmt = $conn->prepare("UPDATE orders SET status = :status WHERE order_id = :id");
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $order_id);
            $stmt->execute();
            
            setFlashMessage('success', 'Status pesanan berhasil diperbarui.');
            redirect('manage_orders.php');
        } catch (PDOException $e) {
            setFlashMessage('danger', 'Gagal memperbarui status pesanan: ' . $e->getMessage());
        }
    }
    
    // Update payment status
    if (isset($_POST['update_payment'])) {
        $order_id = $_POST['order_id'];
        $payment_status = $_POST['payment_status'];
        
        try {
            $stmt = $conn->prepare("UPDATE orders SET payment_status = :payment_status WHERE order_id = :id");
            $stmt->bindParam(':payment_status', $payment_status);
            $stmt->bindParam(':id', $order_id);
            $stmt->execute();
            
            // If payment is verified as paid, also update order status if it's still pending
            if ($payment_status === 'paid' || $payment_status === 'direct_paid') {
                $stmt = $conn->prepare("UPDATE orders SET status = 'confirmed' WHERE order_id = :id AND status = 'pending'");
                $stmt->bindParam(':id', $order_id);
                $stmt->execute();
            }
            
            setFlashMessage('success', 'Status pembayaran berhasil diperbarui.');
            redirect('manage_orders.php');
        } catch (PDOException $e) {
            setFlashMessage('danger', 'Gagal memperbarui status pembayaran: ' . $e->getMessage());
        }
    }
}

// Build query
$query = "SELECT o.*, c.name as customer_name, c.phone 
          FROM orders o 
          JOIN customers c ON o.customer_id = c.customer_id 
          WHERE 1=1";
$params = [];

// Apply filters
if (!empty($status_filter)) {
    $query .= " AND o.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($payment_filter)) {
    $query .= " AND o.payment_status = :payment";
    $params[':payment'] = $payment_filter;
}

if (!empty($payment_method_filter)) {
    $query .= " AND o.payment_method = :payment_method";
    $params[':payment_method'] = $payment_method_filter;
}

if (!empty($date_from)) {
    $query .= " AND DATE(o.order_date) >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(o.order_date) <= :date_to";
    $params[':date_to'] = $date_to;
}

if (!empty($search)) {
    $query .= " AND (c.name LIKE :search OR c.phone LIKE :search OR o.order_id LIKE :search)";
    $params[':search'] = "%$search%";
}

// Order by
$query .= " ORDER BY o.order_date DESC";

// Get orders
try {
    $stmt = $conn->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    setFlashMessage('danger', 'Gagal mengambil data pesanan: ' . $e->getMessage());
    $orders = [];
}

// Get order stats
try {
    // Get total orders
    $stmt = $conn->query("SELECT COUNT(*) as total FROM orders");
    $totalOrders = $stmt->fetch()['total'];
    
    // Get pending orders
    $stmt = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
    $pendingOrders = $stmt->fetch()['count'];
    
    // Get processing orders
    $stmt = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'processing'");
    $processingOrders = $stmt->fetch()['count'];
    
    // Get delivered orders
    $stmt = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'delivered'");
    $deliveredOrders = $stmt->fetch()['count'];
    
    // Get total revenue
    $stmt = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE payment_status IN ('paid', 'direct_paid')");
    $totalRevenue = $stmt->fetch()['total'] ?: 0;
} catch (PDOException $e) {
    $totalOrders = 0;
    $pendingOrders = 0;
    $processingOrders = 0;
    $deliveredOrders = 0;
    $totalRevenue = 0;
}

// Include header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Kelola Pesanan</h1>
    <div class="d-none d-md-block">
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#exportModal">
            <i class="fas fa-file-export me-1"></i> Export Data
        </button>
    </div>
</div>

<?php displayFlashMessage(); ?>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stats-card bg-primary text-white">
            <div class="stats-icon bg-white text-primary">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <h5>Total Pesanan</h5>
            <h2><?php echo $totalOrders; ?></h2>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stats-card bg-warning text-white">
            <div class="stats-icon bg-white text-warning">
                <i class="fas fa-clock"></i>
            </div>
            <h5>Pesanan Tertunda</h5>
            <h2><?php echo $pendingOrders; ?></h2>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stats-card bg-info text-white">
            <div class="stats-icon bg-white text-info">
                <i class="fas fa-truck"></i>
            </div>
            <h5>Dalam Pengiriman</h5>
            <h2><?php echo $processingOrders; ?></h2>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stats-card bg-success text-white">
            <div class="stats-icon bg-white text-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <h5>Pesanan Selesai</h5>
            <h2><?php echo $deliveredOrders; ?></h2>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="card data-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Filter Pesanan</h5>
        <a href="manage_orders.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-sync-alt me-1"></i> Reset Filter
        </a>
    </div>
    <div class="card-body">
        <form method="get" action="manage_orders.php">
            <div class="row g-3">
                <div class="col-md-2 col-sm-6">
                    <label for="status" class="form-label">Status Pesanan</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                        <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Terkonfirmasi</option>
                        <option value="processing" <?php echo $status_filter == 'processing' ? 'selected' : ''; ?>>Diproses</option>
                        <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Terkirim</option>
                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label for="payment" class="form-label">Status Pembayaran</label>
                    <select class="form-select" id="payment" name="payment">
                        <option value="">Semua Status</option>
                        <option value="unpaid" <?php echo $payment_filter == 'unpaid' ? 'selected' : ''; ?>>Belum Bayar</option>
                        <option value="pending" <?php echo $payment_filter == 'pending' ? 'selected' : ''; ?>>Menunggu Verifikasi</option>
                        <option value="paid" <?php echo $payment_filter == 'paid' ? 'selected' : ''; ?>>Sudah Bayar</option>
                        <option value="direct_paid" <?php echo $payment_filter == 'direct_paid' ? 'selected' : ''; ?>>Pembayaran Langsung</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label for="payment_method" class="form-label">Metode Pembayaran</label>
                    <select class="form-select" id="payment_method" name="payment_method">
                        <option value="">Semua Metode</option>
                        <option value="cash" <?php echo $payment_method_filter == 'cash' ? 'selected' : ''; ?>>Tunai saat pengiriman</option>
                        <option value="transfer" <?php echo $payment_method_filter == 'transfer' ? 'selected' : ''; ?>>Transfer Bank</option>
                        <option value="direct" <?php echo $payment_method_filter == 'direct' ? 'selected' : ''; ?>>Pembayaran Langsung</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label for="date_from" class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-2 col-sm-6">
                    <label for="date_to" class="form-label">Hingga Tanggal</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-2 col-sm-12">
                    <label for="search" class="form-label">Pencarian</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="search" name="search" placeholder="Nama/No. Telp" value="<?php echo $search; ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Mobile Export Button -->
<div class="d-md-none mb-4">
    <button class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#exportModal">
        <i class="fas fa-file-export me-1"></i> Export Data Pesanan
    </button>
</div>

<!-- Orders Table -->
<div class="card data-card">
    <div class="card-header">
        <h5 class="mb-0">Daftar Pesanan</h5>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table class="table table-striped admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Pelanggan</th>
                        <th class="d-none d-md-table-cell">Tanggal</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th class="d-none d-md-table-cell">Pembayaran</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo $order['customer_name']; ?><br>
                                    <small class="text-muted"><?php echo $order['phone']; ?></small>
                                </td>
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
                                        case 'direct_paid':
                                            echo '<span class="badge bg-info">Pembayaran Langsung</span>';
                                            break;
                                        default:
                                            echo '<span class="badge bg-secondary">Unknown</span>';
                                    }
                                    ?>
                                    <br>
                                    <small class="text-muted">
                                    <?php 
                                    switch ($order['payment_method']) {
                                        case 'cash':
                                            echo 'Tunai';
                                            break;
                                        case 'transfer':
                                            echo 'Transfer Bank';
                                            break;
                                        case 'direct':
                                            echo 'Langsung';
                                            break;
                                        default:
                                            echo $order['payment_method'];
                                    }
                                    ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_order.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-primary update-status-btn"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#updateStatusModal"
                                                data-id="<?php echo $order['order_id']; ?>"
                                                data-status="<?php echo $order['status']; ?>"
                                                title="Update Status">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-success update-payment-btn"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#updatePaymentModal"
                                                data-id="<?php echo $order['order_id']; ?>"
                                                data-payment="<?php echo $order['payment_status']; ?>"
                                                data-method="<?php echo $order['payment_method']; ?>"
                                                title="Update Pembayaran">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Tidak ada pesanan yang ditemukan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="updateStatusModalLabel">Perbarui Status Pesanan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="manage_orders.php">
                <input type="hidden" id="update_order_id" name="order_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status Pesanan</label>
                        <select class="form-select" id="update_status" name="status" required>
                            <option value="pending">Menunggu</option>
                            <option value="confirmed">Terkonfirmasi</option>
                            <option value="processing">Diproses</option>
                            <option value="delivered">Terkirim</option>
                            <option value="cancelled">Dibatalkan</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Perbarui Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Payment Modal -->
<div class="modal fade" id="updatePaymentModal" tabindex="-1" aria-labelledby="updatePaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="updatePaymentModalLabel">Perbarui Status Pembayaran</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="manage_orders.php">
                <input type="hidden" id="update_payment_order_id" name="order_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="payment_status" class="form-label">Status Pembayaran</label>
                        <select class="form-select" id="update_payment_status" name="payment_status" required>
                            <option value="unpaid">Belum Bayar</option>
                            <option value="pending">Menunggu Verifikasi</option>
                            <option value="paid">Sudah Bayar</option>
                            <option value="direct_paid">Pembayaran Langsung</option>
                        </select>
                    </div>
                    <div class="alert alert-info" id="payment_info">
                        <small><i class="fas fa-info-circle me-1"></i> Mengubah status menjadi "Sudah Bayar" juga akan mengubah status pesanan menjadi "Terkonfirmasi" secara otomatis jika sebelumnya "Menunggu".</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="update_payment" class="btn btn-success">Perbarui Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="exportModalLabel">Export Data Pesanan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="get" action="export_orders.php">
                    <div class="mb-3">
                        <label for="export_format" class="form-label">Format Export</label>
                        <select class="form-select" id="export_format" name="format" required>
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="export_date_from" class="form-label">Dari Tanggal</label>
                        <input type="date" class="form-control" id="export_date_from" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="export_date_to" class="form-label">Hingga Tanggal</label>
                        <input type="date" class="form-control" id="export_date_to" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-download me-2"></i> Download
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Include footer -->
<?php include 'includes/footer.php'; ?>

<!-- Custom JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Update status modal
        const updateStatusButtons = document.querySelectorAll('.update-status-btn');
        updateStatusButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const status = this.getAttribute('data-status');
                
                document.getElementById('update_order_id').value = id;
                document.getElementById('update_status').value = status;
            });
        });
        
        // Update payment modal
        const updatePaymentButtons = document.querySelectorAll('.update-payment-btn');
        updatePaymentButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const payment = this.getAttribute('data-payment');
                const method = this.getAttribute('data-method');
                
                document.getElementById('update_payment_order_id').value = id;
                document.getElementById('update_payment_status').value = payment;
                
                // Show appropriate payment status options based on payment method
                const paymentStatusSelect = document.getElementById('update_payment_status');
                const directPaidOption = paymentStatusSelect.querySelector('option[value="direct_paid"]');
                
                if (method === 'direct') {
                    // For direct payment, enable direct_paid option and update info text
                    directPaidOption.style.display = 'block';
                    document.getElementById('payment_info').innerHTML = '<small><i class="fas fa-info-circle me-1"></i> Ini adalah pembayaran langsung. Pilih "Pembayaran Langsung" untuk mengonfirmasi pembayaran telah diterima.</small>';
                } else if (method === 'transfer') {
                    // For transfer payment, hide direct_paid option
                    directPaidOption.style.display = 'none';
                    document.getElementById('payment_info').innerHTML = '<small><i class="fas fa-info-circle me-1"></i> Pembayaran via transfer bank. Verifikasi bukti transfer sebelum mengubah status menjadi "Sudah Bayar".</small>';
                } else {
                    // For cash payment, hide direct_paid option
                    directPaidOption.style.display = 'none';
                    document.getElementById('payment_info').innerHTML = '<small><i class="fas fa-info-circle me-1"></i> Pembayaran tunai saat pengiriman. Konfirmasi saat pembayaran diterima.</small>';
                }
            });
        });
    });
</script>
<script src="js/dashboard.js"></script>