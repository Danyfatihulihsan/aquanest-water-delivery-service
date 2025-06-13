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
$page_title = "Verifikasi Pembayaran";

// Initialize filters
$filters = [
    'payment_method' => isset($_GET['payment_method']) ? $_GET['payment_method'] : '',
    'payment_status' => isset($_GET['payment_status']) ? $_GET['payment_status'] : 'pending',
    'start_date' => isset($_GET['start_date']) ? $_GET['start_date'] : '',
    'end_date' => isset($_GET['end_date']) ? $_GET['end_date'] : '',
    'search' => isset($_GET['search']) ? $_GET['search'] : ''
];

// Process payment verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_payment'])) {
        $orderId = $_POST['order_id'];
        $status = $_POST['status'];
        
        try {
            $stmt = $conn->prepare("UPDATE orders SET payment_status = :status WHERE order_id = :id");
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $orderId);
            $stmt->execute();
            
            // If payment is verified, also update order status if it's still pending
            if ($status === 'paid') {
                $stmt = $conn->prepare("UPDATE orders SET status = 'confirmed' WHERE order_id = :id AND status = 'pending'");
                $stmt->bindParam(':id', $orderId);
                $stmt->execute();
            }
            
            setFlashMessage('success', 'Status pembayaran berhasil diperbarui.');
        } catch (PDOException $e) {
            setFlashMessage('danger', 'Gagal memperbarui status pembayaran: ' . $e->getMessage());
        }
    }
}

// Build SQL query with filters
$sql = "SELECT o.*, c.name as customer_name, c.phone 
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id 
        WHERE 1=1";

$params = [];

// Apply filters
if (!empty($filters['payment_method'])) {
    $sql .= " AND o.payment_method = :payment_method";
    $params[':payment_method'] = $filters['payment_method'];
}

if (!empty($filters['payment_status'])) {
    $sql .= " AND o.payment_status = :payment_status";
    $params[':payment_status'] = $filters['payment_status'];
}

if (!empty($filters['start_date'])) {
    $sql .= " AND o.order_date >= :start_date";
    $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
}

if (!empty($filters['end_date'])) {
    $sql .= " AND o.order_date <= :end_date";
    $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
}

if (!empty($filters['search'])) {
    $sql .= " AND (c.name LIKE :search OR c.phone LIKE :search OR o.order_id LIKE :search)";
    $params[':search'] = '%' . $filters['search'] . '%';
}

// Order by
$sql .= " ORDER BY o.order_date DESC";

try {
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $payments = $stmt->fetchAll();
} catch (PDOException $e) {
    setFlashMessage('danger', 'Gagal mengambil data pembayaran: ' . $e->getMessage());
    $payments = [];
}

// Include header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">Verifikasi Pembayaran</h1>
        <p class="text-muted mb-0">Kelola dan verifikasi pembayaran pelanggan</p>
    </div>
</div>

<?php displayFlashMessage(); ?>

<!-- Filters -->
<div class="card data-card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Filter Pembayaran</h5>
    </div>
    <div class="card-body">
        <form method="get" action="view_payment.php" class="row g-3">
            <div class="col-md-3">
                <label for="payment_method" class="form-label">Metode Pembayaran</label>
                <select class="form-select" id="payment_method" name="payment_method">
                    <option value="">Semua Metode</option>
                    <option value="cash" <?php echo $filters['payment_method'] == 'cash' ? 'selected' : ''; ?>>Tunai saat pengiriman</option>
                    <option value="transfer" <?php echo $filters['payment_method'] == 'transfer' ? 'selected' : ''; ?>>Transfer Bank</option>
                    <option value="direct" <?php echo $filters['payment_method'] == 'direct' ? 'selected' : ''; ?>>Pembayaran Langsung</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="payment_status" class="form-label">Status Pembayaran</label>
                <select class="form-select" id="payment_status" name="payment_status">
                    <option value="">Semua Status</option>
                    <option value="unpaid" <?php echo $filters['payment_status'] == 'unpaid' ? 'selected' : ''; ?>>Belum Bayar</option>
                    <option value="pending" <?php echo $filters['payment_status'] == 'pending' ? 'selected' : ''; ?>>Menunggu Verifikasi</option>
                    <option value="paid" <?php echo $filters['payment_status'] == 'paid' ? 'selected' : ''; ?>>Sudah Bayar</option>
                    <option value="direct_paid" <?php echo $filters['payment_status'] == 'direct_paid' ? 'selected' : ''; ?>>Pembayaran Langsung</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="start_date" class="form-label">Dari Tanggal</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $filters['start_date']; ?>">
            </div>
            
            <div class="col-md-2">
                <label for="end_date" class="form-label">Sampai Tanggal</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $filters['end_date']; ?>">
            </div>
            
            <div class="col-md-2">
                <label for="search" class="form-label">Cari</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo $filters['search']; ?>" placeholder="Nama/ID/Telepon">
            </div>
            
            <div class="col-12 d-flex justify-content-end">
                <a href="view_payment.php" class="btn btn-secondary me-2">Reset Filter</a>
                <button type="submit" class="btn btn-primary">Terapkan Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Payments List -->
