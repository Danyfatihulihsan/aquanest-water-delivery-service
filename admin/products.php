<?php
session_start();
require_once '../includes/functions.php';

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Handle product operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        switch ($_POST['action']) {
            case 'add':
                $sql = "INSERT INTO products (name, description, price, stock, status) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    sanitize($_POST['name']),
                    sanitize($_POST['description']),
                    (float)$_POST['price'],
                    (int)$_POST['stock'],
                    'active'
                ]);
                setFlashMessage('success', 'Product added successfully');
                break;
                
            case 'update':
                $sql = "UPDATE products SET name = ?, description = ?, price = ?, stock = ? WHERE product_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    sanitize($_POST['name']),
                    sanitize($_POST['description']),
                    (float)$_POST['price'],
                    (int)$_POST['stock'],
                    (int)$_POST['product_id']
                ]);
                setFlashMessage('success', 'Product updated successfully');
                break;
                
            case 'delete':
                $sql = "UPDATE products SET status = 'deleted' WHERE product_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([(int)$_POST['product_id']]);
                setFlashMessage('success', 'Product deleted successfully');
                break;
                
            case 'update_stock':
                $sql = "UPDATE products SET stock = stock + ? WHERE product_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    (int)$_POST['quantity'],
                    (int)$_POST['product_id']
                ]);
                setFlashMessage('success', 'Stock updated successfully');
                break;
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error in products.php: " . $e->getMessage());
        setFlashMessage('error', 'An error occurred. Please try again.');
    }
    
    // Redirect to prevent form resubmission
    header("Location: products.php");
    exit();
}

// Get product details if ID is provided
$product = null;
if (isset($_GET['id'])) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $product = $stmt->fetch();
}

// Get products list with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

$where = ["status != 'deleted'"];
$params = [];

if ($search) {
    $where[] = "(name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = "WHERE " . implode(" AND ", $where);

$sql = "SELECT * FROM products $whereClause ORDER BY name ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get total products count for pagination
$sql = "SELECT COUNT(*) FROM products $whereClause";
array_pop($params); // remove offset
array_pop($params); // remove limit
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$totalProducts = $stmt->fetchColumn();
$totalPages = ceil($totalProducts / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - Aquanest Admin</title>
    
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
                <h2 class="text-lg font-medium text-gray-900">Products Management</h2>
                
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

                <!-- Product Form -->
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">
                            <?= $product ? 'Edit Product' : 'Add New Product' ?>
                        </h3>
                    </div>
                    
                    <div class="p-6">
                        <form action="products.php" method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="<?= $product ? 'update' : 'add' ?>">
                            <?php if ($product): ?>
                                <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                            <?php endif; ?>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Product Name</label>
                                    <input type="text" 
                                           name="name" 
                                           value="<?= $product ? htmlspecialchars($product['name']) : '' ?>"
                                           required 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                  focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Price</label>
                                    <input type="number" 
                                           name="price" 
                                           value="<?= $product ? $product['price'] : '' ?>"
                                           required 
                                           min="0" 
                                           step="0.01"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                  focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Stock</label>
                                    <input type="number" 
                                           name="stock" 
                                           value="<?= $product ? $product['stock'] : '' ?>"
                                           required 
                                           min="0"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                  focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Description</label>
                                    <textarea name="description" 
                                              required 
                                              rows="3"
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                     focus:border-blue-500 focus:ring-blue-500"><?= $product ? htmlspecialchars($product['description']) : '' ?></textarea>
                                </div>
                            </div>
                            
                            <div class="flex justify-end space-x-3">
                                <?php if ($product): ?>
                                    <a href="products.php" 
                                       class="px-4 py-2 border rounded-md text-gray-700 hover:bg-gray-50 
                                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                        Cancel
                                    </a>
                                <?php endif; ?>
                                
                                <button type="submit" 
                                        class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 
                                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    <?= $product ? 'Update Product' : 'Add Product' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Products List -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
                            <h3 class="text-lg font-medium text-gray-900">Products List</h3>
                            
                            <div class="mt-4 sm:mt-0">
                                <form action="products.php" method="GET" class="flex space-x-4">
                                    <input type="text" 
                                           name="search" 
                                           value="<?= htmlspecialchars($search) ?>"
                                           placeholder="Search products..." 
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
                                        Product
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Price
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Stock
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($products as $item): ?>
                                    <tr>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?= htmlspecialchars($item['description']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= formatRupiah($item['price']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?= $item['stock'] ?> units</div>
                                            <?php if ($item['stock'] < 10): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    Low Stock
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                       <?= $item['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                                <?= ucfirst($item['status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-3">
                                            <a href="?id=<?= $item['product_id'] ?>" 
                                               class="text-blue-600 hover:text-blue-900">
                                                Edit
                                            </a>
                                            
                                            <form action="products.php" method="POST" class="inline">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                                <button type="submit" 
                                                        onclick="return confirm('Are you sure you want to delete this product?')"
                                                        class="text-red-600 hover:text-red-900">
                                                    Delete
                                                </button>
                                            </form>
                                            
                                            <button onclick="showStockUpdateModal(<?= $item['product_id'] ?>)" 
                                                    class="text-green-600 hover:text-green-900">
                                                Update Stock
                                            </button>
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
                                    Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalProducts) ?> 
                                    of <?= $totalProducts ?> products
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
            </div>
        </div>
    </div>

    <!-- Stock Update Modal -->
    <div id="stockUpdateModal" 
         class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden"
         onclick="hideStockUpdateModal()">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6" 
                 onclick="event.stopPropagation()">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Update Stock</h3>
                
                <form action="products.php" method="POST">
                    <input type="hidden" name="action" value="update_stock">
                    <input type="hidden" name="product_id" id="stockUpdateProductId">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Quantity to Add/Remove</label>
                            <input type="number" 
                                   name="quantity" 
                                   required 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                          focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="Enter positive number to add, negative to remove">
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" 
                                    onclick="hideStockUpdateModal()"
                                    class="px-4 py-2 border rounded-md text-gray-700 hover:bg-gray-50 
                                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 
                                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Update Stock
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showStockUpdateModal(productId) {
            document.getElementById('stockUpdateProductId').value = productId;
            document.getElementById('stockUpdateModal').classList.remove('hidden');
        }
        
        function hideStockUpdateModal() {
            document.getElementById('stockUpdateModal').classList.add('hidden');
        }
    </script>
</body>
</html>
