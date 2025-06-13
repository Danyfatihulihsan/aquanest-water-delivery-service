<?php
// admin_couriers.php
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
    
    // Add new courier
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_courier') {
        try {
            $sql = "INSERT INTO couriers (courier_name, courier_phone, email, vehicle_type, vehicle_brand, vehicle_model, vehicle_plate, vehicle_number, address, joined_date, is_active, status) 
                    VALUES (:name, :phone, :email, :vehicle_type, :vehicle_brand, :vehicle_model, :vehicle_plate, :vehicle_plate, :address, CURDATE(), 1, 'available')";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':name', $_POST['name']);
            $stmt->bindParam(':phone', $_POST['phone']);
            $stmt->bindParam(':email', $_POST['email']);
            $stmt->bindParam(':vehicle_type', $_POST['vehicle_type']);
            $stmt->bindParam(':vehicle_brand', $_POST['vehicle_brand']);
            $stmt->bindParam(':vehicle_model', $_POST['vehicle_model']);
            $stmt->bindParam(':vehicle_plate', $_POST['vehicle_plate']);
            $stmt->bindParam(':address', $_POST['address']);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Kurir berhasil ditambahkan']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Update courier status
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
        try {
            $sql = "UPDATE couriers SET status = :status WHERE courier_id = :courier_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':status', $_POST['status']);
            $stmt->bindParam(':courier_id', $_POST['courier_id']);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Toggle courier active status
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_active') {
        try {
            $sql = "UPDATE couriers SET is_active = NOT is_active WHERE courier_id = :courier_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':courier_id', $_POST['courier_id']);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Get courier detail
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_courier') {
        try {
            $sql = "SELECT * FROM couriers WHERE courier_id = :courier_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':courier_id', $_GET['courier_id']);
            $stmt->execute();
            $courier = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'courier' => $courier]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Update courier data
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_courier') {
        try {
            $sql = "UPDATE couriers SET 
                    courier_name = :name,
                    courier_phone = :phone,
                    email = :email,
                    vehicle_type = :vehicle_type,
                    vehicle_brand = :vehicle_brand,
                    vehicle_model = :vehicle_model,
                    vehicle_plate = :vehicle_plate,
                    vehicle_number = :vehicle_plate,
                    address = :address
                    WHERE courier_id = :courier_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':courier_id', $_POST['courier_id']);
            $stmt->bindParam(':name', $_POST['name']);
            $stmt->bindParam(':phone', $_POST['phone']);
            $stmt->bindParam(':email', $_POST['email']);
            $stmt->bindParam(':vehicle_type', $_POST['vehicle_type']);
            $stmt->bindParam(':vehicle_brand', $_POST['vehicle_brand']);
            $stmt->bindParam(':vehicle_model', $_POST['vehicle_model']);
            $stmt->bindParam(':vehicle_plate', $_POST['vehicle_plate']);
            $stmt->bindParam(':address', $_POST['address']);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Data kurir berhasil diperbarui']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
}

// Get all couriers
$sql = "SELECT c.courier_id, c.courier_name as name, c.courier_phone as phone, 
        c.email, c.vehicle_type, c.vehicle_brand, c.vehicle_model, 
        c.vehicle_plate, c.vehicle_number, c.address, c.status, c.is_active,
        c.joined_date, c.created_at,
        COUNT(DISTINCT CASE WHEN DATE(cd.assigned_at) = CURDATE() THEN cd.delivery_id END) as today_deliveries,
        COUNT(DISTINCT CASE WHEN cd.status = 'delivered' THEN cd.delivery_id END) as total_deliveries
        FROM couriers c
        LEFT JOIN courier_deliveries cd ON c.courier_id = cd.courier_id
        GROUP BY c.courier_id
        ORDER BY c.courier_id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$couriers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [
    'total' => count($couriers),
    'available' => count(array_filter($couriers, function($c) { return $c['status'] == 'available' && $c['is_active']; })),
    'on_delivery' => count(array_filter($couriers, function($c) { return $c['status'] == 'on_delivery'; })),
    'off_duty' => count(array_filter($couriers, function($c) { return $c['status'] == 'off_duty' || !$c['is_active']; }))
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kurir - Admin Aquanest</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="">
</head>
<body class="bg-gray-100">
    <!-- Navbar Admin -->
    <nav class="bg-blue-800 text-white shadow-lg">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <h1 class="text-xl font-bold">Admin Panel - Aquanest</h1>
                <div class="flex gap-4">
                    <a href="dashboard.php" class="hover:text-blue-200">Dashboard</a>
                    <a href="admin_orders.php" class="hover:text-blue-200">Pesanan</a>
                    <a href="admin_couriers.php" class="text-blue-200">Kurir</a>
                    <a href="logout.php" class="hover:text-blue-200">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Manajemen Kurir</h2>
            <button onclick="openAddCourierModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                <i class="fas fa-plus"></i>
                Tambah Kurir
            </button>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Kurir</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total']; ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fas fa-users text-blue-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Kurir Tersedia</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo $stats['available']; ?></p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Sedang Antar</p>
                        <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['on_delivery']; ?></p>
                    </div>
                    <div class="bg-yellow-100 p-3 rounded-full">
                        <i class="fas fa-motorcycle text-yellow-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Off Duty</p>
                        <p class="text-2xl font-bold text-gray-600"><?php echo $stats['off_duty']; ?></p>
                    </div>
                    <div class="bg-gray-100 p-3 rounded-full">
                        <i class="fas fa-moon text-gray-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Couriers Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Daftar Kurir</h3>
                    <div class="flex gap-2">
                        <input type="text" id="searchInput" placeholder="Cari kurir..." class="px-3 py-1 border rounded-lg text-sm">
                        <select id="statusFilter" class="px-3 py-1 border rounded-lg text-sm">
                            <option value="">Semua Status</option>
                            <option value="available">Available</option>
                            <option value="on_delivery">On Delivery</option>
                            <option value="off_duty">Off Duty</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kurir</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kontak</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kendaraan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statistik</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($couriers as $courier): ?>
                        <tr data-name="<?php echo strtolower($courier['name']); ?>" data-status="<?php echo $courier['status']; ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 flex-shrink-0 rounded-full bg-gray-200 flex items-center justify-center">
                                        <i class="fas fa-user text-gray-500"></i>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($courier['name']); ?></div>
                                        <div class="text-sm text-gray-500">ID: #<?php echo str_pad($courier['courier_id'], 3, '0', STR_PAD_LEFT); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($courier['phone']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($courier['email'] ?: '-'); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php echo ucfirst($courier['vehicle_type']); ?> - <?php echo $courier['vehicle_brand'] . ' ' . $courier['vehicle_model']; ?>
                                </div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($courier['vehicle_plate']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if (!$courier['is_active']): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                        Nonaktif
                                    </span>
                                <?php else: ?>
                                    <select onchange="updateCourierStatus(<?php echo $courier['courier_id']; ?>, this.value)" 
                                            class="text-xs font-semibold rounded px-2 py-1 
                                            <?php echo $courier['status'] == 'available' ? 'bg-green-100 text-green-800' : 
                                                      ($courier['status'] == 'on_delivery' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); ?>">
                                        <option value="available" <?php echo $courier['status'] == 'available' ? 'selected' : ''; ?>>Available</option>
                                        <option value="on_delivery" <?php echo $courier['status'] == 'on_delivery' ? 'selected' : ''; ?>>On Delivery</option>
                                        <option value="off_duty" <?php echo $courier['status'] == 'off_duty' ? 'selected' : ''; ?>>Off Duty</option>
                                    </select>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div>Hari ini: <?php echo $courier['today_deliveries']; ?> pengiriman</div>
                                <div class="text-xs text-gray-500">Total: <?php echo $courier['total_deliveries']; ?> pengiriman</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="viewCourierDetail(<?php echo $courier['courier_id']; ?>)" class="text-blue-600 hover:text-blue-900 mr-3">Detail</button>
                                <button onclick="editCourier(<?php echo $courier['courier_id']; ?>)" class="text-yellow-600 hover:text-yellow-900 mr-3">Edit</button>
                                <button onclick="toggleCourierActive(<?php echo $courier['courier_id']; ?>, <?php echo $courier['is_active'] ? 'false' : 'true'; ?>)" 
                                        class="<?php echo $courier['is_active'] ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'; ?>">
                                    <?php echo $courier['is_active'] ? 'Nonaktifkan' : 'Aktifkan'; ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Courier Modal -->
    <div id="addCourierModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-lg bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900">Tambah Kurir Baru</h3>
                <button onclick="closeAddCourierModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="addCourierForm">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Nama Lengkap</label>
                    <input type="text" name="name" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">No. Telepon</label>
                    <input type="tel" name="phone" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                    <input type="email" name="email" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Jenis Kendaraan</label>
                    <select name="vehicle_type" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
                        <option value="">Pilih Jenis</option>
                        <option value="motor">Motor</option>
                        <option value="mobil">Mobil</option>
                        <option value="pickup">Pickup</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Merk</label>
                        <input type="text" name="vehicle_brand" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Model</label>
                        <input type="text" name="vehicle_model" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Plat Nomor</label>
                    <input type="text" name="vehicle_plate" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Alamat</label>
                    <textarea name="address" rows="2" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeAddCourierModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Courier Modal -->
    <div id="editCourierModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-lg bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900">Edit Data Kurir</h3>
                <button onclick="closeEditCourierModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="editCourierForm">
                <input type="hidden" name="courier_id" id="edit_courier_id">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Nama Lengkap</label>
                    <input type="text" name="name" id="edit_name" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">No. Telepon</label>
                    <input type="tel" name="phone" id="edit_phone" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                    <input type="email" name="email" id="edit_email" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Jenis Kendaraan</label>
                    <select name="vehicle_type" id="edit_vehicle_type" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
                        <option value="">Pilih Jenis</option>
                        <option value="motor">Motor</option>
                        <option value="mobil">Mobil</option>
                        <option value="pickup">Pickup</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Merk</label>
                        <input type="text" name="vehicle_brand" id="edit_vehicle_brand" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Model</label>
                        <input type="text" name="vehicle_model" id="edit_vehicle_model" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Plat Nomor</label>
                    <input type="text" name="vehicle_plate" id="edit_vehicle_plate" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Alamat</label>
                    <textarea name="address" id="edit_address" rows="2" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeEditCourierModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Detail Courier Modal -->
    <div id="detailCourierModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-lg bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900">Detail Kurir</h3>
                <button onclick="closeDetailCourierModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="detailCourierContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Search and filter functionality
        $('#searchInput').on('keyup', function() {
            filterTable();
        });

        $('#statusFilter').on('change', function() {
            filterTable();
        });

        function filterTable() {
            const searchTerm = $('#searchInput').val().toLowerCase();
            const statusFilter = $('#statusFilter').val();

            $('tbody tr').each(function() {
                const name = $(this).data('name');
                const status = $(this).data('status');
                
                let showRow = true;
                
                if (searchTerm && !name.includes(searchTerm)) {
                    showRow = false;
                }
                
                if (statusFilter && status !== statusFilter) {
                    showRow = false;
                }
                
                $(this).toggle(showRow);
            });
        }

        // Update courier status
        function updateCourierStatus(courierId, status) {
            $.post('admin_couriers.php', {
                action: 'update_status',
                courier_id: courierId,
                status: status
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            }, 'json');
        }

        // Toggle courier active status
        function toggleCourierActive(courierId, makeActive) {
            const action = makeActive ? 'mengaktifkan' : 'menonaktifkan';
            if (confirm(`Apakah Anda yakin ingin ${action} kurir ini?`)) {
                $.post('admin_couriers.php', {
                    action: 'toggle_active',
                    courier_id: courierId
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }, 'json');
            }
        }

        // Modal functions
        function openAddCourierModal() {
            $('#addCourierModal').removeClass('hidden');
        }

        function closeAddCourierModal() {
            $('#addCourierModal').addClass('hidden');
            $('#addCourierForm')[0].reset();
        }

        function openEditCourierModal() {
            $('#editCourierModal').removeClass('hidden');
        }

        function closeEditCourierModal() {
            $('#editCourierModal').addClass('hidden');
            $('#editCourierForm')[0].reset();
        }

        function closeDetailCourierModal() {
            $('#detailCourierModal').addClass('hidden');
        }

        // Edit courier
        function editCourier(courierId) {
            $.get('admin_couriers.php', {
                action: 'get_courier',
                courier_id: courierId
            }, function(response) {
                if (response.success) {
                    const courier = response.courier;
                    $('#edit_courier_id').val(courier.courier_id);
                    $('#edit_name').val(courier.courier_name || courier.name);
                    $('#edit_phone').val(courier.courier_phone || courier.phone);
                    $('#edit_email').val(courier.email);
                    $('#edit_vehicle_type').val(courier.vehicle_type);
                    $('#edit_vehicle_brand').val(courier.vehicle_brand);
                    $('#edit_vehicle_model').val(courier.vehicle_model);
                    $('#edit_vehicle_plate').val(courier.vehicle_plate);
                    $('#edit_address').val(courier.address);
                    openEditCourierModal();
                }
            }, 'json');
        }

        // View courier detail
        function viewCourierDetail(courierId) {
            window.location.href = 'courier_detail.php?id=' + courierId;
        }

        // Handle add form submission
        $('#addCourierForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = $(this).serialize() + '&action=add_courier';
            
            $.post('admin_couriers.php', formData, function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            }, 'json');
        });

        // Handle edit form submission
        $('#editCourierForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = $(this).serialize() + '&action=update_courier';
            
            $.post('admin_couriers.php', formData, function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            }, 'json');
        });
    </script>
</body>
</html>