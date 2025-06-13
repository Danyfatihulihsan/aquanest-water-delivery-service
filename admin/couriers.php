<?php
session_start();
require_once '../includes/functions.php';

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Handle courier operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        switch ($_POST['action']) {
            case 'add':
                $sql = "INSERT INTO couriers (name, phone, email, vehicle_type, vehicle_number, status) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    sanitize($_POST['name']),
                    sanitize($_POST['phone']),
                    sanitize($_POST['email']),
                    sanitize($_POST['vehicle_type']),
                    sanitize($_POST['vehicle_number']),
                    'available'
                ]);
                setFlashMessage('success', 'Courier added successfully');
                break;
                
            case 'update':
                $sql = "UPDATE couriers 
                        SET name = ?, phone = ?, email = ?, vehicle_type = ?, vehicle_number = ? 
                        WHERE courier_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    sanitize($_POST['name']),
                    sanitize($_POST['phone']),
                    sanitize($_POST['email']),
                    sanitize($_POST['vehicle_type']),
                    sanitize($_POST['vehicle_number']),
                    (int)$_POST['courier_id']
                ]);
                setFlashMessage('success', 'Courier updated successfully');
                break;
                
            case 'update_status':
                $sql = "UPDATE couriers SET status = ? WHERE courier_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    sanitize($_POST['status']),
                    (int)$_POST['courier_id']
                ]);
                setFlashMessage('success', 'Courier status updated successfully');
                break;
                
            case 'delete':
                // Instead of deleting, mark as inactive
                $sql = "UPDATE couriers SET status = 'inactive' WHERE courier_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([(int)$_POST['courier_id']]);
                setFlashMessage('success', 'Courier deactivated successfully');
                break;
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error in couriers.php: " . $e->getMessage());
        setFlashMessage('error', 'An error occurred. Please try again.');
    }
    
    // Redirect to prevent form resubmission
    header("Location: couriers.php");
    exit();
}

