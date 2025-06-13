<?php
session_start();
require_once '../includes/functions.php';

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $conn->beginTransaction();
        
        $orderId = (int)$_POST['order_id'];
        
        switch ($_POST['action']) {
            case 'update_status':
                $newStatus = sanitize($_POST['status']);
                $sql = "UPDATE orders SET order_status = ? WHERE order_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$newStatus, $orderId]);
                
                // Add to tracking history
                addTrackingHistory(
                    $conn,
                    $orderId,
                    "Order " . ucfirst($newStatus),
                    "Order status updated to " . ucfirst($newStatus)
                );
                
                setFlashMessage('success', 'Order status updated successfully');
                break;
                
            case 'assign_courier':
                $courierId = (int)$_POST['courier_id'];
                $sql = "UPDATE orders SET courier_id = ? WHERE order_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$courierId, $orderId]);
                
                // Get courier details
                $stmt = $conn->prepare("SELECT name FROM couriers WHERE courier_id = ?");
                $stmt->execute([$courierId]);
                $courier = $stmt->fetch();
                
                // Add to tracking history
                addTrackingHistory(
                    $conn,
                    $orderId,
                    "Courier Assigned",
                    "Order assigned to courier: " . $courier['name']
                );
                
                setFlashMessage('success', 'Courier assigned successfully');
                break;
                
            case 'confirm_payment':
                $sql = "UPDATE orders SET payment_status = 'paid' WHERE order_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$orderId]);
                
                // Add to tracking history
                addTrackingHistory(
                    $conn,
                    $orderId,
                    "Payment Confirmed",
                    "Payment has been confirmed by admin"
                );
                
                setFlashMessage('success', 'Payment confirmed successfully');
                break;
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error in orders.php: " . $e->getMessage());
        setFlashMessage('error', 'An error occurred. Please try again.');
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

// Get order details if ID is provided
$order = null;
if (isset($_GET['id'])) {
    $orderId = (int)$_GET['id'];
    $order = getOrderDetails($conn, $orderId);
}

// Get orders list with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

$where = [];
$params = [];

if ($search) {
    $where[] = "(o.order_id LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $where[] = "o.order_status = ?";
    $params[] = $status;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
    SELECT o.*, c.name as customer_name, c.phone as customer_phone,
           cr.name as courier_name, cr.phone as courier_phone
    FROM orders o
    JOIN customers c ON o.customer_id = c.customer_id
    LEFT JOIN couriers cr ON o.courier_id = cr.courier_id
    $whereClause
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get total orders count for pagination
$sql = "
    SELECT COUNT(*) 
    FROM orders o
    JOIN customers c ON o.customer_id = c.customer_id
    $whereClause
";
$stmt = $conn->prepare($sql);
array_pop($params); // remove limit
array_pop($params); // remove offset
$stmt->execute($params);
$totalOrders = $stmt->fetchColumn();
$totalPages = ceil($totalOrders / $limit);

// Get available couriers
$availableCouriers = $conn->query("
    SELECT * FROM couriers 
    WHERE status = 'available' 
    ORDER BY name ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - Aquanest Admin</title>
    
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
                <h2 class="text-lg font-medium text-gray-900">Orders Management</h2>
                
                <div class="flex items-center">
                    <span class="text-sm text-gray-600">Welcome, <?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                </div>
            </div>

            <!-- Content -->
            <div class="p-6">
                <?php if (isset($_SESSION['flash'])): ?>
                    <div class="mb-4 rounded-md p-4 <?= $_SESSION['flash']['type'] === 'success' ? 'bg-green-50' : 'bg-red-50' ?>">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <?php if ($_SESSION['flash']['type'] === 'success'): ?>
                                    <i class="fas fa-check-circle text-green-400"></i>
                                <?php else: ?>
                                    <i class="fas fa-exclamation-circle text-red-400"></i>
                                <?php endif; ?>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm <?= $_SESSION['flash']['type'] === 'success' ? 'text-green-800' : 'text-red-800' ?>">
                                    <?= $_SESSION['flash']['message'] ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php unset($_SESSION['flash']); ?>
                <?php endif; ?>

                <?php if ($order): ?>
                    <!-- Order Details -->
                    <div class="bg-white rounded-lg shadow mb-6">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">
                                        Order #<?= str_pad($order['order_id'], 6, '0', STR_PAD_LEFT) ?>
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        Ordered on <?= date('d M Y H:i', strtotime($order['created_at'])) ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium text-gray-500">Total Amount</p>
                                    <p class="text-lg font-semibold text-gray-900">
                                        <?= formatRupiah($order['total_amount']) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Customer Info -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-2">Customer Information</h4>
                                <div class="text-sm text-gray-600 space-y-1">
                                    <p><span class="font-medium">Name:</span> <?= htmlspecialchars($order['customer_name']) ?></p>
                                    <p><span class="font-medium">Phone:</span> <?= htmlspecialchars($order['customer_phone']) ?></p>
                                    <p><span class="font-medium">Email:</span> <?= htmlspecialchars($order['customer_email']) ?></p>
                                    <p><span class="font-medium">Address:</span> <?= htmlspecialchars($order['delivery_address']) ?></p>
                                </div>
                            </div>
                            
                            <!-- Order Status -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-2">Order Status</h4>
                                <form action="orders.php" method="POST" class="space-y-4">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                    
                                    <div>
                                        <select name="status" 
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                       focus:border-blue-500 focus:ring-blue-500">
                                            <option value="pending" <?= $order['order_status'] === 'pending' ? 'selected' : '' ?>>
                                                Pending
                                            </option>
                                            <option value="processing" <?= $order['order_status'] === 'processing' ? 'selected' : '' ?>>
                                                Processing
                                            </option>
                                            <option value="on_delivery" <?= $order['order_status'] === 'on_delivery' ? 'selected' : '' ?>>
                                                On Delivery
                                            </option>
                                            <option value="delivered" <?= $order['order_status'] === 'delivered' ? 'selected' : '' ?>>
                                                Delivered
                                            </option>
                                            <option value="cancelled" <?= $order['order_status'] === 'cancelled' ? 'selected' : '' ?>>
                                                Cancelled
                                            </option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" 
                                            class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 
                                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                        Update Status
                                    </button>
                                </form>
                                
                                <?php if ($order['payment_status'] === 'waiting' && $order['payment_proof']): ?>
                                    <form action="orders.php" method="POST" class="mt-4">
                                        <input type="hidden" name="action" value="confirm_payment">
                                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                        
                                        <button type="submit" 
                                                class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 
                                                       focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                            Confirm Payment
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Courier Assignment -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-2">Courier Assignment</h4>
                                <?php if ($order['courier_id']): ?>
                                    <div class="text-sm text-gray-600 space-y-1">
                                        <p><span class="font-medium">Name:</span> <?= htmlspecialchars($order['courier_name']) ?></p>
                                        <p><span class="font-medium">Phone:</span> <?= htmlspecialchars($order['courier_phone']) ?></p>
                                    </div>
                                <?php else: ?>
                                    <form action="orders.php" method="POST">
                                        <input type="hidden" name="action" value="assign_courier">
                                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                        
                                        <div class="space-y-4">
                                            <select name="courier_id" 
                                                    required
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                           focus:border-blue-500 focus:ring-blue-500">
                                                <option value="">Select Courier</option>
                                                <?php foreach ($availableCouriers as $courier): ?>
                                                    <option value="<?= $courier['courier_id'] ?>">
                                                        <?= htmlspecialchars($courier['name']) ?> - 
                                                        <?= htmlspecialchars($courier['phone']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            
                                            <button type="submit" 
                                                    class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 
                                                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                                Assign Courier
                                            </button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Order Items -->
                        <div class="px-6 py-4 border-t border-gray-200">
                            <h4 class="text-sm font-medium text-gray-900 mb-4">Order Items</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Product
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Price
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Quantity
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Subtotal
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($order['items'] as $item): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= htmlspecialchars($item['product_name']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= formatRupiah($item['price']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= $item['quantity'] ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= formatRupiah($item['subtotal']) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Tracking History -->
                        <div class="px-6 py-4 border-t border-gray-200">
                            <h4 class="text-sm font-medium text-gray-900 mb-4">Tracking History</h4>
                            <div class="space-y-6">
                                <?php foreach ($order['tracking_history'] as $track): ?>
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <div class="h-4 w-4 rounded-full bg-blue-600"></div>
                                            <div class="w-0.5 h-full bg-blue-200 mx-auto"></div>
                                        </div>
                                        <div class="ml-4">
                                            <h5 class="text-sm font-medium text-gray-900"><?= htmlspecialchars($track['title']) ?></h5>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($track['description']) ?></p>
                                            <p class="text-xs text-gray-400 mt-0.5">
                                                <?= date('d M Y H:i', strtotime($track['created_at'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Orders List -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
                                <h3 class="text-lg font-medium text-gray-900">Orders List</h3>
                                
                                <div class="mt-4 sm:mt-0">
                                    <form action="orders.php" method="GET" class="flex space-x-4">
                                        <input type="text" 
                                               name="search" 
                                               value="<?= htmlspecialchars($search) ?>"
                                               placeholder="Search orders..." 
                                               class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 
                                                      focus:ring-blue-500">
                                        
                                        <select name="status" 
                                                class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 
                                                       focus:ring-blue-500">
                                            <option value="">All Status</option>
                                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>
                                                Pending
                                            </option>
                                            <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>
                                                Processing
                                            </option>
                                            <option value="on_delivery" <?= $status === 'on_delivery' ? 'selected' : '' ?>>
                                                On Delivery
                                            </option>
                                            <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>
                                                Delivered
                                            </option>
                                            <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>
                                                Cancelled
                                            </option>
                                        </select>
                                        
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
                                            Payment
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
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                #<?= str_pad($order['order_id'], 6, '0', STR_PAD_LEFT) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?= htmlspecialchars($order['customer_name']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($order['customer_phone']) ?></div>
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
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                           <?= $order['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                                    <?= ucfirst($order['payment_status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('d M Y H:i', strtotime($order['created_at'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                <a href="?id=<?= $order['order_id'] ?>" 
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
                                        Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalOrders) ?> 
                                        of <?= $totalOrders ?> orders
                                    </div>
                                    
                                    <div class="flex space-x-2">
                                        <?php if ($page > 1): ?>
                                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>" 
                                               class="px-3 py-1 border rounded text-sm text-gray-600 hover:bg-gray-50">
                                                Previous
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>" 
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
