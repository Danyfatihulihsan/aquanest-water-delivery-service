<?php
// admin_orders.php
session_start();

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/functions.php';

// Handle AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    // Update order status
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
        try {
            $sql = "UPDATE orders SET status = :status WHERE order_id = :order_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':status', $_POST['status']);
            $stmt->bindParam(':order_id', $_POST['order_id']);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
    
    // Verify payment
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_payment') {
        try {
            $conn->beginTransaction();
            
            // Update payment status
            $sql = "UPDATE orders SET payment_status = 'verified', status = 'processing' WHERE order_id = :order_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':order_id', $_POST['order_id']);
            $stmt->execute();
            
            // Auto assign courier
            $sql = "SELECT courier_id FROM couriers WHERE status = 'available' AND is_active = 1 ORDER BY RAND() LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $courier = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($courier) {
                // Create delivery record
                $sql = "INSERT INTO courier_deliveries (courier_id, order_id, status) VALUES (:courier_id, :order_id, 'assigned')";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':courier_id', $courier['courier_id']);
                $stmt->bindParam(':order_id', $_POST['order_id']);
                $stmt->execute();
                
                // Update courier status
                $sql = "UPDATE couriers SET status = 'on_delivery' WHERE courier_id = :courier_id";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':courier_id', $courier['courier_id']);
                $stmt->execute();
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Pembayaran terverifikasi']);
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
    
    // Get order details
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_order_detail') {
        try {
            if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
                echo json_encode(['success' => false, 'message' => 'Order ID tidak diberikan']);
                exit();
            }
            
            // Debug log
            error_log("Getting order detail for ID: " . $_GET['order_id']);
            
            // Get order info
            $sql = "SELECT o.*, c.name as customer_name, c.phone, c.email, c.address,
                    p.bank_name, p.account_number, p.account_name, p.proof_image,
                    cr.courier_name, cr.courier_phone
                    FROM orders o
                    JOIN customers c ON o.customer_id = c.customer_id
                    LEFT JOIN payment_proofs p ON o.order_id = p.order_id
                    LEFT JOIN courier_deliveries cd ON o.order_id = cd.order_id
                    LEFT JOIN couriers cr ON cd.courier_id = cr.courier_id
                    WHERE o.order_id = :order_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':order_id', $_GET['order_id'], PDO::PARAM_INT);
            $stmt->execute();
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                echo json_encode(['success' => false, 'message' => 'Order tidak ditemukan']);
                exit();
            }
            
            // Get order items
            $sql = "SELECT oi.*, p.product_name, p.image_url
                    FROM order_items oi
                    JOIN products p ON oi.product_id = p.product_id
                    WHERE oi.order_id = :order_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':order_id', $_GET['order_id'], PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'order' => $order, 'items' => $items]);
        } catch (Exception $e) {
            error_log("Error in get_order_detail: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$paymentFilter = $_GET['payment'] ?? '';
$searchTerm = $_GET['search'] ?? '';
$dateFilter = $_GET['date'] ?? '';

// Build query
$sql = "SELECT o.*, c.name as customer_name, c.phone as customer_phone,
        cr.courier_name, cd.status as delivery_status
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id
        LEFT JOIN courier_deliveries cd ON o.order_id = cd.order_id
        LEFT JOIN couriers cr ON cd.courier_id = cr.courier_id
        WHERE 1=1";

$params = [];

if ($statusFilter) {
    $sql .= " AND o.status = :status";
    $params[':status'] = $statusFilter;
}

if ($paymentFilter) {
    $sql .= " AND o.payment_status = :payment";
    $params[':payment'] = $paymentFilter;
}

if ($searchTerm) {
    $sql .= " AND (o.order_id LIKE :search OR c.name LIKE :search OR c.phone LIKE :search)";
    $params[':search'] = "%$searchTerm%";
}

if ($dateFilter) {
    $sql .= " AND DATE(o.order_date) = :date";
    $params[':date'] = $dateFilter;
}

$sql .= " ORDER BY o.order_date DESC";

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [
    'total' => count($orders),
    'pending' => count(array_filter($orders, function($o) { return isset($o['status']) && $o['status'] == 'pending'; })),
    'processing' => count(array_filter($orders, function($o) { return isset($o['status']) && $o['status'] == 'processing'; })),
    'delivered' => count(array_filter($orders, function($o) { return isset($o['status']) && $o['status'] == 'delivered'; }))
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pesanan - Admin Aquanest</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Custom styles for better modal appearance */
        .modal-backdrop {
            backdrop-filter: blur(5px);
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navbar Admin -->
    <nav class="bg-blue-800 text-white shadow-lg">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <h1 class="text-xl font-bold">Admin Panel - Aquanest</h1>
                <div class="flex gap-4">
                    <a href="dashboard.php" class="hover:text-blue-200">Dashboard</a>
                    <a href="admin_orders.php" class="text-blue-200">Pesanan</a>
                    <a href="admin_couriers.php" class="hover:text-blue-200">Kurir</a>
                    <a href="logout.php" class="hover:text-blue-200">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Manajemen Pesanan</h2>
            <p class="text-gray-600">Kelola semua pesanan pelanggan</p>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Pesanan</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total']; ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fas fa-shopping-bag text-blue-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Menunggu</p>
                        <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['pending']; ?></p>
                    </div>
                    <div class="bg-yellow-100 p-3 rounded-full">
                        <i class="fas fa-clock text-yellow-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Diproses</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo $stats['processing']; ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fas fa-cog text-blue-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Selesai</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo $stats['delivered']; ?></p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="GET" class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <input type="text" name="search" placeholder="Cari ID/Nama/Telepon..." 
                           value="<?php echo htmlspecialchars($searchTerm); ?>"
                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <select name="status" class="px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        <option value="">Semua Status</option>
                        <option value="pending" <?php echo $statusFilter == 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                        <option value="processing" <?php echo $statusFilter == 'processing' ? 'selected' : ''; ?>>Diproses</option>
                        <option value="shipped" <?php echo $statusFilter == 'shipped' ? 'selected' : ''; ?>>Dikirim</option>
                        <option value="delivered" <?php echo $statusFilter == 'delivered' ? 'selected' : ''; ?>>Selesai</option>
                        <option value="cancelled" <?php echo $statusFilter == 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                    </select>
                </div>
                <div>
                    <select name="payment" class="px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        <option value="">Semua Pembayaran</option>
                        <option value="pending" <?php echo $paymentFilter == 'pending' ? 'selected' : ''; ?>>Belum Bayar</option>
                        <option value="verified" <?php echo $paymentFilter == 'verified' ? 'selected' : ''; ?>>Terverifikasi</option>
                        <option value="failed" <?php echo $paymentFilter == 'failed' ? 'selected' : ''; ?>>Gagal</option>
                    </select>
                </div>
                <div>
                    <input type="date" name="date" value="<?php echo $dateFilter; ?>" 
                           class="px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-search mr-2"></i>Filter
                </button>
                <a href="admin_orders.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                    Reset
                </a>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pelanggan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pembayaran</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kurir</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">#<?php echo str_pad($order['order_id'] ?? 0, 5, '0', STR_PAD_LEFT); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($order['customer_name'] ?? 'Unknown'); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['customer_phone'] ?? ''); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">Rp <?php echo number_format($order['total_amount'] ?? 0, 0, ',', '.'); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <select onchange="updateOrderStatus(<?php echo $order['order_id']; ?>, this.value)" 
                                        class="text-xs font-semibold rounded px-2 py-1 
                                        <?php 
                                        $currentStatus = $order['status'] ?? 'pending';
                                        $statusClass = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'processing' => 'bg-blue-100 text-blue-800',
                                            'shipped' => 'bg-purple-100 text-purple-800',
                                            'delivered' => 'bg-green-100 text-green-800',
                                            'cancelled' => 'bg-red-100 text-red-800'
                                        ];
                                        echo $statusClass[$currentStatus] ?? 'bg-gray-100 text-gray-800';
                                        ?>">
                                    <option value="pending" <?php echo $currentStatus == 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                                    <option value="processing" <?php echo $currentStatus == 'processing' ? 'selected' : ''; ?>>Diproses</option>
                                    <option value="shipped" <?php echo $currentStatus == 'shipped' ? 'selected' : ''; ?>>Dikirim</option>
                                    <option value="delivered" <?php echo $currentStatus == 'delivered' ? 'selected' : ''; ?>>Selesai</option>
                                    <option value="cancelled" <?php echo $currentStatus == 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                                </select>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php 
                                $paymentStatus = $order['payment_status'] ?? 'pending';
                                if ($paymentStatus == 'pending'): 
                                ?>
                                    <button onclick="verifyPayment(<?php echo $order['order_id']; ?>)" 
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 hover:bg-yellow-200 cursor-pointer">
                                        Verifikasi
                                    </button>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $paymentStatus == 'verified' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $paymentStatus == 'verified' ? 'Terverifikasi' : 'Gagal'; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if (!empty($order['courier_name'])): ?>
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($order['courier_name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo $order['delivery_status'] ?? 'assigned'; ?></div>
                                <?php else: ?>
                                    <span class="text-sm text-gray-500">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo !empty($order['order_date']) ? date('d/m/Y H:i', strtotime($order['order_date'])) : '-'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button type="button" onclick="viewOrderDetail(<?php echo $order['order_id']; ?>)" class="text-blue-600 hover:text-blue-900 hover:underline focus:outline-none">
                                    <i class="fas fa-eye mr-1"></i>Detail
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Order Detail Modal -->
    <div id="orderDetailModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 modal-backdrop">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-lg bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900">Detail Pesanan</h3>
                <button type="button" onclick="closeOrderDetailModal()" class="text-gray-400 hover:text-gray-600 focus:outline-none">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="orderDetailContent" class="max-h-[70vh] overflow-y-auto">
                <!-- Content will be loaded here -->
            </div>
            <div class="mt-4 flex justify-end">
                <button type="button" onclick="closeOrderDetailModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <script>
        // Wait for DOM to be ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM ready');
            
            // Test if jQuery is loaded
            if (typeof jQuery === 'undefined') {
                console.error('jQuery is not loaded!');
                return;
            }
            
            console.log('jQuery version:', jQuery.fn.jquery);
        });

        // Ensure jQuery is loaded before using $
        if (typeof jQuery !== 'undefined') {
            $ = jQuery;
        }

        function updateOrderStatus(orderId, status) {
            console.log('Updating order status:', orderId, status);
            
            $.post('admin_orders.php', {
                action: 'update_status',
                order_id: orderId,
                status: status
            }, function(response) {
                console.log('Update response:', response);
                if (response.success) {
                    location.reload();
                }
            }, 'json').fail(function(xhr, status, error) {
                console.error('Error updating status:', error);
                console.error('Response:', xhr.responseText);
                alert('Error updating status: ' + error);
            });
        }

        function verifyPayment(orderId) {
            if (confirm('Verifikasi pembayaran untuk pesanan ini?')) {
                $.post('admin_orders.php', {
                    action: 'verify_payment',
                    order_id: orderId
                }, function(response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                }, 'json').fail(function(xhr, status, error) {
                    console.error('Error verifying payment:', error);
                    alert('Error verifying payment: ' + error);
                });
            }
        }

        function viewOrderDetail(orderId) {
            console.log('View order detail clicked:', orderId);
            
            // Check if jQuery is available
            if (typeof $ === 'undefined') {
                alert('jQuery is not loaded. Please refresh the page.');
                return;
            }
            
            // Show loading
            const modal = document.getElementById('orderDetailModal');
            const content = document.getElementById('orderDetailContent');
            
            if (!modal || !content) {
                console.error('Modal elements not found');
                return;
            }
            
            content.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
            modal.classList.remove('hidden');
            
            // Make AJAX request
            $.ajax({
                url: 'admin_orders.php',
                type: 'GET',
                data: {
                    action: 'get_order_detail',
                    order_id: orderId
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Response received:', response);
                    
                    if (response.success) {
                        const order = response.order;
                        const items = response.items || [];
                        
                        let itemsHtml = '';
                        if (items.length > 0) {
                            items.forEach(function(item) {
                                itemsHtml += `
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <img src="${item.image_url || '/images/no-image.png'}" alt="${item.product_name}" class="h-10 w-10 rounded object-cover" onerror="this.src='/images/no-image.png'">
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">${item.product_name || 'Unknown Product'}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${item.quantity || 0}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Rp ${Number(item.price || 0).toLocaleString('id-ID')}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Rp ${Number(item.subtotal || 0).toLocaleString('id-ID')}</td>
                                    </tr>
                                `;
                            });
                        } else {
                            itemsHtml = '<tr><td colspan="4" class="text-center py-4">Tidak ada item</td></tr>';
                        }
                        
                        let paymentInfo = '';
                        if (order.proof_image) {
                            paymentInfo = `
                                <div class="mb-4">
                                    <h4 class="font-semibold text-gray-800 mb-2">Bukti Pembayaran:</h4>
                                    <p class="text-sm text-gray-600">Bank: ${order.bank_name || '-'}</p>
                                    <p class="text-sm text-gray-600">No. Rekening: ${order.account_number || '-'}</p>
                                    <p class="text-sm text-gray-600">Atas Nama: ${order.account_name || '-'}</p>
                                    <img src="../uploads/payment_proofs/${order.proof_image}" alt="Bukti Pembayaran" class="mt-2 max-w-xs rounded border" onerror="this.style.display='none'">
                                </div>
                            `;
                        }
                        
                        const htmlContent = `
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-2">Informasi Pelanggan:</h4>
                                    <p class="text-sm text-gray-600">Nama: ${order.customer_name || '-'}</p>
                                    <p class="text-sm text-gray-600">Telepon: ${order.phone || '-'}</p>
                                    <p class="text-sm text-gray-600">Email: ${order.email || '-'}</p>
                                    <p class="text-sm text-gray-600">Alamat: ${order.address || '-'}</p>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-2">Informasi Pesanan:</h4>
                                    <p class="text-sm text-gray-600">Order ID: #${String(order.order_id).padStart(5, '0')}</p>
                                    <p class="text-sm text-gray-600">Tanggal: ${order.order_date ? new Date(order.order_date).toLocaleString('id-ID') : '-'}</p>
                                    <p class="text-sm text-gray-600">Status: ${order.status || 'pending'}</p>
                                    <p class="text-sm text-gray-600">Pembayaran: ${order.payment_status || 'pending'}</p>
                                    ${order.courier_name ? `<p class="text-sm text-gray-600">Kurir: ${order.courier_name} (${order.courier_phone || '-'})</p>` : ''}
                                </div>
                            </div>
                            
                            ${paymentInfo}
                            
                            <div class="mb-4">
                                <h4 class="font-semibold text-gray-800 mb-2">Detail Produk:</h4>
                                <table class="w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        ${itemsHtml}
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="border-t pt-4">
                                <div class="flex justify-between text-lg font-semibold">
                                    <span>Total:</span>
                                    <span>Rp ${Number(order.total_amount || 0).toLocaleString('id-ID')}</span>
                                </div>
                            </div>
                        `;
                        
                        content.innerHTML = htmlContent;
                    } else {
                        content.innerHTML = '<div class="text-center py-4 text-red-600">Error: ' + (response.message || 'Gagal memuat data') + '</div>';
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', status, error);
                    console.error('Response:', xhr.responseText);
                    
                    let errorMessage = 'Error: ' + error;
                    if (xhr.responseText) {
                        try {
                            const jsonError = JSON.parse(xhr.responseText);
                            if (jsonError.message) {
                                errorMessage = jsonError.message;
                            }
                        } catch (e) {
                            // If response is not JSON, show the raw response
                            console.error('Raw response:', xhr.responseText);
                        }
                    }
                    
                    content.innerHTML = '<div class="text-center py-4 text-red-600">' + errorMessage + '</div>';
                }
            });
        }

        function closeOrderDetailModal() {
            const modal = document.getElementById('orderDetailModal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }
        
        // Click handler untuk close button dan backdrop
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('orderDetailModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeOrderDetailModal();
                    }
                });
            }
        });
    </script>
</body>
</html>