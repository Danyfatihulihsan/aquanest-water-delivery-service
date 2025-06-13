<?php
session_start();
require_once '../includes/functions.php';

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Get date range
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// Sales Overview
$sql = "SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_revenue,
            COUNT(DISTINCT customer_id) as unique_customers,
            AVG(total_amount) as average_order_value
        FROM orders 
        WHERE created_at BETWEEN ? AND ?
        AND order_status != 'cancelled'";
$stmt = $conn->prepare($sql);
$stmt->execute([$start_date, $end_date . ' 23:59:59']);
$sales_overview = $stmt->fetch();

// Daily Sales
$sql = "SELECT 
            DATE(created_at) as date,
            COUNT(*) as orders,
            SUM(total_amount) as revenue
        FROM orders 
        WHERE created_at BETWEEN ? AND ?
        AND order_status != 'cancelled'
        GROUP BY DATE(created_at)
        ORDER BY date ASC";
$stmt = $conn->prepare($sql);
$stmt->execute([$start_date, $end_date . ' 23:59:59']);
$daily_sales = $stmt->fetchAll();

// Top Products
$sql = "SELECT 
            p.name,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.quantity * oi.price) as total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.created_at BETWEEN ? AND ?
        AND o.order_status != 'cancelled'
        GROUP BY p.product_id
        ORDER BY total_revenue DESC
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->execute([$start_date, $end_date . ' 23:59:59']);
$top_products = $stmt->fetchAll();

// Delivery Performance
$sql = "SELECT 
            order_status,
            COUNT(*) as count,
            AVG(TIMESTAMPDIFF(HOUR, created_at, 
                CASE 
                    WHEN order_status = 'delivered' THEN delivered_at
                    ELSE NOW()
                END
            )) as avg_delivery_time
        FROM orders
        WHERE created_at BETWEEN ? AND ?
        GROUP BY order_status";
$stmt = $conn->prepare($sql);
$stmt->execute([$start_date, $end_date . ' 23:59:59']);
$delivery_stats = $stmt->fetchAll();

// Subscription Stats
$sql = "SELECT 
            COUNT(*) as total_subscriptions,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_subscriptions,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_subscriptions
        FROM subscriptions
        WHERE created_at BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$start_date, $end_date . ' 23:59:59']);
$subscription_stats = $stmt->fetch();

