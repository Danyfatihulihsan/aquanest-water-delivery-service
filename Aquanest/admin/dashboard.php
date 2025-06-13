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

// Set page title
$page_title = "Dashboard";

// Include database connection and functions
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';


if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    // Verify payment
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_payment') {
        try {
            $order_id = (int)$_POST['order_id'];
            
            $conn->beginTransaction();
            
            // Update payment status
            $sql = "UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE order_id = :order_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':order_id', $order_id);
            $stmt->execute();
            
            // Get order details
            $sql = "SELECT o.*, c.address FROM orders o 
                    JOIN customers c ON o.customer_id = c.customer_id 
                    WHERE o.order_id = :order_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':order_id', $order_id);
            $stmt->execute();
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Auto assign courier
            $sql = "SELECT courier_id, name, phone FROM couriers 
                    WHERE status = 'available' AND is_active = 1 
                    ORDER BY RAND() LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $courier = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($courier) {
                // Update order with courier
                $sql = "UPDATE orders SET courier_id = :courier_id WHERE order_id = :order_id";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':courier_id', $courier['courier_id']);
                $stmt->bindParam(':order_id', $order_id);
                $stmt->execute();
                
                // Update courier status
                $sql = "UPDATE couriers SET status = 'on_delivery' WHERE courier_id = :courier_id";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':courier_id', $courier['courier_id']);
                $stmt->execute();
                
                // Add tracking history
                $sql = "INSERT INTO order_tracking_history (order_id, title, description, is_completed, is_current, created_at) 
                        VALUES (:order_id, :title, :description, 1, 1, NOW())";
                $stmt = $conn->prepare($sql);
                $title = 'Pembayaran Dikonfirmasi';
                $description = 'Pembayaran telah diverifikasi. Kurir: ' . $courier['name'] . ' (' . $courier['phone'] . ')';
                $stmt->bindParam(':order_id', $order_id);
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':description', $description);
                $stmt->execute();
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil diverifikasi']);
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Reject payment
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_payment') {
        try {
            $order_id = (int)$_POST['order_id'];
            $reason = $_POST['reason'];
            
            $sql = "UPDATE orders SET payment_status = 'rejected', rejection_reason = :reason WHERE order_id = :order_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':order_id', $order_id);
            $stmt->bindParam(':reason', $reason);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil ditolak']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
}

// Modifikasi mekanisme caching untuk mencegah error
$use_cache = false;
$dashboard_data = null;
$cache_dir = '../cache';
$cache_file = $cache_dir . '/dashboard_data.json';
$cache_time = 3600; // 1 hour cache

// Cek apakah direktori cache ada dan bisa ditulis
if (is_dir($cache_dir) && is_writable($cache_dir)) {
    $use_cache = true;
    // Cek apakah file cache ada dan masih valid
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {
        $cached_content = file_get_contents($cache_file);
        if ($cached_content !== false) {
            $dashboard_data = json_decode($cached_content, true);
            // Verify cache data integrity
            if (json_last_error() !== JSON_ERROR_NONE) {
                $dashboard_data = null;
                error_log('Dashboard cache corruption: ' . json_last_error_msg());
            }
        }
    }
}

