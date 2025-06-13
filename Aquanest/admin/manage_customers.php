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

// Include database connection and functions
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Set page title
$page_title = "Kelola Pelanggan";

// Handle search
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$query = "SELECT * FROM customers";
$params = [];

// Apply search filter if provided
if (!empty($search)) {
    $query .= " WHERE name LIKE :search1 OR email LIKE :search2 OR phone LIKE :search3 OR address LIKE :search4";
    $searchValue = "%$search%";
    $params[':search1'] = $searchValue;
    $params[':search2'] = $searchValue;
    $params[':search3'] = $searchValue;
    $params[':search4'] = $searchValue;
}

// Order by latest first
$query .= " ORDER BY created_at DESC";

// Get customers
try {
    $stmt = $conn->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    setFlashMessage('danger', 'Gagal mengambil data pelanggan: ' . $e->getMessage());
    $customers = [];
}

// Get customer count
try {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM customers");
    $customerCount = $stmt->fetch()['total'];
} catch (PDOException $e) {
    $customerCount = 0;
}

// Get new customers this month
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM customers WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $stmt->execute();
    $newCustomersThisMonth = $stmt->fetch()['total'];
} catch (PDOException $e) {
    $newCustomersThisMonth = 0;
}

// Include header (with new template)
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Kelola Pelanggan</h1>
    <button class="btn btn-sm btn-outline-secondary d-none d-md-block" data-bs-toggle="modal" data-bs-target="#exportModal">
        <i class="fas fa-file-export me-1"></i> Export Data
    </button>
</div>

<?php displayFlashMessage(); ?>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-4 col-sm-6 mb-4">
        <div class="stats-card bg-primary text-white">
            <div class="stats-icon bg-white text-primary">
                <i class="fas fa-users"></i>
            </div>
            <h5>Total Pelanggan</h5>
            <h2><?php echo $customerCount; ?></h2>
        </div>
    </div>
    <div class="col-md-4 col-sm-6 mb-4">
        <div class="stats-card bg-success text-white">
            <div class="stats-icon bg-white text-success">
                <i class="fas fa-user-plus"></i>
            </div>
            <h5>Pelanggan Baru Bulan Ini</h5>
            <h2><?php echo $newCustomersThisMonth; ?></h2>
        </div>
    </div>
    <div class="col-md-4 col-sm-12 mb-4">
        <div class="stats-card bg-info text-white">
            <div class=" bg-white text-info">
            </div>
            <h5>Cari Pelanggan</h5>
            <form method="get" action="manage_customers.php" class="mt-2">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="Nama, email, telepon..." value="<?php echo $search; ?>">
                    <button class="btn btn-light" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mobile Export Button -->
<div class="d-md-none mb-4">
    <button class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#exportModal">
        <i class="fas fa-file-export me-1"></i> Export Data Pelanggan
    </button>
</div>

<!-- Customers Table -->
<div class="card data-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Pelanggan</h5>
        
        <?php if (!empty($search)): ?>
            <a href="manage_customers.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-sync-alt me-1"></i> Reset Pencarian
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table class="table table-striped admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th class="d-none d-md-table-cell">Email</th>
                        <th>Telepon</th>
                        <th class="d-none d-md-table-cell">Alamat</th>
                        <th class="d-none d-md-table-cell">Tanggal Bergabung</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($customers) > 0): ?>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><?php echo $customer['customer_id']; ?></td>
                                <td><?php echo $customer['name']; ?></td>
                                <td class="d-none d-md-table-cell"><?php echo $customer['email'] ? $customer['email'] : '-'; ?></td>
                                <td><?php echo $customer['phone']; ?></td>
                                <td class="d-none d-md-table-cell"><?php echo substr($customer['address'], 0, 30); ?>...</td>
                                <td class="d-none d-md-table-cell"><?php echo date('d-m-Y', strtotime($customer['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-info view-customer-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewCustomerModal"
                                                data-id="<?php echo $customer['customer_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($customer['name']); ?>"
                                                data-email="<?php echo htmlspecialchars($customer['email']); ?>"
                                                data-phone="<?php echo htmlspecialchars($customer['phone']); ?>"
                                                data-address="<?php echo htmlspecialchars($customer['address']); ?>"
                                                data-created="<?php echo date('d-m-Y H:i', strtotime($customer['created_at'])); ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="customer_orders.php?id=<?php echo $customer['customer_id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-shopping-cart"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Tidak ada pelanggan yang ditemukan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View Customer Modal -->
<div class="modal fade" id="viewCustomerModal" tabindex="-1" aria-labelledby="viewCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewCustomerModalLabel">Detail Pelanggan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>ID Pelanggan:</strong> <span id="view_customer_id"></span></p>
                        <p><strong>Nama:</strong> <span id="view_customer_name"></span></p>
                        <p><strong>Email:</strong> <span id="view_customer_email"></span></p>
                        <p><strong>Telepon:</strong> <span id="view_customer_phone"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Tanggal Bergabung:</strong> <span id="view_customer_created"></span></p>
                        <p><strong>Alamat:</strong></p>
                        <p id="view_customer_address" class="border p-2 bg-light" style="max-height: 150px; overflow-y: auto;"></p>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <a href="#" id="view_customer_orders_link" class="btn btn-primary">
                        <i class="fas fa-shopping-cart me-1"></i> Lihat Pesanan Pelanggan
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="exportModalLabel">Export Data Pelanggan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="get" action="export_customers.php">
                    <div class="mb-3">
                        <label for="export_format" class="form-label">Format Export</label>
                        <select class="form-select" id="export_format" name="format" required>
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <?php if (!empty($search)): ?>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="export_current" name="export_current" checked>
                                <label class="form-check-label" for="export_current">
                                    Hanya export data pencarian saat ini
                                </label>
                                <input type="hidden" name="search" value="<?php echo $search; ?>">
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-download me-2"></i> Download
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script src="js/dashboard.js"></script>
<!-- Include footer (with new template) -->
