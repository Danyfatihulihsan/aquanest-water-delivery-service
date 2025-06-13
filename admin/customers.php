<?php
session_start();
require_once '../includes/functions.php';

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Get customers list with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

$where = [];
$params = [];

if ($search) {
    $where[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get customers with their order statistics
$sql = "SELECT c.*,
               COUNT(DISTINCT o.order_id) as total_orders,
               SUM(o.total_amount) as total_spent,
               MAX(o.created_at) as last_order_date,
               EXISTS(SELECT 1 FROM subscriptions s WHERE s.user_id = c.customer_id AND s.status = 'active') as has_subscription
        FROM customers c
        LEFT JOIN orders o ON c.customer_id = o.customer_id
        $whereClause
        GROUP BY c.customer_id
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Get total customers count for pagination
$sql = "SELECT COUNT(*) FROM customers $whereClause";
array_pop($params); // remove offset
array_pop($params); // remove limit
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$totalCustomers = $stmt->fetchColumn();
$totalPages = ceil($totalCustomers / $limit);

// Get customer details if ID is provided
$customer = null;
if (isset($_GET['id'])) {
    $customerId = (int)$_GET['id'];
    
    // Get customer info
    $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
    
    if ($customer) {
        // Get customer's orders
        $stmt = $conn->prepare("
            SELECT o.*, 
                   (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', p.name) SEPARATOR ', ')
                    FROM order_items oi 
                    JOIN products p ON oi.product_id = p.product_id 
                    WHERE oi.order_id = o.order_id) as items
            FROM orders o 
            WHERE o.customer_id = ? 
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$customerId]);
        $customer['orders'] = $stmt->fetchAll();
        
        // Get customer's active subscription
        $stmt = $conn->prepare("
            SELECT s.*, p.name as plan_name, p.price as plan_price
            FROM subscriptions s
            JOIN subscription_plans p ON s.plan_id = p.id
            WHERE s.user_id = ? AND s.status = 'active'
        ");
        $stmt->execute([$customerId]);
        $customer['subscription'] = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers Management - Aquanest Admin</title>
    
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
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1">
            <!-- Top Bar -->
            <div class="h-16 bg-white shadow-sm flex items-center justify-between px-6">
                <h2 class="text-lg font-medium text-gray-900">Customers Management</h2>
                
                <div class="flex items-center">
                    <span class="text-sm text-gray-600">Welcome, <?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                </div>
            </div>

            <!-- Content -->
            <div class="p-6">
                <?php if ($customer): ?>
                    <!-- Customer Details -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">
                                        <?= htmlspecialchars($customer['name']) ?>
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        Customer since <?= date('d M Y', strtotime($customer['created_at'])) ?>
                                    </p>
                                </div>
                                <a href="customers.php" 
                                   class="text-blue-600 hover:text-blue-900">
                                    Back to List
                                </a>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Customer Information -->
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900 mb-4">Contact Information</h4>
                                    <div class="space-y-3">
                                        <p class="text-sm">
                                            <span class="font-medium text-gray-700">Email:</span>
                                            <span class="text-gray-900 ml-2"><?= htmlspecialchars($customer['email']) ?></span>
                                        </p>
                                        <p class="text-sm">
                                            <span class="font-medium text-gray-700">Phone:</span>
                                            <span class="text-gray-900 ml-2"><?= htmlspecialchars($customer['phone']) ?></span>
                                        </p>
                                        <p class="text-sm">
                                            <span class="font-medium text-gray-700">Address:</span>
                                            <span class="text-gray-900 ml-2"><?= htmlspecialchars($customer['address']) ?></span>
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Active Subscription -->
                                <?php if ($customer['subscription']): ?>
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-900 mb-4">Active Subscription</h4>
                                        <div class="bg-blue-50 rounded-lg p-4">
                                            <div class="space-y-2">
                                                <p class="text-sm font-medium text-blue-900">
                                                    <?= htmlspecialchars($customer['subscription']['plan_name']) ?>
                                                </p>
                                                <p class="text-sm text-blue-700">
                                                    <?= formatRupiah($customer['subscription']['plan_price']) ?> per 
                                                    <?= $customer['subscription']['duration'] ?>
                                                </p>
                                                <p class="text-sm text-blue-700">
                                                    Next delivery: <?= date('d M Y', strtotime($customer['subscription']['next_delivery'])) ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Order History -->
                            <div class="mt-8">
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Order History</h4>
                                <?php if (empty($customer['orders'])): ?>
                                    <p class="text-sm text-gray-500">No orders found</p>
                                <?php else: ?>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Order ID
                                                    </th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Date
                                                    </th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Items
                                                    </th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Amount
                                                    </th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Status
                                                    </th>
                                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Action
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($customer['orders'] as $order): ?>
                                                    <tr>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                            #<?= str_pad($order['order_id'], 6, '0', STR_PAD_LEFT) ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            <?= date('d M Y H:i', strtotime($order['created_at'])) ?>
                                                        </td>
                                                        <td class="px-6 py-4 text-sm text-gray-500">
                                                            <?= htmlspecialchars($order['items']) ?>
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
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Customers List -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
                                <h3 class="text-lg font-medium text-gray-900">Customers List</h3>
                                
                                <div class="mt-4 sm:mt-0">
                                    <form action="customers.php" method="GET" class="flex space-x-4">
                                        <input type="text" 
                                               name="search" 
                                               value="<?= htmlspecialchars($search) ?>"
                                               placeholder="Search customers..." 
                                               class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 
                                                      focus:ring-blue-500">
                                        
                                        <button type="submit" 
                                                class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 
                                                       focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                            Search
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Customer
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Contact
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Orders
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Total Spent
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Last Order
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($customers as $item): ?>
                                        <tr>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?= htmlspecialchars($item['name']) ?>
                                                            <?php if ($item['has_subscription']): ?>
                                                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                                    Subscriber
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            Customer since <?= date('d M Y', strtotime($item['created_at'])) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?= htmlspecialchars($item['email']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($item['phone']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= number_format($item['total_orders']) ?> orders
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= formatRupiah($item['total_spent'] ?? 0) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= $item['last_order_date'] ? date('d M Y', strtotime($item['last_order_date'])) : '-' ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                <a href="?id=<?= $item['customer_id'] ?>" 
                                                   class="text-blue-600 hover:text-blue-900">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="px-6 py-4 border-t border-gray-200">
                                <div class="flex justify-between items-center">
                                    <div class="text-sm text-gray-500">
                                        Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalCustomers) ?> 
                                        of <?= $totalCustomers ?> customers
                                    </div>
                                    
                                    <div class="flex space-x-2">
                                        <?php if ($page > 1): ?>
                                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" 
                                               class="px-3 py-1 border rounded text-sm text-gray-600 hover:bg-gray-50">
                                                Previous
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" 
                                               class="px-3 py-1 border rounded text-sm text-gray-600 hover:bg-gray-50">
                                                Next
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