// Jika data cache tidak tersedia, ambil dari database
if ($dashboard_data === null) {
    try {
        // Get total orders
        $stmt = $conn->query("SELECT COUNT(*) as total FROM orders");
        $totalOrders = $stmt->fetch()['total'];
        
        // Get pending orders
        // Get pending payment count
        $stmt = $conn->query("SELECT COUNT(*) as pending_payments FROM orders WHERE payment_status = 'waiting' AND payment_method = 'bank_transfer'");
        $pendingPaymentCount = $stmt->fetch()['pending_payments'];

        // Get pending payment verifications (limit 5)
        $sql = "SELECT o.*, c.name as customer_name, c.phone as customer_phone 
                FROM orders o
                JOIN customers c ON o.customer_id = c.customer_id
                WHERE o.payment_status = 'waiting' AND o.payment_method = 'bank_transfer'
                ORDER BY o.order_date DESC
                LIMIT 5";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $pending_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        
        // Get total products
        $stmt = $conn->query("SELECT COUNT(*) as total FROM products");
        $totalProducts = $stmt->fetch()['total'];
        
        // Get total customers
        $stmt = $conn->query("SELECT COUNT(*) as total FROM customers");
        $totalCustomers = $stmt->fetch()['total'];
        
        // Get total revenue
        $stmt = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE payment_status IN ('paid', 'direct_paid')");
        $totalRevenue = $stmt->fetch()['total'] ?: 0;
        
        // Tambahan: Hitung pertumbuhan dibanding bulan lalu
        $stmt = $conn->query("SELECT COUNT(*) as current_month FROM orders 
                              WHERE MONTH(order_date) = MONTH(CURRENT_DATE) 
                              AND YEAR(order_date) = YEAR(CURRENT_DATE)");
        $currentMonthOrders = $stmt->fetch()['current_month'];
        
        $stmt = $conn->query("SELECT COUNT(*) as prev_month FROM orders 
                              WHERE MONTH(order_date) = MONTH(DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)) 
                              AND YEAR(order_date) = YEAR(DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH))");
        $prevMonthOrders = $stmt->fetch()['prev_month'];
        
        $ordersGrowth = 0;
        if ($prevMonthOrders > 0) {
            $ordersGrowth = round((($currentMonthOrders - $prevMonthOrders) / $prevMonthOrders) * 100, 1);
        }
        
        $stmt = $conn->query("SELECT SUM(total_amount) as current_month FROM orders 
                              WHERE payment_status IN ('paid', 'direct_paid') 
                              AND MONTH(order_date) = MONTH(CURRENT_DATE) 
                              AND YEAR(order_date) = YEAR(CURRENT_DATE)");
        $currentMonthRevenue = $stmt->fetch()['current_month'] ?: 0;
        
        $stmt = $conn->query("SELECT SUM(total_amount) as prev_month FROM orders 
                              WHERE payment_status IN ('paid', 'direct_paid') 
                              AND MONTH(order_date) = MONTH(DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)) 
                              AND YEAR(order_date) = YEAR(DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH))");
        $prevMonthRevenue = $stmt->fetch()['prev_month'] ?: 0;
        
        $revenueGrowth = 0;
        if ($prevMonthRevenue > 0) {
            $revenueGrowth = round((($currentMonthRevenue - $prevMonthRevenue) / $prevMonthRevenue) * 100, 1);
        }
        
        // Get weekly orders data for chart
        $stmt = $conn->query("SELECT DATE_FORMAT(order_date, '%a') as day, COUNT(*) as count,
                             SUM(total_amount) as revenue 
                             FROM orders 
                             WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                             GROUP BY DATE_FORMAT(order_date, '%a')");
        $weeklyOrdersData = $stmt->fetchAll();
        
        // Format for chart
        $weekDays = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']; // Shorter day names for mobile
        $weeklyOrdersCounts = array_fill(0, 7, 0);
        $weeklyOrdersRevenue = array_fill(0, 7, 0);
        
        foreach ($weeklyOrdersData as $dayData) {
            $dayIndex = array_search($dayData['day'], ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);
            if ($dayIndex !== false) {
                $weeklyOrdersCounts[$dayIndex] = (int)$dayData['count'];
                $weeklyOrdersRevenue[$dayIndex] = (float)$dayData['revenue'];
            }
        }
        
        // Get product distribution data for chart - limited to top 5 for mobile
        $stmt = $conn->query("SELECT p.name, COUNT(oi.item_id) as count,
                             SUM(oi.quantity) as total_quantity,
                             SUM(oi.subtotal) as revenue
                             FROM order_items oi
                             JOIN products p ON oi.product_id = p.product_id
                             GROUP BY oi.product_id
                             ORDER BY count DESC
                             LIMIT 5");
        $productDistributionData = $stmt->fetchAll();
        
        // Format for chart
        $productLabels = [];
        $productCounts = [];
        $productRevenue = [];
        
        foreach ($productDistributionData as $productData) {
            // Truncate product names if too long (for mobile screens)
            $productName = (strlen($productData['name']) > 15) ? 
                          substr($productData['name'], 0, 12) . '...' : 
                          $productData['name'];
            $productLabels[] = $productName;
            $productCounts[] = (int)$productData['count'];
            $productRevenue[] = (float)$productData['revenue'] / 1000000; // Convert to millions
        }
        
        // Get recent orders
        $stmt = $conn->query("SELECT o.*, c.name as customer_name, c.phone 
                             FROM orders o 
                             JOIN customers c ON o.customer_id = c.customer_id 
                             ORDER BY o.order_date DESC 
                             LIMIT 5");
        $recentOrders = $stmt->fetchAll();
        
        // Get recent customers
        $stmt = $conn->query("SELECT c.*, 
                             (SELECT COUNT(*) FROM orders WHERE customer_id = c.customer_id) as order_count 
                             FROM customers c 
                             ORDER BY c.created_at DESC 
                             LIMIT 5");
        $recentCustomers = $stmt->fetchAll();
        
        // Get low stock products
        $stmt = $conn->query("SELECT p.*, 
                             (SELECT SUM(quantity) FROM order_items oi JOIN orders o ON oi.order_id = o.order_id 
                              WHERE oi.product_id = p.product_id AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) 
                              as monthly_demand 
                             FROM products p 
                             WHERE p.stock < 10 AND p.is_active = TRUE 
                             ORDER BY p.stock ASC 
                             LIMIT 5");
        $lowStockProducts = $stmt->fetchAll();
        
        // Calculate restock recommendations
        foreach ($lowStockProducts as $key => $product) {
            $lowStockProducts[$key]['restock_recommendation'] = max(20, $product['monthly_demand'] * 1.5);
        }
        
        // Save to cache if caching is enabled
        if ($use_cache) {
            $dashboard_data = [
                'stats' => [
                    'total_orders' => $totalOrders,
                    'pending_orders' => $pendingOrders,
                    'total_products' => $totalProducts,
                    'total_customers' => $totalCustomers,
                    'total_revenue' => $totalRevenue,
                    'low_stock_count' => count($lowStockProducts)
                ],
                'growth' => [
                    'orders' => $ordersGrowth,
                    'revenue' => $revenueGrowth
                ],
                'charts' => [
                    'weekly_orders' => [
                        'labels' => $weekDays,
                        'counts' => $weeklyOrdersCounts,
                        'revenue' => $weeklyOrdersRevenue
                    ],
                    'products' => [
                        'labels' => $productLabels,
                        'counts' => $productCounts,
                        'revenue' => $productRevenue
                    ]
                ],
                'recent_orders' => $recentOrders,
                'recent_customers' => $recentCustomers,
                'low_stock' => $lowStockProducts
            ];
            
            // Tulis ke cache file jika direktori ada
            try {
                file_put_contents($cache_file, json_encode($dashboard_data));
            } catch (Exception $e) {
                // Caching gagal, tapi bisa diabaikan karena data sudah di-load
                error_log('Caching error in dashboard: ' . $e->getMessage());
            }
        }
        
    } catch (PDOException $e) {
        setFlashMessage('danger', 'Terjadi kesalahan saat mengambil data: ' . $e->getMessage());
        error_log('Dashboard error: ' . $e->getMessage());
    }
} else {
    // Ekstrak data dari cache
    $totalOrders = $dashboard_data['stats']['total_orders'];
    $pendingOrders = $dashboard_data['stats']['pending_orders'];
    $totalProducts = $dashboard_data['stats']['total_products'];
    $totalCustomers = $dashboard_data['stats']['total_customers'];
    $totalRevenue = $dashboard_data['stats']['total_revenue'];
    $ordersGrowth = $dashboard_data['growth']['orders'];
    $revenueGrowth = $dashboard_data['growth']['revenue'];
    $weeklyOrdersCounts = $dashboard_data['charts']['weekly_orders']['counts'];
    $weeklyOrdersRevenue = $dashboard_data['charts']['weekly_orders']['revenue'];
    $productLabels = $dashboard_data['charts']['products']['labels'];
    $productCounts = $dashboard_data['charts']['products']['counts'];
    $productRevenue = $dashboard_data['charts']['products']['revenue'];
    $recentOrders = $dashboard_data['recent_orders'];
    $recentCustomers = $dashboard_data['recent_customers'];
    $lowStockProducts = $dashboard_data['low_stock'];
}

// Include header
include 'includes/header.php';

?>

<!-- Responsive viewport meta tag for mobile devices -->
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Link to custom CSS for Aquanest Admin -->
<link href="css/aquanest-admin.css" rel="stylesheet">

    <!-- <?php displayFlashMessage(); ?> -->

    <!-- Stats Cards Row -->
    <div class="row mb-4">
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="aquanest-card">
                <div class="stats-card bg-primary text-white">
                    <div class="stats-icon bg-white text-primary">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h5>Total Pesanan</h5>
                    <h2><?php echo $totalOrders; ?></h2>
                    <p class="d-flex align-items-center mb-0">
                        <span>vs bulan lalu</span>
                        <span class="trend-indicator <?php echo $ordersGrowth >= 0 ? 'trend-up' : 'trend-down'; ?>">
                            <i class="fas fa-<?php echo $ordersGrowth >= 0 ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                            <?php echo abs($ordersGrowth); ?>%
                        </span>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="aquanest-card">
                <div class="stats-card bg-warning text-white">
                    <div class="stats-icon bg-white text-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h5>Pesanan Tertunda</h5>
                    <h2><?php echo $pendingOrders; ?></h2>
                    <p class="mb-0">
                        <a href="manage_orders.php?status=pending" class="text-white">Lihat Detail <i class="fas fa-arrow-right ms-1"></i></a>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="aquanest-card">
                <div class="stats-card bg-success text-white">
                    <div class="stats-icon bg-white text-success">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <h5>Total Pendapatan</h5>
                    <h2><?php echo formatRupiah($totalRevenue); ?></h2>
                    <p class="d-flex align-items-center mb-0">
                        <span>vs bulan lalu</span>
                        <span class="trend-indicator <?php echo $revenueGrowth >= 0 ? 'trend-up' : 'trend-down'; ?>">
                            <i class="fas fa-<?php echo $revenueGrowth >= 0 ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                            <?php echo abs($revenueGrowth); ?>%
                        </span>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="aquanest-card">
                <div class="stats-card bg-info text-white">
                    <div class="stats-icon bg-white text-info">
                        <i class="fas fa-box"></i>
                    </div>
                    <h5>Total Produk</h5>
                    <h2><?php echo $totalProducts; ?></h2>
                    <p class="mb-0">
                        <a href="manage_products.php" class="text-white">Lihat Detail <i class="fas fa-arrow-right ms-1"></i></a>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="aquanest-card">
                <div class="stats-card bg-primary text-white">
                    <div class="stats-icon bg-white text-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <h5>Total Pelanggan</h5>
                    <h2><?php echo $totalCustomers; ?></h2>
                    <p class="mb-0">
                        <a href="manage_customers.php" class="text-white">Lihat Detail <i class="fas fa-arrow-right ms-1"></i></a>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="aquanest-card">
                <div class="stats-card bg-danger text-white">
                    <div class="stats-icon bg-white text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h5>Stok Menipis</h5>
                    <h2><?php echo count($lowStockProducts); ?></h2>
                    <p class="mb-0">
                        <a href="manage_products.php" class="text-white">Cek Stok <i class="fas fa-arrow-right ms-1"></i></a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
 
<?php if ($pendingPaymentCount > 0): ?>
<div class="bg-white rounded-lg shadow-md mt-6">
    <div class="px-6 py-4 border-b border-gray-200 bg-yellow-50">
        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
            <i class="fas fa-exclamation-circle text-yellow-600 mr-2"></i>
            Verifikasi Pembayaran Tertunda (<?php echo $pendingPaymentCount; ?>)
        </h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bukti</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($pending_payments as $payment): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        #<?php echo str_pad($payment['order_id'], 5, '0', STR_PAD_LEFT); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($payment['customer_name']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($payment['customer_phone']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        Rp <?php echo number_format($payment['total_amount'], 0, ',', '.'); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo date('d/m/Y H:i', strtotime($payment['order_date'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if (!empty($payment['payment_proof'])): ?>
                            <a href="../uploads/payment_proofs/<?php echo $payment['payment_proof']; ?>" 
                               target="_blank" 
                               class="text-blue-600 hover:text-blue-900">
                                <i class="fas fa-image"></i> Lihat
                            </a>
                        <?php else: ?>
                            <span class="text-gray-400">Belum upload</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="admin_orders.php?payment=pending" class="text-blue-600 hover:text-blue-900">
                            Verifikasi
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="px-6 py-3 bg-gray-50 text-right">
        <a href="admin_orders.php?payment=pending" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
            Lihat Semua Pembayaran Tertunda <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
</div>
<?php endif; ?>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-lg-7 mb-4">
            <div class="aquanest-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Pesanan & Pendapatan Minggu Ini</h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="ordersChartDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="ordersChartDropdown">
                            <li><a class="dropdown-item" href="#" id="toggleChartView">Tampilkan Pendapatan</a></li>
                            <li><a class="dropdown-item" href="reports.php">Lihat Laporan Lengkap</a></li>
                            <li><a class="dropdown-item" href="#" id="downloadChartData">Unduh Data</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="ordersChart" 
                                data-orders="<?php echo htmlspecialchars(json_encode($weeklyOrdersCounts)); ?>"
                                data-revenue="<?php echo htmlspecialchars(json_encode($weeklyOrdersRevenue)); ?>"
                                data-labels="<?php echo htmlspecialchars(json_encode($weekDays)); ?>"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-5 mb-4">
            <div class="aquanest-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Produk Terlaris</h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="productsChartDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="productsChartDropdown">
                            <li><a class="dropdown-item" href="#" id="toggleProductMetrics">Tampilkan Pendapatan</a></li>
                            <li><a class="dropdown-item" href="reports.php">Lihat Laporan Lengkap</a></li>
                            <li><a class="dropdown-item" href="#" id="downloadProductData">Unduh Data</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="productsChart" 
                                data-labels="<?php echo htmlspecialchars(json_encode($productLabels)); ?>" 
                                data-orders="<?php echo htmlspecialchars(json_encode($productCounts)); ?>"
                                data-revenue="<?php echo htmlspecialchars(json_encode($productRevenue)); ?>"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders and Low Stock Products -->
    <div class="row mb-4">
        <div class="col-xl-8 mb-4">
            <div class="aquanest-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Pesanan Terbaru</h5>
                    <a href="manage_orders.php" class="btn btn-sm btn-primary">Lihat Semua</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-container">
                        <table class="table table-striped admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Pelanggan</th>
                                    <th class="d-none d-md-table-cell">Tanggal</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recentOrders) > 0): ?>
                                    <?php foreach ($recentOrders as $order): ?>
                                        <tr>
                                            <td>#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></td>
                                            <td>
                                                <div>
                                                    <?php echo $order['customer_name']; ?>
                                                    <div class="small text-muted d-none d-md-block"><?php echo isset($order['phone']) ? $order['phone'] : ''; ?></div>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell"><?php echo date('d-m-Y', strtotime($order['order_date'])); ?></td>
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
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="view_order.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Lihat Detail">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Belum ada pesanan.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4 mb-4">
            <div class="aquanest-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Stok Produk Menipis</h5>
                    <a href="manage_products.php" class="btn btn-sm btn-danger">Kelola Stok</a>
                </div>
                <div class="card-body p-0">
                    <?php if (count($lowStockProducts) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($lowStockProducts as $product): ?>
                                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <img src="../img/products/<?php echo $product['image'] ?: 'default.jpg'; ?>" alt="<?php echo $product['name']; ?>" class="rounded me-3" style="width: 40px; height: 40px; object-fit: cover;">
                                        <div>
                                            <h6 class="mb-0"><?php echo $product['name']; ?></h6>
                                            <small class="text-muted"><?php echo formatRupiah($product['price']); ?></small>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="me-3 d-none d-sm-block">
                                            <div class="progress" style="width: 60px; height: 6px;">
                                                <div class="progress-bar bg-danger" style="width: <?php echo min(($product['stock'] / 10) * 100, 100); ?>%"></div>
                                            </div>
                                        </div>
                                        <span class="badge bg-danger rounded-pill"><?php echo $product['stock']; ?> tersisa</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <p class="mb-0">Semua produk memiliki stok yang cukup.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Row -->
    <div class="row mb-4 ">
        <div class="col-12">
            <div class="aquanest-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Aksi Cepat</h5>
                </div>
                <div class="card-body">
                    <div class="row align">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="manage_products.php" class="btn aquanest-btn-success w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4">
                                <i class="fas fa-box-open fs-2 mb-2"></i>
                                <span>Tambah Produk</span>
                            </a>
                        
                        <!-- <div class="col-md-3 col-sm-6 mb-3">
                            <a href="customers_orders.php" class="btn aquanest-btn-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4">
                                <i class="fas fa-plus-circle fs-2 mb-2"></i>
                                <span>Tambah Pesanan</span>
                            </a> -->
                        </div>
                        
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="view_payment.php?payment_status=pending" class="btn btn-warning text-white w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4">
                                <i class="fas fa-money-bill-wave fs-2 mb-2"></i>
                                <span>Verifikasi Pembayaran</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="reports.php" class="btn btn-info text-white w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4">
                                <i class="fas fa-chart-bar fs-2 mb-2"></i>
                                <span>Lihat Laporan</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Floating Action Button -->
    <div class="mobile-fab d-md-none">
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#mobileActionsModal">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <!-- Mobile Actions Modal -->
    <div class="modal fade" id="mobileActionsModal" tabindex="-1" aria-labelledby="mobileActionsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mobileActionsModalLabel">Aksi Cepat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-grid gap-3">
                        <a href="add_order.php" class="btn btn-lg btn-primary">
                            <i class="fas fa-plus-circle me-2"></i> Tambah Pesanan Baru
                        </a>
                        <a href="add_product.php" class="btn btn-lg btn-success">
                            <i class="fas fa-box-open me-2"></i> Tambah Produk Baru
                        </a>
                        <a href="add_customer.php" class="btn btn-lg btn-info text-white">
                            <i class="fas fa-user-plus me-2"></i> Tambah Pelanggan Baru
                        </a>
                        <a href="view_payment.php?payment_status=pending" class="btn btn-lg btn-warning text-white">
                            <i class="fas fa-money-bill-wave me-2"></i> Verifikasi Pembayaran
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>

<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tolak Pembayaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectForm">
                <div class="modal-body">
                    <input type="hidden" id="reject_order_id">
                    <div class="mb-3">
                        <label class="form-label">Alasan Penolakan</label>
                        <textarea class="form-control" id="rejection_reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Inline Chart JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>



<!-- Inline Chart JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script>
// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM loaded, Chart.js available:", typeof Chart !== 'undefined');
    
    // Weekly orders data
    const weekLabels = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
    const weeklyOrdersCounts = <?php echo json_encode($weeklyOrdersCounts ?? [0, 0, 0, 0, 0, 0, 0]); ?>;
    const weeklyOrdersRevenue = <?php echo json_encode($weeklyOrdersRevenue ?? [0, 0, 0, 0, 0, 0, 0]); ?>;
    
    // Product data
    const productLabels = <?php echo json_encode($productLabels ?? []); ?>;
    const productCounts = <?php echo json_encode($productCounts ?? []); ?>;
    const productRevenue = <?php echo json_encode($productRevenue ?? []); ?>;
    
    // Create Orders Chart
    if (document.getElementById('ordersChart')) {
        window.ordersChart = new Chart(document.getElementById('ordersChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: weekLabels,
                datasets: [{
                    label: 'Jumlah Pesanan',
                    data: weeklyOrdersCounts,
                    backgroundColor: 'rgba(48, 85, 211, 0.8)',
                    borderColor: 'rgba(48, 85, 211, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Toggle between Orders and Revenue
        document.getElementById('toggleChartView')?.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (window.ordersChart.data.datasets[0].label === 'Jumlah Pesanan') {
                window.ordersChart.data.datasets[0].label = 'Pendapatan (Rp)';
                window.ordersChart.data.datasets[0].data = weeklyOrdersRevenue;
                window.ordersChart.data.datasets[0].backgroundColor = 'rgba(34, 197, 94, 0.8)';
                window.ordersChart.data.datasets[0].borderColor = 'rgba(34, 197, 94, 1)';
                this.innerText = 'Tampilkan Pesanan';
            } else {
                window.ordersChart.data.datasets[0].label = 'Jumlah Pesanan';
                window.ordersChart.data.datasets[0].data = weeklyOrdersCounts;
                window.ordersChart.data.datasets[0].backgroundColor = 'rgba(48, 85, 211, 0.8)';
                window.ordersChart.data.datasets[0].borderColor = 'rgba(48, 85, 211, 1)';
                this.innerText = 'Tampilkan Pendapatan';
            }
            
            window.ordersChart.update();
        });
    }
    
    // Create Products Chart
    if (document.getElementById('productsChart')) {
        const defaultProductLabels = ['Galon 19L', 'Botol 1.5L', 'Botol 600ml', 'Kemasan Gelas', 'Refill'];
        const defaultProductData = [25, 18, 12, 8, 5];
        
        // Use defaults if no data
        const chartLabels = productLabels.length > 0 ? productLabels : defaultProductLabels;
        const chartData = productCounts.length > 0 ? productCounts : defaultProductData;
        
        // Chart colors
        const backgroundColors = [
            'rgba(48, 85, 211, 0.8)',
            'rgba(34, 197, 94, 0.8)',
            'rgba(59, 130, 246, 0.8)',
            'rgba(245, 158, 11, 0.8)',
            'rgba(239, 68, 68, 0.8)'
        ];
        
        window.productsChart = new Chart(document.getElementById('productsChart').getContext('2d'), {
            type: window.innerWidth < 768 ? 'bar' : 'doughnut',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Jumlah Penjualan',
                    data: chartData,
                    backgroundColor: backgroundColors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: window.innerWidth < 768 ? 'bottom' : 'right'
                    }
                }
            }
        });
        
        // Toggle between product orders and revenue
        document.getElementById('toggleProductMetrics')?.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (window.productsChart.data.datasets[0].label === 'Jumlah Penjualan') {
                window.productsChart.data.datasets[0].label = 'Pendapatan (Juta Rp)';
                window.productsChart.data.datasets[0].data = productRevenue.length > 0 ? productRevenue : chartData.map(v => v * 25000);
                this.innerText = 'Tampilkan Jumlah Penjualan';
            } else {
                window.productsChart.data.datasets[0].label = 'Jumlah Penjualan';
                window.productsChart.data.datasets[0].data = chartData;
                this.innerText = 'Tampilkan Pendapatan';
            }
            
            window.productsChart.update();
        });
    }
});
function verifyPayment(orderId) {
    if (confirm('Verifikasi pembayaran untuk order #' + orderId.toString().padStart(6, '0') + '?')) {
        $.ajax({
            url: 'dashboard.php',
            type: 'POST',
            data: {
                action: 'verify_payment',
                order_id: orderId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            }
        });
    }
}

function rejectPayment(orderId) {
    $('#reject_order_id').val(orderId);
    $('#rejectModal').modal('show');
}

$('#rejectForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'dashboard.php',
        type: 'POST',
        data: {
            action: 'reject_payment',
            order_id: $('#reject_order_id').val(),
            reason: $('#rejection_reason').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#rejectModal').modal('hide');
                alert(response.message);
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
});

</script>