// Top Couriers
$sql = "SELECT 
            c.name,
            COUNT(*) as total_deliveries,
            AVG(CASE WHEN o.order_status = 'delivered' THEN 1 ELSE 0 END) * 100 as success_rate,
            AVG(TIMESTAMPDIFF(HOUR, o.created_at, o.delivered_at)) as avg_delivery_time
        FROM orders o
        JOIN couriers c ON o.courier_id = c.courier_id
        WHERE o.created_at BETWEEN ? AND ?
        GROUP BY c.courier_id
        ORDER BY total_deliveries DESC
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->execute([$start_date, $end_date . ' 23:59:59']);
$top_couriers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Aquanest Admin</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1">
            <!-- Top Bar -->
            <div class="h-16 bg-white shadow-sm flex items-center justify-between px-6">
                <h2 class="text-lg font-medium text-gray-900">Reports & Analytics</h2>
                
                <div class="flex items-center">
                    <span class="text-sm text-gray-600">Welcome, <?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                </div>
            </div>

            <!-- Content -->
            <div class="p-6">
                <!-- Date Range Filter -->
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="p-6">
                        <form action="reports.php" method="GET" class="flex space-x-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Start Date</label>
                                <input type="date" 
                                       name="start_date" 
                                       value="<?= $start_date ?>"
                                       max="<?= date('Y-m-d') ?>"
                                       class="mt-1 block rounded-md border-gray-300 shadow-sm 
                                              focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">End Date</label>
                                <input type="date" 
                                       name="end_date" 
                                       value="<?= $end_date ?>"
                                       max="<?= date('Y-m-d') ?>"
                                       class="mt-1 block rounded-md border-gray-300 shadow-sm 
                                              focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            
                            <div class="flex items-end">
                                <button type="submit" 
                                        class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 
                                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    Apply Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sales Overview -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                <i class="fas fa-shopping-cart text-white"></i>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-medium text-gray-500">Total Orders</h4>
                                <p class="text-2xl font-semibold text-gray-900">
                                    <?= number_format($sales_overview['total_orders']) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                <i class="fas fa-wallet text-white"></i>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-medium text-gray-500">Total Revenue</h4>
                                <p class="text-2xl font-semibold text-gray-900">
                                    <?= formatRupiah($sales_overview['total_revenue']) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                                <i class="fas fa-users text-white"></i>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-medium text-gray-500">Unique Customers</h4>
                                <p class="text-2xl font-semibold text-gray-900">
                                    <?= number_format($sales_overview['unique_customers']) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                                <i class="fas fa-chart-line text-white"></i>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-medium text-gray-500">Average Order Value</h4>
                                <p class="text-2xl font-semibold text-gray-900">
                                    <?= formatRupiah($sales_overview['average_order_value']) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Daily Sales Chart -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Daily Sales</h3>
                        <canvas id="dailySalesChart"></canvas>
                    </div>
                    
                    <!-- Delivery Performance Chart -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Delivery Performance</h3>
                        <canvas id="deliveryChart"></canvas>
                    </div>
                </div>

                <!-- Top Products & Couriers -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Top Products -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Top Products</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <?php foreach ($top_products as $product): ?>
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($product['name']) ?>
                                            </h4>
                                            <p class="text-sm text-gray-500">
                                                <?= number_format($product['total_quantity']) ?> units sold
                                            </p>
                                        </div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= formatRupiah($product['total_revenue']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Top Couriers -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Top Couriers</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <?php foreach ($top_couriers as $courier): ?>
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($courier['name']) ?>
                                            </h4>
                                            <p class="text-sm text-gray-500">
                                                <?= number_format($courier['total_deliveries']) ?> deliveries
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-medium text-gray-900">
                                                <?= number_format($courier['success_rate'], 1) ?>% success rate
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                <?= number_format($courier['avg_delivery_time'], 1) ?> hours avg. time
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subscription Stats -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Subscription Overview</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Total Subscriptions</h4>
                                <p class="mt-2 text-3xl font-semibold text-gray-900">
                                    <?= number_format($subscription_stats['total_subscriptions']) ?>
                                </p>
                            </div>
                            
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Active Subscriptions</h4>
                                <p class="mt-2 text-3xl font-semibold text-green-600">
                                    <?= number_format($subscription_stats['active_subscriptions']) ?>
                                </p>
                            </div>
                            
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Cancelled Subscriptions</h4>
                                <p class="mt-2 text-3xl font-semibold text-red-600">
                                    <?= number_format($subscription_stats['cancelled_subscriptions']) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Daily Sales Chart
        const dailySalesCtx = document.getElementById('dailySalesChart').getContext('2d');
        new Chart(dailySalesCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($daily_sales, 'date')) ?>,
                datasets: [
                    {
                        label: 'Revenue',
                        data: <?= json_encode(array_column($daily_sales, 'revenue')) ?>,
                        borderColor: 'rgb(59, 130, 246)',
                        tension: 0.1
                    },
                    {
                        label: 'Orders',
                        data: <?= json_encode(array_column($daily_sales, 'orders')) ?>,
                        borderColor: 'rgb(16, 185, 129)',
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Delivery Performance Chart
        const deliveryCtx = document.getElementById('deliveryChart').getContext('2d');
        new Chart(deliveryCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($delivery_stats, 'order_status')) ?>,
                datasets: [{
                    label: 'Orders',
                    data: <?= json_encode(array_column($delivery_stats, 'count')) ?>,
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.5)',
                        'rgba(16, 185, 129, 0.5)',
                        'rgba(245, 158, 11, 0.5)',
                        'rgba(239, 68, 68, 0.5)'
                    ],
                    borderColor: [
                        'rgb(59, 130, 246)',
                        'rgb(16, 185, 129)',
                        'rgb(245, 158, 11)',
                        'rgb(239, 68, 68)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
