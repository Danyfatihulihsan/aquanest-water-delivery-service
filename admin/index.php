<?php
session_start();
require_once '../includes/functions.php';

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Get statistics
$stats = [
    'total_orders' => $conn->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'pending_orders' => $conn->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn(),
    'active_subscriptions' => $conn->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'")->fetchColumn(),
    'total_revenue' => $conn->query("SELECT SUM(total_amount) FROM orders WHERE payment_status = 'paid'")->fetchColumn() ?? 0
];

// Get recent orders
$recentOrders = $conn->query("
    SELECT o.*, c.name as customer_name 
    FROM orders o 
    JOIN customers c ON o.customer_id = c.customer_id 
    ORDER BY o.created_at DESC 
    LIMIT 5
")->fetchAll();

// Get active couriers
$activeCouriers = $conn->query("
    SELECT * FROM couriers 
    WHERE status = 'available' 
    ORDER BY name ASC
")->fetchAll();

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Get active tab
$activeTab = $_GET['tab'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Aquanest</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="w-64 bg-white shadow-sm">
            <div class="h-16 flex items-center px-6">
                <h1 class="text-xl font-bold text-gray-900">Aquanest Admin</h1>
            </div>
            
            <nav class="mt-4">
                <a href="?tab=dashboard" 
                   class="flex items-center px-6 py-3 text-sm <?= $activeTab === 'dashboard' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>">
                    <i class="fas fa-home w-5"></i>
                    <span class="ml-3">Dashboard</span>
                </a>
                
                <a href="orders.php" 
                   class="flex items-center px-6 py-3 text-sm text-gray-600 hover:bg-gray-50">
                    <i class="fas fa-shopping-cart w-5"></i>
                    <span class="ml-3">Orders</span>
                </a>
                
                <a href="products.php" 
                   class="flex items-center px-6 py-3 text-sm text-gray-600 hover:bg-gray-50">
                    <i class="fas fa-box w-5"></i>
                    <span class="ml-3">Products</span>
                </a>
                
                <a href="subscriptions.php" 
                   class="flex items-center px-6 py-3 text-sm text-gray-600 hover:bg-gray-50">
                    <i class="fas fa-sync w-5"></i>
                    <span class="ml-3">Subscriptions</span>
                </a>
                
                <a href="couriers.php" 
                   class="flex items-center px-6 py-3 text-sm text-gray-600 hover:bg-gray-50">
                    <i class="fas fa-truck w-5"></i>
                    <span class="ml-3">Couriers</span>
                </a>
                
                <a href="customers.php" 
                   class="flex items-center px-6 py-3 text-sm text-gray-600 hover:bg-gray-50">
                    <i class="fas fa-users w-5"></i>
                    <span class="ml-3">Customers</span>
                </a>
                
                <a href="reports.php" 
                   class="flex items-center px-6 py-3 text-sm text-gray-600 hover:bg-gray-50">
                    <i class="fas fa-chart-bar w-5"></i>
                    <span class="ml-3">Reports</span>
                </a>
                
                <a href="settings.php" 
                   class="flex items-center px-6 py-3 text-sm text-gray-600 hover:bg-gray-50">
                    <i class="fas fa-cog w-5"></i>
                    <span class="ml-3">Settings</span>
                </a>
                
                <a href="?action=logout" 
                   class="flex items-center px-6 py-3 text-sm text-red-600 hover:bg-red-50">
                    <i class="fas fa-sign-out-alt w-5"></i>
                    <span class="ml-3">Logout</span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1">
            <!-- Top Bar -->
            <div class="h-16 bg-white shadow-sm flex items-center justify-between px-6">
                <h2 class="text-lg font-medium text-gray-900">
                    <?php
                    switch ($activeTab) {
                        case 'dashboard':
                            echo 'Dashboard Overview';
                            break;
                        default:
                            echo 'Dashboard';
                    }
                    ?>
                </h2>
                
                <div class="flex items-center">
                    <span class="text-sm text-gray-600">Welcome, <?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="p-6">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                <i class="fas fa-shopping-cart text-white"></i>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-medium text-gray-500">Total Orders</h4>
                                <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['total_orders']) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                                <i class="fas fa-clock text-white"></i>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-medium text-gray-500">Pending Orders</h4>
                                <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['pending_orders']) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                <i class="fas fa-sync text-white"></i>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-medium text-gray-500">Active Subscriptions</h4>
                                <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['active_subscriptions']) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                                <i class="fas fa-wallet text-white"></i>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-medium text-gray-500">Total Revenue</h4>
                                <p class="text-2xl font-semibold text-gray-900"><?= formatRupiah($stats['total_revenue']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Recent Orders</h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Order ID
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Customer
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Amount
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            #<?= str_pad($order['order_id'], 6, '0', STR_PAD_LEFT) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($order['customer_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= formatRupiah($order['total_amount']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                       <?php
                                                       switch ($order['order_status']) {
                                                           case 'delivered':
                                                               echo 'bg-green-100 text-green-800';
                                                               break;
                                                           case 'processing':
                                                           case 'on_delivery':
                                                               echo 'bg-blue-100 text-blue-800';
                                                               break;
                                                           case 'cancelled':
                                                               echo 'bg-red-100 text-red-800';
                                                               break;
                                                           default:
                                                               echo 'bg-gray-100 text-gray-800';
                                                       }
                                                       ?>">
                                                <?= ucfirst($order['order_status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('d M Y H:i', strtotime($order['created_at'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                            <a href="orders.php?id=<?= $order['order_id'] ?>" 
                                               class="text-blue-600 hover:text-blue-900">
                                                View Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="px-6 py-4 border-t border-gray-200">
                        <a href="orders.php" class="text-sm text-blue-600 hover:text-blue-900">View all orders</a>
                    </div>
                </div>

                <!-- Active Couriers -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Active Couriers</h3>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($activeCouriers as $courier): ?>
                                <div class="border rounded-lg p-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-gray-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-user text-gray-500"></i>
                                        </div>
                                        <div class="ml-4">
                                            <h4 class="text-sm font-medium text-gray-900"><?= htmlspecialchars($courier['name']) ?></h4>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($courier['phone']) ?></p>
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Available
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="px-6 py-4 border-t border-gray-200">
                        <a href="couriers.php" class="text-sm text-blue-600 hover:text-blue-900">Manage couriers</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
