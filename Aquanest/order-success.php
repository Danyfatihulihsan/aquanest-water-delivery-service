<?php
// Start session
session_start();

// Include database connection
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirect('index.php');
}

// Get order details
$orderId = $_GET['id'];

// Get order details using PDO
$sql = "SELECT o.*, c.name as customer_name, c.email, c.phone, c.address 
        FROM orders o 
        JOIN customers c ON o.customer_id = c.customer_id 
        WHERE o.order_id = :order_id";

$stmt = $conn->prepare($sql);
$stmt->bindParam(":order_id", $orderId, PDO::PARAM_INT);
$stmt->execute();

// Check if order exists
if ($stmt->rowCount() == 0) {
    setFlashMessage('danger', 'Pesanan tidak ditemukan.');
    redirect('index.php');
}

$order = $stmt->fetch(PDO::FETCH_ASSOC);

// Get order items using PDO
$itemSql = "SELECT oi.*, p.name as product_name 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.product_id 
            WHERE oi.order_id = :order_id";

$itemStmt = $conn->prepare($itemSql);
$itemStmt->bindParam(":order_id", $orderId, PDO::PARAM_INT);
$itemStmt->execute();
$orderItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

// Process payment proof upload
if (isset($_POST['upload_payment_proof'])) {
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $filename = $_FILES['payment_proof']['name'];
        $filesize = $_FILES['payment_proof']['size'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (!in_array(strtolower($ext), $allowed)) {
            setFlashMessage('danger', 'Format file tidak didukung. Gunakan format JPG, PNG, atau PDF.');
        } else if ($filesize > 5242880) { // 5 MB limit
            setFlashMessage('danger', 'Ukuran file terlalu besar. Maksimal 5MB.');
        } else {
            $new_filename = 'payment_' . $orderId . '_' . date('YmdHis') . '.' . $ext;
            $upload_path = 'uploads/payments/' . $new_filename;
            
            if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $upload_path)) {
                // Update order payment information using PDO
                $updateSql = "UPDATE orders SET payment_proof = :payment_proof, payment_status = 'pending_verification', payment_date = NOW() WHERE order_id = :order_id";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bindParam(":payment_proof", $new_filename, PDO::PARAM_STR);
                $updateStmt->bindParam(":order_id", $orderId, PDO::PARAM_INT);
                
                if ($updateStmt->execute()) {
                    setFlashMessage('success', 'Bukti pembayaran berhasil diunggah. Pembayaran Anda sedang diverifikasi.');
                    redirect('order-success.php?id=' . $orderId);
                } else {
                    setFlashMessage('danger', 'Terjadi kesalahan saat menyimpan bukti pembayaran.');
                }
            } else {
                setFlashMessage('danger', 'Gagal mengunggah file. Silakan coba lagi.');
            }
        }
    } else {
        setFlashMessage('danger', 'Silakan pilih file bukti pembayaran.');
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan - AirBiru</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/order.css">
    <link href="css/navbar.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Custom styles for order success page */
        .order-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .status-banner {
            background-color: #2b95e9;
            color: white;
            padding: 40px 20px;
            text-align: center;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .status-icon {
            font-size: 50px;
            margin-bottom: 20px;
        }
        
        .order-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .order-card-header {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        
        .order-card-header h2 {
            margin: 0;
            font-size: 20px;
        }
        
        .order-card-body {
            padding: 20px;
        }
        
        .info-section {
            margin-bottom: 30px;
        }
        
        .info-section:last-child {
            margin-bottom: 0;
        }
        
        .info-section h3 {
            margin-bottom: 15px;
            font-size: 18px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .info-item {
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #555;
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
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .table tr:last-child td {
            border-bottom: none;
        }
        
        .table tfoot td {
            font-weight: 600;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .payment-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .payment-card.selected {
            border-color: #2b95e9;
            box-shadow: 0 0 0 1px #2b95e9;
        }
        
        .payment-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .payment-card-icon {
            font-size: 24px;
            margin-right: 10px;
            color: #2b95e9;
        }
        
        .payment-card-title {
            font-weight: 600;
            margin: 0;
        }
        
        .payment-card-description {
            color: #777;
            font-size: 14px;
            margin: 0;
        }
        
        .payment-details {
            margin-top: 20px;
        }
        
        .bank-info {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 15px;
            margin-bottom: 15px;
        }
        
        .form-text {
            color: #777;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 5px;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: #2b95e9;
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background-color: #1a7ad3;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
            border: none;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-outline {
            background-color: white;
            color: #2b95e9;
            border: 1px solid #2b95e9;
        }
        
        .btn-outline:hover {
            background-color: #f0f7ff;
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .payment-methods {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
   <?php include 'includes/navbar.php'; ?>
    
    <!-- Order Success Content -->
    <div class="order-container">
        <!-- Status Banner -->
        <div class="status-banner">
            <div class="status-icon">
                <i class="fas fa-<?php 
                    if ($order['status'] == 'pending') echo 'clock';
                    elseif ($order['status'] == 'processing') echo 'cogs';
                    elseif ($order['status'] == 'shipped') echo 'truck';
                    elseif ($order['status'] == 'delivered') echo 'check-circle';
                    elseif ($order['status'] == 'cancelled') echo 'times-circle';
                    else echo 'info-circle';
                ?>"></i>
            </div>
            <h1>Detail Pesanan #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></h1>
            <p>
                <?php 
                if ($order['status'] == 'pending') echo "Pesanan Anda sedang menunggu konfirmasi";
                elseif ($order['status'] == 'processing') echo "Pesanan Anda sedang diproses";
                elseif ($order['status'] == 'shipped') echo "Pesanan Anda sedang dalam pengiriman";
                elseif ($order['status'] == 'delivered') echo "Pesanan Anda telah diterima";
                elseif ($order['status'] == 'cancelled') echo "Pesanan Anda telah dibatalkan";
                else echo "Status pesanan: " . ucfirst($order['status']);
                ?>
            </p>
            <div class="status-badges">
                <span class="badge badge-<?php 
                    if ($order['payment_status'] == 'unpaid') echo 'danger';
                    elseif ($order['payment_status'] == 'pending_verification') echo 'warning';
                    elseif ($order['payment_status'] == 'paid') echo 'success';
                    else echo 'secondary';
                ?>">
                    <?php 
                    if ($order['payment_status'] == 'unpaid') echo "Belum Dibayar";
                    elseif ($order['payment_status'] == 'pending_verification') echo "Menunggu Verifikasi Pembayaran";
                    elseif ($order['payment_status'] == 'paid') echo "Pembayaran Selesai";
                    else echo ucfirst($order['payment_status']);
                    ?>
                </span>
            </div>
        </div>
        
        <!-- Order Details Card -->
        <div class="order-card">
            <div class="order-card-header">
                <h2>Informasi Pesanan</h2>
            </div>
            <div class="order-card-body">
                <div class="info-grid">
                    <div class="info-section">
                        <h3>Data Pemesan</h3>
                        <div class="info-item">
                            <div class="info-label">Nama</div>
                            <div class="info-value"><?php echo $order['customer_name']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo $order['email'] ?: '-'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Telepon</div>
                            <div class="info-value"><?php echo $order['phone']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Alamat Pengiriman</div>
                            <div class="info-value"><?php echo $order['address']; ?></div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h3>Detail Pesanan</h3>
                        <div class="info-item">
                            <div class="info-label">Nomor Pesanan</div>
                            <div class="info-value">#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Tanggal Pesanan</div>
                            <div class="info-value"><?php echo date('d-m-Y H:i', strtotime($order['order_date'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Status Pesanan</div>
                            <div class="info-value">
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
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Metode Pembayaran</div>
                            <div class="info-value">
                                <?php 
                                if ($order['payment_method'] == 'cash') echo "Tunai saat pengiriman";
                                elseif ($order['payment_method'] == 'transfer') echo "Transfer Bank";
                                elseif ($order['payment_method'] == 'credit_card') echo "Kartu Kredit";
                                elseif ($order['payment_method'] == 'e_wallet') echo "E-Wallet";
                                else echo ucfirst($order['payment_method']);
                                ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Status Pembayaran</div>
                            <div class="info-value">
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
                    </div>
                </div>
                
                <!-- Order Items -->
                <div class="info-section">
                    <h3>Item Pesanan</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Harga</th>
                                <th>Jumlah</th>
                                <th class="text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                            <tr>
                                <td><?php echo $item['product_name']; ?></td>
                                <td><?php echo formatRupiah($item['price']); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td class="text-right"><?php echo formatRupiah($item['subtotal']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-right">Total</td>
                                <td class="text-right"><?php echo formatRupiah($order['total_amount']); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <?php if (!empty($order['notes'])): ?>
                <div class="info-section">
                    <h3>Catatan</h3>
                    <p><?php echo $order['notes']; ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Payment Section (if not paid) -->
                <?php if ($order['payment_status'] == 'unpaid'): ?>
                <div class="info-section">
                    <h3>Pembayaran</h3>
                    <?php if ($order['payment_method'] == 'transfer'): ?>
                    <div class="bank-info">
                        <p><strong>Silakan transfer ke rekening berikut:</strong></p>
                        <p>Bank BCA<br>
                        No. Rekening: 1234567890<br>
                        Atas Nama: PT AirBiru Indonesia</p>
                        <p>Jumlah: <strong><?php echo formatRupiah($order['total_amount']); ?></strong></p>
                    </div>
                    
                    <form action="" method="post" enctype="multipart/form-data">
                        <div>
                            <label for="payment_proof" class="form-label">Upload Bukti Transfer</label>
                            <input type="file" id="payment_proof" name="payment_proof" class="form-control" accept="image/*,application/pdf" required>
                            <div class="form-text">Format yang didukung: JPG, PNG, PDF. Maks 5MB.</div>
                        </div>
                        <button type="submit" name="upload_payment_proof" class="btn btn-primary">Upload Bukti Pembayaran</button>
                    </form>
                    <?php else: ?>
                    <p>Pembayaran akan dilakukan saat pengiriman (Cash on Delivery).</p>
                    <?php endif; ?>
                </div>
                <?php elseif ($order['payment_status'] == 'pending_verification'): ?>
                <div class="info-section">
                    <h3>Status Pembayaran</h3>
                    <div class="alert alert-warning">
                        <p><i class="fas fa-info-circle"></i> Pembayaran Anda sedang diverifikasi. Proses verifikasi biasanya membutuhkan waktu 1-2 jam pada jam kerja.</p>
                    </div>
                    
                    <?php if (!empty($order['payment_proof'])): ?>
                    <div class="mt-4">
                        <h4>Bukti Pembayaran</h4>
                        <div class="text-center">
                            <img src="uploads/payments/<?php echo $order['payment_proof']; ?>" alt="Bukti Pembayaran" style="max-width: 100%; max-height: 300px; border-radius: 5px;">
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php elseif ($order['payment_status'] == 'paid'): ?>
                <div class="info-section">
                    <h3>Status Pembayaran</h3>
                    <div class="alert alert-success">
                        <p><i class="fas fa-check-circle"></i> Pembayaran telah dikonfirmasi pada <?php echo date('d-m-Y H:i', strtotime($order['payment_date'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Action Buttons -->
                <div class="info-section">
                    <div style="display: flex; gap: 10px;">
                        <a href="my-orders.php" class="btn btn-outline">Kembali ke Daftar Pesanan</a>
                        <a href="javascript:window.print()" class="btn btn-outline">Cetak Pesanan</a>
                        <?php if ($order['status'] == 'shipped'): ?>
                        <a href="confirm-receipt.php?id=<?php echo $order['order_id']; ?>" class="btn btn-success">Konfirmasi Penerimaan</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
        // Toggle mobile menu
        document.getElementById('menu-icon').addEventListener('click', function() {
            const navList = document.getElementById('nav-list');
            navList.classList.toggle('active');
        });
    </script>
</body>
</html>
<script src="js/navbar.js"></script>