// Get courier details if ID is provided
$courier = null;
if (isset($_GET['id'])) {
    $stmt = $conn->prepare("SELECT * FROM couriers WHERE courier_id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $courier = $stmt->fetch();
}

// Get couriers list with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

$where = [];
$params = [];

if ($search) {
    $where[] = "(name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $where[] = "status = ?";
    $params[] = $status;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

$sql = "SELECT * FROM couriers $whereClause ORDER BY name ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$couriers = $stmt->fetchAll();

// Get total couriers count for pagination
$sql = "SELECT COUNT(*) FROM couriers $whereClause";
array_pop($params); // remove offset
array_pop($params); // remove limit
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$totalCouriers = $stmt->fetchColumn();
$totalPages = ceil($totalCouriers / $limit);

// Get courier performance stats
$courierStats = [];
if (!empty($couriers)) {
    $courierIds = array_column($couriers, 'courier_id');
    $placeholders = str_repeat('?,', count($courierIds) - 1) . '?';
    
    $sql = "SELECT courier_id, 
                   COUNT(*) as total_deliveries,
                   AVG(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as success_rate,
                   AVG(TIMESTAMPDIFF(MINUTE, created_at, delivered_at)) as avg_delivery_time
            FROM orders 
            WHERE courier_id IN ($placeholders)
            GROUP BY courier_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($courierIds);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($stats as $stat) {
        $courierStats[$stat['courier_id']] = $stat;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Couriers Management - Aquanest Admin</title>
    
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
                <h2 class="text-lg font-medium text-gray-900">Couriers Management</h2>
                
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

                <!-- Courier Form -->
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">
                            <?= $courier ? 'Edit Courier' : 'Add New Courier' ?>
                        </h3>
                    </div>
                    
                    <div class="p-6">
                        <form action="couriers.php" method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="<?= $courier ? 'update' : 'add' ?>">
                            <?php if ($courier): ?>
                                <input type="hidden" name="courier_id" value="<?= $courier['courier_id'] ?>">
                            <?php endif; ?>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Name</label>
                                    <input type="text" 
                                           name="name" 
                                           value="<?= $courier ? htmlspecialchars($courier['name']) : '' ?>"
                                           required 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                  focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Phone</label>
                                    <input type="tel" 
                                           name="phone" 
                                           value="<?= $courier ? htmlspecialchars($courier['phone']) : '' ?>"
                                           required 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                  focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Email</label>
                                    <input type="email" 
                                           name="email" 
                                           value="<?= $courier ? htmlspecialchars($courier['email']) : '' ?>"
                                           required 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                  focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Vehicle Type</label>
                                    <select name="vehicle_type" 
                                            required 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                   focus:border-blue-500 focus:ring-blue-500">
                                        <option value="motorcycle" <?= ($courier && $courier['vehicle_type'] === 'motorcycle') ? 'selected' : '' ?>>
                                            Motorcycle
                                        </option>
                                        <option value="car" <?= ($courier && $courier['vehicle_type'] === 'car') ? 'selected' : '' ?>>
                                            Car
                                        </option>
                                        <option value="truck" <?= ($courier && $courier['vehicle_type'] === 'truck') ? 'selected' : '' ?>>
                                            Truck
                                        </option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Vehicle Number</label>
                                    <input type="text" 
                                           name="vehicle_number" 
                                           value="<?= $courier ? htmlspecialchars($courier['vehicle_number']) : '' ?>"
                                           required 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                  focus:border-blue-500 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <div class="flex justify-end space-x-3">
                                <?php if ($courier): ?>
                                    <a href="couriers.php" 
                                       class="px-4 py-2 border rounded-md text-gray-700 hover:bg-gray-50 
                                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                        Cancel
                                    </a>
                                <?php endif; ?>
                                
                                <button type="submit" 
                                        class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 
                                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    <?= $courier ? 'Update Courier' : 'Add Courier' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Couriers List -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
                            <h3 class="text-lg font-medium text-gray-900">Couriers List</h3>
                            
                            <div class="mt-4 sm:mt-0">
                                <form action="couriers.php" method="GET" class="flex space-x-4">
                                    <input type="text" 
                                           name="search" 
                                           value="<?= htmlspecialchars($search) ?>"
                                           placeholder="Search couriers..." 
                                           class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 
                                                  focus:ring-blue-500">
                                    
                                    <select name="status" 
                                            class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 
                                                   focus:ring-blue-500">
                                        <option value="">All Status</option>
                                        <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>
                                            Available
                                        </option>
                                        <option value="on_delivery" <?= $status === 'on_delivery' ? 'selected' : '' ?>>
                                            On Delivery
                                        </option>
                                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>
                                            Inactive
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
                                        Courier
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Vehicle Info
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Performance
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($couriers as $item): ?>
                                    <tr>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?= htmlspecialchars($item['phone']) ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?= htmlspecialchars($item['email']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900">
                                                <?= ucfirst($item['vehicle_type']) ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?= htmlspecialchars($item['vehicle_number']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                       <?php
                                                       switch ($item['status']) {
                                                           case 'available':
                                                               echo 'bg-green-100 text-green-800';
                                                               break;
                                                           case 'on_delivery':
                                                               echo 'bg-blue-100 text-blue-800';
                                                               break;
                                                           default:
                                                               echo 'bg-gray-100 text-gray-800';
                                                       }
                                                       ?>">
                                                <?= ucfirst($item['status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if (isset($courierStats[$item['courier_id']])): ?>
                                                <div class="text-sm text-gray-900">
                                                    <?= $courierStats[$item['courier_id']]['total_deliveries'] ?> deliveries
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?= round($courierStats[$item['courier_id']]['success_rate'] * 100) ?>% success rate
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    Avg. <?= round($courierStats[$item['courier_id']]['avg_delivery_time'] / 60) ?> hours delivery time
                                                </div>
                                            <?php else: ?>
                                                <div class="text-sm text-gray-500">No deliveries yet</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-3">
                                            <a href="?id=<?= $item['courier_id'] ?>" 
                                               class="text-blue-600 hover:text-blue-900">
                                                Edit
                                            </a>
                                            
                                            <?php if ($item['status'] !== 'inactive'): ?>
                                                <form action="couriers.php" method="POST" class="inline">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="courier_id" value="<?= $item['courier_id'] ?>">
                                                    <button type="submit" 
                                                            onclick="return confirm('Are you sure you want to deactivate this courier?')"
                                                            class="text-red-600 hover:text-red-900">
                                                        Deactivate
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($item['status'] !== 'on_delivery'): ?>
                                                <form action="couriers.php" method="POST" class="inline">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="courier_id" value="<?= $item['courier_id'] ?>">
                                                    <input type="hidden" name="status" value="<?= $item['status'] === 'available' ? 'inactive' : 'available' ?>">
                                                    <button type="submit" 
                                                            class="<?= $item['status'] === 'available' ? 'text-yellow-600 hover:text-yellow-900' : 'text-green-600 hover:text-green-900' ?>">
                                                        <?= $item['status'] === 'available' ? 'Set Inactive' : 'Set Available' ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
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
                                    Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalCouriers) ?> 
                                    of <?= $totalCouriers ?> couriers
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
            </div>
        </div>
    </div>
</body>
</html>