<div class="card data-card mb-4">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Pembayaran</h5>
        <span class="badge bg-white text-success"><?php echo count($payments); ?> Pembayaran</span>
    </div>
    <div class="card-body p-0">
        <?php if (count($payments) > 0): ?>
            <div class="table-container">
                <table class="table table-striped admin-table mb-0">
                    <thead>
                        <tr>
                            <th>ID Pesanan</th>
                            <th>Pelanggan</th>
                            <th>Tanggal</th>
                            <th>Total</th>
                            <th>Metode</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>#<?php echo str_pad($payment['order_id'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <div>
                                        <?php echo $payment['customer_name']; ?>
                                        <div class="small text-muted"><?php echo $payment['phone']; ?></div>
                                    </div>
                                </td>
                                <td><?php echo date('d-m-Y H:i', strtotime($payment['order_date'])); ?></td>
                                <td><?php echo formatRupiah($payment['total_amount']); ?></td>
                                <td>
                                    <?php 
                                    switch ($payment['payment_method']) {
                                        case 'cash':
                                            echo 'Tunai saat pengiriman';
                                            break;
                                        case 'transfer':
                                            echo 'Transfer Bank';
                                            break;
                                        case 'direct':
                                            echo 'Pembayaran Langsung';
                                            break;
                                        default:
                                            echo $payment['payment_method'];
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    switch ($payment['payment_status']) {
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
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_order.php?id=<?php echo $payment['order_id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if (!empty($payment['payment_proof']) || $payment['payment_method'] == 'direct'): ?>
                                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#verifyModal<?php echo $payment['order_id']; ?>" title="Verifikasi Pembayaran">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            
                                            <!-- Modal for Payment Verification -->
                                            <div class="modal fade" id="verifyModal<?php echo $payment['order_id']; ?>" tabindex="-1" aria-labelledby="verifyModalLabel<?php echo $payment['order_id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-success text-white">
                                                            <h5 class="modal-title" id="verifyModalLabel<?php echo $payment['order_id']; ?>">Verifikasi Pembayaran #<?php echo str_pad($payment['order_id'], 6, '0', STR_PAD_LEFT); ?></h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="card mb-3">
                                                                        <div class="card-header bg-primary text-white">
                                                                            <h5 class="mb-0">Detail Pesanan</h5>
                                                                        </div>
                                                                        <div class="card-body">
                                                                            <p><strong>ID Pesanan:</strong> #<?php echo str_pad($payment['order_id'], 6, '0', STR_PAD_LEFT); ?></p>
                                                                            <p><strong>Pelanggan:</strong> <?php echo $payment['customer_name']; ?></p>
                                                                            <p><strong>Total:</strong> <?php echo formatRupiah($payment['total_amount']); ?></p>
                                                                            <p><strong>Metode Pembayaran:</strong> 
                                                                                <?php 
                                                                                switch ($payment['payment_method']) {
                                                                                    case 'cash':
                                                                                        echo 'Tunai saat pengiriman';
                                                                                        break;
                                                                                    case 'transfer':
                                                                                        echo 'Transfer Bank';
                                                                                        break;
                                                                                    case 'direct':
                                                                                        echo 'Pembayaran Langsung';
                                                                                        break;
                                                                                    default:
                                                                                        echo $payment['payment_method'];
                                                                                }
                                                                                ?>
                                                                            </p>
                                                                            <p><strong>Status Saat Ini:</strong> 
                                                                                <?php
                                                                                switch ($payment['payment_status']) {
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
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <form method="post" action="view_payment.php">
                                                                        <input type="hidden" name="order_id" value="<?php echo $payment['order_id']; ?>">
                                                                        
                                                                        <div class="mb-3">
                                                                            <label for="status<?php echo $payment['order_id']; ?>" class="form-label">Perbarui Status Pembayaran</label>
                                                                            <select class="form-select" id="status<?php echo $payment['order_id']; ?>" name="status" required>
                                                                                <option value="unpaid" <?php echo $payment['payment_status'] == 'unpaid' ? 'selected' : ''; ?>>Belum Bayar</option>
                                                                                <option value="pending" <?php echo $payment['payment_status'] == 'pending' ? 'selected' : ''; ?>>Menunggu Verifikasi</option>
                                                                                <option value="paid" <?php echo $payment['payment_status'] == 'paid' ? 'selected' : ''; ?>>Sudah Bayar</option>
                                                                                <option value="direct_paid" <?php echo $payment['payment_status'] == 'direct_paid' ? 'selected' : ''; ?>>Pembayaran Langsung</option>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <div class="alert alert-info">
                                                                            <small><i class="fas fa-info-circle me-1"></i> Mengubah status menjadi "Sudah Bayar" juga akan mengubah status pesanan menjadi "Terkonfirmasi" secara otomatis jika sebelumnya "Menunggu".</small>
                                                                        </div>
                                                                        
                                                                        <button type="submit" name="verify_payment" class="btn btn-success w-100">
                                                                            <i class="fas fa-check-circle me-1"></i> Perbarui Status Pembayaran
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                                
                                                                <div class="col-md-6">
                                                                    <?php if (!empty($payment['payment_proof'])): ?>
                                                                        <div class="card">
                                                                            <div class="card-header bg-info text-white">
                                                                                <h5 class="mb-0">Bukti Pembayaran</h5>
                                                                            </div>
                                                                            <div class="card-body text-center">
                                                                                <img src="../uploads/bukti_pembayaran/<?php echo $payment['payment_proof']; ?>" alt="Bukti Pembayaran" class="img-fluid payment-proof-img" style="max-height: 300px;">
                                                                                <p class="mt-2"><small class="text-muted">Diunggah pada: <?php echo date('d-m-Y H:i', strtotime($payment['order_date'])); ?></small></p>
                                                                            </div>
                                                                        </div>
                                                                    <?php elseif ($payment['payment_method'] == 'direct'): ?>
                                                                        <div class="card">
                                                                            <div class="card-header bg-info text-white">
                                                                                <h5 class="mb-0">Informasi Pembayaran Langsung</h5>
                                                                            </div>
                                                                            <div class="card-body">
                                                                                <div class="alert alert-info">
                                                                                    <p><i class="fas fa-info-circle me-1"></i> Ini adalah pesanan dengan pembayaran langsung. Verifikasikan setelah pembayaran diterima.</p>
                                                                                </div>
                                                                                <?php if ($payment['payment_status'] == 'direct_paid'): ?>
                                                                                    <div class="alert alert-success">
                                                                                        <p class="mb-0"><i class="fas fa-check-circle me-1"></i> Pembayaran langsung telah dikonfirmasi.</p>
                                                                                    </div>
                                                                                <?php else: ?>
                                                                                    <div class="alert alert-warning">
                                                                                        <p class="mb-0"><i class="fas fa-exclamation-circle me-1"></i> Pembayaran langsung belum dikonfirmasi.</p>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <div class="alert alert-warning">
                                                                            <p class="mb-0"><i class="fas fa-exclamation-circle me-1"></i> Tidak ada bukti pembayaran yang diunggah.</p>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center p-4">
                <p class="mb-0">Tidak ada pembayaran yang sesuai dengan filter.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Mobile Action Section -->
<div class="d-block d-md-none mb-4">
    <div class="card data-card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Aksi Cepat</h5>
        </div>
        <div class="card-body">
            <div class="d-grid gap-2">
                <a href="manage_orders.php" class="btn btn-primary">
                    <i class="fas fa-shopping-cart me-2"></i> Kelola Pesanan
                </a>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-tachometer-alt me-2"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS for this page -->
<style>
    .table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .admin-table {
        white-space: nowrap;
    }
    
    @media (max-width: 767.98px) {
        .payment-proof-img {
            max-height: 200px;
        }
        
        .payment-method-badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            background-color: #f8f9fa;
            margin-top: 5px;
        }
    }
</style>

<!-- Custom JavaScript for this page -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            boundary: 'window'
        });
    });
    
    // Set default payment status selection on page load
    const paymentStatusSelect = document.getElementById('payment_status');
    if (paymentStatusSelect.value === '' && window.location.href.indexOf('payment_status=') === -1) {
        paymentStatusSelect.value = 'pending';
    }
});
</script>

<?php include 'includes/footer.php'; ?>