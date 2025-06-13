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
$page_title = "Detail Pesanan";

// Check if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('danger', 'ID Pesanan tidak valid.');
    redirect('manage_orders.php');
}


function debug_query($query, $params = []) {
    echo "<pre>";
    echo "Query: " . $query . "\n";
    echo "Params: " . print_r($params, true);
    echo "</pre>";
}

// Get order details
$orderId = $_GET['id'];
$order = getOrderById($conn, $orderId);

// If order not found
if (!$order) {
    setFlashMessage('danger', 'Pesanan tidak ditemukan.');
    redirect('manage_orders.php');
}

// Get order items
$orderItems = getOrderItems($conn, $orderId);

// Process order operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update order status
    if (isset($_POST['update_status'])) {
        $status = $_POST['status'];
        
        try {
            $stmt = $conn->prepare("UPDATE orders SET status = :status WHERE order_id = :id");
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $orderId);
            $stmt->execute();
            
            setFlashMessage('success', 'Status pesanan berhasil diperbarui.');
            redirect('view_order.php?id=' . $orderId);
        } catch (PDOException $e) {
            setFlashMessage('danger', 'Gagal memperbarui status pesanan: ' . $e->getMessage());
        }
    }
    
    // Update payment status
    if (isset($_POST['update_payment'])) {
        $payment_status = $_POST['payment_status'];
        
        try {
            $stmt = $conn->prepare("UPDATE orders SET payment_status = :payment_status WHERE order_id = :id");
            $stmt->bindParam(':payment_status', $payment_status);
            $stmt->bindParam(':id', $orderId);
            $stmt->execute();
            
            // If payment is verified as paid, also update order status if it's still pending
            if ($payment_status === 'paid' || $payment_status === 'direct_paid') {
                $stmt = $conn->prepare("UPDATE orders SET status = 'confirmed' WHERE order_id = :id AND status = 'pending'");
                $stmt->bindParam(':id', $orderId);
                $stmt->execute();
            }
            
            setFlashMessage('success', 'Status pembayaran berhasil diperbarui.');
            redirect('view_order.php?id=' . $orderId);
        } catch (PDOException $e) {
            setFlashMessage('danger', 'Gagal memperbarui status pembayaran: ' . $e->getMessage());
        }
    }
}

// Include header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Detail Pesanan #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></h1>
    <div class="d-none d-md-flex">
        <a href="manage_orders.php" class="btn btn-sm btn-outline-secondary me-2">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
        <a href="javascript:window.print()" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-print me-1"></i> Cetak
        </a>
    </div>
</div>

<?php displayFlashMessage(); ?>

<!-- Mobile Action Buttons -->
<div class="d-flex d-md-none gap-2 mb-4">
    <a href="manage_orders.php" class="btn btn-sm btn-outline-secondary flex-grow-1">
        <i class="fas fa-arrow-left me-1"></i> Kembali
    </a>
    <a href="javascript:window.print()" class="btn btn-sm btn-outline-secondary flex-grow-1">
        <i class="fas fa-print me-1"></i> Cetak
    </a>
</div>

<!-- Order Details -->
<div class="row mb-4">
    <div class="col-md-6 mb-4">
        <div class="card data-card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Informasi Pesanan</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-5 col-md-4 fw-bold">ID Pesanan:</div>
                    <div class="col-7 col-md-8">#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></div>
                </div>
                <div class="row mt-2">
                    <div class="col-5 col-md-4 fw-bold">Tanggal Pesanan:</div>
                    <div class="col-7 col-md-8"><?php echo date('d-m-Y H:i', strtotime($order['order_date'])); ?></div>
                </div>
                <div class="row mt-2">
                    <div class="col-5 col-md-4 fw-bold">Status Pesanan:</div>
                    <div class="col-7 col-md-8">
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
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-5 col-md-4 fw-bold">Total Pembayaran:</div>
                    <div class="col-7 col-md-8"><?php echo formatRupiah($order['total_amount']); ?></div>
                </div>
                <div class="row mt-2">
                    <div class="col-5 col-md-4 fw-bold">Metode Pembayaran:</div>
                    <div class="col-7 col-md-8">
                        <?php 
                        switch ($order['payment_method']) {
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
                                echo $order['payment_method'];
                        }
                        ?>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-5 col-md-4 fw-bold">Status Pembayaran:</div>
                    <div class="col-7 col-md-8">
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
                    </div>
                </div>
                <?php if (!empty($order['notes'])): ?>
                    <div class="row mt-2">
                        <div class="col-5 col-md-4 fw-bold">Catatan:</div>
                        <div class="col-7 col-md-8"><?php echo $order['notes']; ?></div>
                    </div>
                <?php endif; ?>

                <div class="mt-4">
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                            <i class="fas fa-sync-alt me-1"></i> Ubah Status
                        </button>
                        
                        <!-- Payment status update button for all payment methods -->
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#updatePaymentModal">
                            <i class="fas fa-money-bill-wave me-1"></i> Ubah Status Pembayaran
                        </button>

                        <!-- Tombol Kirim Notifikasi WhatsApp -->
                        <!-- <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#sendNotificationModal">
                            <i class="fab fa-whatsapp me-1"></i> Kirim Notifikasi WhatsApp
                        </button> -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card data-card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Informasi Pelanggan</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-5 col-md-4 fw-bold">Nama:</div>
                    <div class="col-7 col-md-8"><?php echo $order['customer_name']; ?></div>
                </div>
                <div class="row mt-2">
                    <div class="col-5 col-md-4 fw-bold">Telepon:</div>
                    <div class="col-7 col-md-8">
                        <a href="tel:<?php echo $order['phone']; ?>" class="text-decoration-none">
                            <?php echo $order['phone']; ?>
                        </a>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-5 col-md-4 fw-bold">Alamat:</div>
                    <div class="col-7 col-md-8"><?php echo $order['address']; ?></div>
                </div>
                
                <div class="mt-4">
                    <a href="customer_orders.php?id=<?php echo $order['customer_id']; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-list me-1"></i> Lihat Semua Pesanan Pelanggan
                    </a>
                </div>
            </div>
        </div>
        
        <?php if (!empty($order['payment_proof'])): ?>
        <div class="card data-card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Bukti Pembayaran</h5>
            </div>
            <div class="card-body text-center">
                <img src="../uploads/bukti_pembayaran/<?php echo $order['payment_proof']; ?>" alt="Bukti Pembayaran" class="img-fluid mb-3 payment-proof-img" style="max-height: 300px;">
                <p class="mb-0"><small class="text-muted">Diunggah pada: <?php echo date('d-m-Y H:i', strtotime($order['order_date'])); ?></small></p>
            </div>
        </div>
        <?php elseif ($order['payment_method'] == 'direct' && $order['payment_status'] == 'direct_paid'): ?>
        <div class="card data-card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Informasi Pembayaran Langsung</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <p class="mb-0"><i class="fas fa-check-circle me-1"></i> Pembayaran langsung telah dikonfirmasi.</p>
                </div>
                <p class="mb-0">Pembayaran dilakukan secara langsung dan telah diverifikasi oleh admin.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Order Items -->
