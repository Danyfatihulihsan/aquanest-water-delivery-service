<?php
session_start();
require_once '../includes/functions.php';

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Handle subscription operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        switch ($_POST['action']) {
            case 'update_plan':
                $sql = "UPDATE subscription_plans 
                        SET name = ?, description = ?, price = ?, duration = ?, delivery_count = ?, 
                            discount = ?, popular = ? 
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    sanitize($_POST['name']),
                    sanitize($_POST['description']),
                    (float)$_POST['price'],
                    sanitize($_POST['duration']),
                    (int)$_POST['delivery_count'],
                    (float)$_POST['discount'],
                    isset($_POST['popular']) ? 1 : 0,
                    (int)$_POST['plan_id']
                ]);
                setFlashMessage('success', 'Subscription plan updated successfully');
                break;
                
            case 'add_plan':
                $sql = "INSERT INTO subscription_plans 
                        (name, description, price, duration, delivery_count, discount, popular) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    sanitize($_POST['name']),
                    sanitize($_POST['description']),
                    (float)$_POST['price'],
                    sanitize($_POST['duration']),
                    (int)$_POST['delivery_count'],
                    (float)$_POST['discount'],
                    isset($_POST['popular']) ? 1 : 0
                ]);
                setFlashMessage('success', 'Subscription plan added successfully');
                break;
                
            case 'update_subscription':
                $sql = "UPDATE subscriptions 
                        SET status = ?, delivery_day = ?, next_delivery = ? 
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    sanitize($_POST['status']),
                    sanitize($_POST['delivery_day']),
                    $_POST['next_delivery'],
                    (int)$_POST['subscription_id']
                ]);
                setFlashMessage('success', 'Subscription updated successfully');
                break;
                
            case 'cancel_subscription':
                $sql = "UPDATE subscriptions SET status = 'cancelled' WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([(int)$_POST['subscription_id']]);
                setFlashMessage('success', 'Subscription cancelled successfully');
                break;
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error in subscriptions.php: " . $e->getMessage());
        setFlashMessage('error', 'An error occurred. Please try again.');
    }
    
    // Redirect to prevent form resubmission
    header("Location: subscriptions.php");
    exit();
}

// Get subscription plan if ID is provided
$plan = null;
if (isset($_GET['plan_id'])) {
    $stmt = $conn->prepare("SELECT * FROM subscription_plans WHERE id = ?");
    $stmt->execute([(int)$_GET['plan_id']]);
    $plan = $stmt->fetch();
}

// Get subscription if ID is provided
$subscription = null;
if (isset($_GET['id'])) {
    $sql = "SELECT s.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
                   p.name as plan_name, p.price as plan_price, p.duration as plan_duration
            FROM subscriptions s
            JOIN customers c ON s.user_id = c.customer_id
            JOIN subscription_plans p ON s.plan_id = p.id
            WHERE s.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([(int)$_GET['id']]);
    $subscription = $stmt->fetch();
}

// Get subscription plans
$plans = $conn->query("SELECT * FROM subscription_plans ORDER BY price ASC")->fetchAll();

// Get subscriptions list with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

$where = [];
$params = [];