<div class="card data-card mb-4">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">Item Pesanan</h5>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table class="table table-striped admin-table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Harga Satuan</th>
                        <th>Jumlah</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                    <tr>
                        <td><?php echo $item['product_name']; ?></td>
                        <td><?php echo formatRupiah($item['price']); ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td><?php echo formatRupiah($item['subtotal']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-end fw-bold">Total:</td>
                        <td class="fw-bold"><?php echo formatRupiah($order['total_amount']); ?></td>
                    </tr>
                </tfoot>
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
            <form method="post" action="view_order.php?id=<?php echo $order['order_id']; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status Pesanan</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                            <option value="confirmed" <?php echo $order['status'] == 'confirmed' ? 'selected' : ''; ?>>Terkonfirmasi</option>
                            <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Diproses</option>
                            <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Terkirim</option>
                            <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
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
            <form method="post" action="view_order.php?id=<?php echo $order['order_id']; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="payment_status" class="form-label">Status Pembayaran</label>
                        <select class="form-select" id="payment_status" name="payment_status" required>
                            <option value="unpaid" <?php echo $order['payment_status'] == 'unpaid' ? 'selected' : ''; ?>>Belum Bayar</option>
                            <option value="pending" <?php echo $order['payment_status'] == 'pending' ? 'selected' : ''; ?>>Menunggu Verifikasi</option>
                            <option value="paid" <?php echo $order['payment_status'] == 'paid' ? 'selected' : ''; ?>>Sudah Bayar</option>
                            <?php if ($order['payment_method'] == 'direct'): ?>
                            <option value="direct_paid" <?php echo $order['payment_status'] == 'direct_paid' ? 'selected' : ''; ?>>Pembayaran Langsung</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <?php if ($order['payment_method'] == 'direct'): ?>
                        <small><i class="fas fa-info-circle me-1"></i> Ini adalah pembayaran langsung. Pilih "Pembayaran Langsung" untuk mengonfirmasi pembayaran telah diterima.</small>
                        <?php elseif ($order['payment_method'] == 'transfer'): ?>
                        <small><i class="fas fa-info-circle me-1"></i> Pembayaran via transfer bank. Verifikasi bukti transfer sebelum mengubah status menjadi "Sudah Bayar".</small>
                        <?php else: ?>
                        <small><i class="fas fa-info-circle me-1"></i> Pembayaran tunai saat pengiriman. Konfirmasi saat pembayaran diterima.</small>
                        <?php endif; ?>
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

<!-- Send WhatsApp Notification Modal -->
<div class="modal fade" id="sendNotificationModal" tabindex="-1" aria-labelledby="sendNotificationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="sendNotificationModalLabel">Kirim Notifikasi WhatsApp</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="send_notification.php">
                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                <input type="hidden" name="phone" value="<?php echo $order['phone']; ?>">
                
                <div class="modal-body">
                    <p>Kirim notifikasi WhatsApp kepada <strong><?php echo $order['customer_name']; ?></strong> (<?php echo $order['phone']; ?>) mengenai status pesanan.</p>
                    
                    <div class="mb-3">
                        <label for="notification_status" class="form-label">Tipe Notifikasi</label>
                        <select class="form-select" id="notification_status" name="status" required>
                            <option value="confirmed">Pesanan Dikonfirmasi</option>
                            <option value="processing">Pesanan Dalam Perjalanan</option>
                            <option value="delivered">Pesanan Telah Sampai</option>
                            <option value="payment_confirmed">Pembayaran Dikonfirmasi</option>
                            <?php if ($order['payment_method'] == 'direct'): ?>
                            <option value="direct_payment">Pembayaran Langsung Dikonfirmasi</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <small><i class="fas fa-info-circle me-1"></i> Pastikan nomor telepon yang dimasukkan dapat menerima pesan WhatsApp.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="send_notification" class="btn btn-success">
                        <i class="fab fa-whatsapp me-1"></i> Kirim Notifikasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Custom CSS for mobile view -->
<style>
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


<!-- Include footer -->
<?php include 'includes/footer.php'; ?>