if ($search) {
    $where[] = "(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $where[] = "s.status = ?";
    $params[] = $status;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

$sql = "SELECT s.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
               p.name as plan_name, p.price as plan_price, p.duration as plan_duration
        FROM subscriptions s
        JOIN customers c ON s.user_id = c.customer_id
        JOIN subscription_plans p ON s.plan_id = p.id
        $whereClause
        ORDER BY s.created_at DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$subscriptions = $stmt->fetchAll();

// Get total subscriptions count for pagination
$sql = "SELECT COUNT(*) 
        FROM subscriptions s
        JOIN customers c ON s.user_id = c.customer_id
        $whereClause";
array_pop($params); // remove offset
array_pop($params); // remove limit
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$totalSubscriptions = $stmt->fetchColumn();
$totalPages = ceil($totalSubscriptions / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscriptions Management - Aquanest Admin</title>
    
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
                <h2 class="text-lg font-medium text-gray-900">Subscriptions Management</h2>
                
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

                <!-- Subscription Plans -->
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">
                            <?= $plan ? 'Edit Subscription Plan' : 'Subscription Plans' ?>
                        </h3>
                    </div>
                    
                    <?php if ($plan): ?>
                        <!-- Edit Plan Form -->
                        <div class="p-6">
                            <form action="subscriptions.php" method="POST" class="space-y-6">
                                <input type="hidden" name="action" value="update_plan">
                                <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Plan Name</label>
                                        <input type="text" 
                                               name="name" 
                                               value="<?= htmlspecialchars($plan['name']) ?>"
                                               required 
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                      focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Price</label>
                                        <input type="number" 
                                               name="price" 
                                               value="<?= $plan['price'] ?>"
                                               required 
                                               min="0" 
                                               step="0.01"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                      focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Duration</label>
                                        <select name="duration" 
                                                required 
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                       focus:border-blue-500 focus:ring-blue-500">
                                            <option value="weekly" <?= $plan['duration'] === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                            <option value="monthly" <?= $plan['duration'] === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                            <option value="quarterly" <?= $plan['duration'] === 'quarterly' ? 'selected' : '' ?>>Quarterly</option>
                                            <option value="yearly" <?= $plan['duration'] === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Delivery Count</label>
                                        <input type="number" 
                                               name="delivery_count" 
                                               value="<?= $plan['delivery_count'] ?>"
                                               required 
                                               min="1"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                      focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Discount (%)</label>
                                        <input type="number" 
                                               name="discount" 
                                               value="<?= $plan['discount'] ?>"
                                               required 
                                               min="0" 
                                               max="100"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                      focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Description</label>
                                        <textarea name="description" 
                                                  required 
                                                  rows="3"
                                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                         focus:border-blue-500 focus:ring-blue-500"><?= htmlspecialchars($plan['description']) ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           name="popular" 
                                           id="popular"
                                           <?= $plan['popular'] ? 'checked' : '' ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="popular" class="ml-2 block text-sm text-gray-900">
                                        Mark as Popular Plan
                                    </label>
                                </div>
                                
                                <div class="flex justify-end space-x-3">
                                    <a href="subscriptions.php" 
                                       class="px-4 py-2 border rounded-md text-gray-700 hover:bg-gray-50 
                                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                        Cancel
                                    </a>
                                    <button type="submit" 
                                            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 
                                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                        Update Plan
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- Plans List -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Plan Name
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Price
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Duration
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Deliveries
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Discount
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($plans as $item): ?>
                                        <tr>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?= htmlspecialchars($item['name']) ?>
                                                            <?php if ($item['popular']): ?>
                                                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                                    Popular
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            <?= htmlspecialchars($item['description']) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= formatRupiah($item['price']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= ucfirst($item['duration']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= $item['delivery_count'] ?>x
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= $item['discount'] ?>%
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                <a href="?plan_id=<?= $item['id'] ?>" 
                                                   class="text-blue-600 hover:text-blue-900">
                                                    Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Active Subscriptions -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
                            <h3 class="text-lg font-medium text-gray-900">Active Subscriptions</h3>
                            
                            <div class="mt-4 sm:mt-0">
                                <form action="subscriptions.php" method="GET" class="flex space-x-4">
                                    <input type="text" 
                                           name="search" 
                                           value="<?= htmlspecialchars($search) ?>"
                                           placeholder="Search subscriptions..." 
                                           class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 
                                                  focus:ring-blue-500">
                                    
                                    <select name="status" 
                                            class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 
                                                   focus:ring-blue-500">
                                        <option value="">All Status</option>
                                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>
                                            Active
                                        </option>
                                        <option value="paused" <?= $status === 'paused' ? 'selected' : '' ?>>
                                            Paused
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
                    
                    <?php if ($subscription): ?>
                        <!-- Subscription Details -->
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900 mb-2">Customer Information</h4>
                                    <div class="text-sm text-gray-600 space-y-1">
                                        <p><span class="font-medium">Name:</span> <?= htmlspecialchars($subscription['customer_name']) ?></p>
                                        <p><span class="font-medium">Email:</span> <?= htmlspecialchars($subscription['customer_email']) ?></p>
                                        <p><span class="font-medium">Phone:</span> <?= htmlspecialchars($subscription['customer_phone']) ?></p>
                                    </div>
                                </div>
                                
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900 mb-2">Plan Information</h4>
                                    <div class="text-sm text-gray-600 space-y-1">
                                        <p><span class="font-medium">Plan:</span> <?= htmlspecialchars($subscription['plan_name']) ?></p>
                                        <p><span class="font-medium">Price:</span> <?= formatRupiah($subscription['plan_price']) ?></p>
                                        <p><span class="font-medium">Duration:</span> <?= ucfirst($subscription['plan_duration']) ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <form action="subscriptions.php" method="POST" class="mt-6 space-y-6">
                                <input type="hidden" name="action" value="update_subscription">
                                <input type="hidden" name="subscription_id" value="<?= $subscription['id'] ?>">
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Status</label>
                                        <select name="status" 
                                                required 
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                       focus:border-blue-500 focus:ring-blue-500">
                                            <option value="active" <?= $subscription['status'] === 'active' ? 'selected' : '' ?>>
                                                Active
                                            </option>
                                            <option value="paused" <?= $subscription['status'] === 'paused' ? 'selected' : '' ?>>
                                                Paused
                                            </option>
                                            <option value="cancelled" <?= $subscription['status'] === 'cancelled' ? 'selected' : '' ?>>
                                                Cancelled
                                            </option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Delivery Day</label>
                                        <select name="delivery_day" 
                                                required 
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                       focus:border-blue-500 focus:ring-blue-500">
                                            <option value="monday" <?= $subscription['delivery_day'] === 'monday' ? 'selected' : '' ?>>
                                                Monday
                                            </option>
                                            <option value="tuesday" <?= $subscription['delivery_day'] === 'tuesday' ? 'selected' : '' ?>>
                                                Tuesday
                                            </option>
                                            <option value="wednesday" <?= $subscription['delivery_day'] === 'wednesday' ? 'selected' : '' ?>>
                                                Wednesday
                                            </option>
                                            <option value="thursday" <?= $subscription['delivery_day'] === 'thursday' ? 'selected' : '' ?>>
                                                Thursday
                                            </option>
                                            <option value="friday" <?= $subscription['delivery_day'] === 'friday' ? 'selected' : '' ?>>
                                                Friday
                                            </option>
                                            <option value="saturday" <?= $subscription['delivery_day'] === 'saturday' ? 'selected' : '' ?>>
                                                Saturday
                                            </option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Next Delivery</label>
                                        <input type="date" 
                                               name="next_delivery" 
                                               value="<?= date('Y-m-d', strtotime($subscription['next_delivery'])) ?>"
                                               required 
                                               min="<?= date('Y-m-d') ?>"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                      focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                </div>
                                
                                <div class="flex justify-end space-x-3">
                                    <a href="subscriptions.php" 
                                       class="px-4 py-2 border rounded-md text-gray-700 hover:bg-gray-50 
                                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                        Back to List
                                    </a>
                                    
                                    <?php if ($subscription['status'] !== 'cancelled'): ?>
                                        <form action="subscriptions.php" method="POST" class="inline">
                                            <input type="hidden" name="action" value="cancel_subscription">
                                            <input type="hidden" name="subscription_id" value="<?= $subscription['id'] ?>">
                                            <button type="submit" 
                                                    onclick="return confirm('Are you sure you want to cancel this subscription?')"
                                                    class="px-4 py-2 border border-red-300 rounded-md text-red-700 
                                                           hover:bg-red-50 focus:outline-none focus:ring-2 
                                                           focus:ring-red-500 focus:ring-offset-2">
                                                Cancel Subscription
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <button type="submit" 
                                            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 
                                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                        Update Subscription
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- Subscriptions List -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Customer
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Plan
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Delivery Day
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Next Delivery
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($subscriptions as $item): ?>
                                        <tr>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($item['customer_name']) ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?= htmlspecialchars($item['customer_phone']) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900">
                                                    <?= htmlspecialchars($item['plan_name']) ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?= formatRupiah($item['plan_price']) ?> / <?= $item['plan_duration'] ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                           <?php
                                                           switch ($item['status']) {
                                                               case 'active':
                                                                   echo 'bg-green-100 text-green-800';
                                                                   break;
                                                               case 'paused':
                                                                   echo 'bg-yellow-100 text-yellow-800';
                                                                   break;
                                                               case 'cancelled':
                                                                   echo 'bg-red-100 text-red-800';
                                                                   break;
                                                           }
                                                           ?>">
                                                    <?= ucfirst($item['status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= ucfirst($item['delivery_day']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('d M Y', strtotime($item['next_delivery'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                <a href="?id=<?= $item['id'] ?>" 
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
                                        Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalSubscriptions) ?> 
                                        of <?= $totalSubscriptions ?> subscriptions
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
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
