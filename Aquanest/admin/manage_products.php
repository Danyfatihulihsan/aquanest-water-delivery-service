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
$page_title = "Kelola Produk";

// Process product operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new product
    if (isset($_POST['add_product'])) {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $price = sanitize($_POST['price']);
        $stock = sanitize($_POST['stock']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Handle image upload
        $image = 'default.jpg'; // Default image
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $uploadResult = uploadFile($_FILES['image'], '../img/products/');
            if ($uploadResult['success']) {
                $image = $uploadResult['file_name'];
            } else {
                setFlashMessage('warning', 'Gagal mengupload gambar: ' . $uploadResult['message'] . ' Menggunakan gambar default.');
            }
        }
        
        try {
            $stmt = $conn->prepare("INSERT INTO products (name, description, price, stock, image, is_active) 
                                  VALUES (:name, :description, :price, :stock, :image, :is_active)");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':stock', $stock);
            $stmt->bindParam(':image', $image);
            $stmt->bindParam(':is_active', $is_active);
            $stmt->execute();
            
            setFlashMessage('success', 'Produk berhasil ditambahkan.');
            redirect('manage_products.php');
        } catch (PDOException $e) {
            setFlashMessage('danger', 'Gagal menambahkan produk: ' . $e->getMessage());
        }
    }
    
    // Update product
    if (isset($_POST['update_product'])) {
        $product_id = $_POST['product_id'];
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $price = sanitize($_POST['price']);
        $stock = sanitize($_POST['stock']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            // Get current product data
            $stmt = $conn->prepare("SELECT image FROM products WHERE product_id = :id");
            $stmt->bindParam(':id', $product_id);
            $stmt->execute();
            $currentProduct = $stmt->fetch();
            
            $image = $currentProduct['image']; // Keep current image
            
            // Handle image upload if a new one is provided
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $uploadResult = uploadFile($_FILES['image'], '../img/products/');
                if ($uploadResult['success']) {
                    $image = $uploadResult['file_name'];
                } else {
                    setFlashMessage('warning', 'Gagal mengupload gambar baru: ' . $uploadResult['message'] . ' Tetap menggunakan gambar lama.');
                }
            }
            
            // Update product
            $stmt = $conn->prepare("UPDATE products SET 
                                  name = :name, 
                                  description = :description, 
                                  price = :price, 
                                  stock = :stock, 
                                  image = :image, 
                                  is_active = :is_active 
                                  WHERE product_id = :id");
                                  
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':stock', $stock);
            $stmt->bindParam(':image', $image);
            $stmt->bindParam(':is_active', $is_active);
            $stmt->bindParam(':id', $product_id);
            $stmt->execute();
            
            setFlashMessage('success', 'Produk berhasil diperbarui.');
            redirect('manage_products.php');
        } catch (PDOException $e) {
            setFlashMessage('danger', 'Gagal memperbarui produk: ' . $e->getMessage());
        }
    }
    
    // Delete product
    if (isset($_POST['delete_product'])) {
        $product_id = $_POST['product_id'];
        
        try {
            $stmt = $conn->prepare("DELETE FROM products WHERE product_id = :id");
            $stmt->bindParam(':id', $product_id);
            $stmt->execute();
            
            setFlashMessage('success', 'Produk berhasil dihapus.');
            redirect('manage_products.php');
        } catch (PDOException $e) {
            setFlashMessage('danger', 'Gagal menghapus produk: ' . $e->getMessage());
        }
    }
}

// Get products
try {
    // Get all products
    $stmt = $conn->query("SELECT * FROM products ORDER BY product_id DESC");
    $products = $stmt->fetchAll();
    
    // Get product stats
    $stmt = $conn->query("SELECT COUNT(*) as total FROM products");
    $totalProducts = $stmt->fetch()['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as active FROM products WHERE is_active = 1");
    $activeProducts = $stmt->fetch()['active'];
    
    $stmt = $conn->query("SELECT COUNT(*) as low_stock FROM products WHERE stock < 10");
    $lowStockProducts = $stmt->fetch()['low_stock'];
    
    $stmt = $conn->query("SELECT SUM(stock) as total_stock FROM products");
    $totalStock = $stmt->fetch()['total_stock'] ?: 0;
} catch (PDOException $e) {
    setFlashMessage('danger', 'Gagal mengambil data produk: ' . $e->getMessage());
    $products = [];
    $totalProducts = 0;
    $activeProducts = 0;
    $lowStockProducts = 0;
    $totalStock = 0;
}

// Include header
include 'includes/header.php';
?>

<link rel="stylesheet" href="css/dashboard.css">

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Kelola Produk</h1>
    <button class="btn btn-primary d-none d-md-block" data-bs-toggle="modal" data-bs-target="#addProductModal">
        <i class="fas fa-plus-circle me-1"></i> Tambah Produk Baru
    </button>
</div>

<?php displayFlashMessage(); ?>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stats-card bg-primary text-white">
            <div class="stats-icon bg-white text-primary">
                <i class="fas fa-box"></i>
            </div>
            <h5>Total Produk</h5>
            <h2><?php echo $totalProducts; ?></h2>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stats-card bg-success text-white">
            <div class="stats-icon bg-white text-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <h5>Produk Aktif</h5>
            <h2><?php echo $activeProducts; ?></h2>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stats-card bg-danger text-white">
            <div class="stats-icon bg-white text-danger">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <h5>Stok Menipis</h5>
            <h2><?php echo $lowStockProducts; ?></h2>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stats-card bg-info text-white">
            <div class="stats-icon bg-white text-info">
                <i class="fas fa-cubes"></i>
            </div>
            <h5>Total Stok</h5>
            <h2><?php echo $totalStock; ?></h2>
        </div>
    </div>
</div>

<!-- Mobile Add Button -->
<div class="d-md-none mb-4">
    <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#addProductModal">
        <i class="fas fa-plus-circle me-1"></i> Tambah Produk Baru
    </button>
</div>

<!-- Products Table -->
<div class="card data-card">
    <div class="card-header">
        <h5 class="mb-0">Daftar Produk</h5>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table class="table table-striped admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Gambar</th>
                        <th>Nama Produk</th>
                        <th class="d-none d-md-table-cell">Deskripsi</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo $product['product_id']; ?></td>
                                <td>
                                    <img src="../img/products/<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>" class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                </td>
                                <td><?php echo $product['name']; ?></td>
                                <td class="d-none d-md-table-cell"><?php echo substr($product['description'], 0, 50); ?>...</td>
                                <td><?php echo formatRupiah($product['price']); ?></td>
                                <td>
                                    <?php if ($product['stock'] < 10): ?>
                                        <span class="text-danger fw-bold"><?php echo $product['stock']; ?></span>
                                    <?php else: ?>
                                        <?php echo $product['stock']; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($product['is_active']): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Tidak Aktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-info edit-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editProductModal"
                                                data-id="<?php echo $product['product_id']; ?>"
                                                data-name="<?php echo $product['name']; ?>"
                                                data-description="<?php echo htmlspecialchars($product['description']); ?>"
                                                data-price="<?php echo $product['price']; ?>"
                                                data-stock="<?php echo $product['stock']; ?>"
                                                data-active="<?php echo $product['is_active']; ?>"
                                                data-image="<?php echo $product['image']; ?>"
                                                title="Edit Produk">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger delete-btn"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteProductModal"
                                                data-id="<?php echo $product['product_id']; ?>"
                                                data-name="<?php echo $product['name']; ?>"
                                                title="Hapus Produk">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Belum ada produk tersedia.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addProductModalLabel">Tambah Produk Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="manage_products.php" enctype="multipart/form-data" class="admin-form">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Nama Produk <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="price" class="form-label">Harga (Rp) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="price" name="price" required>
                        </div>
                        <div class="col-md-6">
                            <label for="stock" class="form-label">Stok <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="stock" name="stock" required>
                        </div>
                        <div class="col-md-6">
                            <label for="image" class="form-label">Gambar Produk</label>
                            <input type="file" class="form-control image-upload" id="image" name="image" accept="image/*" data-preview="image_preview">
                            <small class="text-muted">Format: JPG, JPEG, PNG (Max: 5MB)</small>
                            <div class="mt-2">
                                <img id="image_preview" src="" alt="Preview" class="img-thumbnail" style="max-height: 100px; display: none;">
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Deskripsi Produk <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                <label class="form-check-label" for="is_active">
                                    Produk Aktif
                                </label>
                                <small class="text-muted d-block">Jika dicentang, produk akan ditampilkan di website.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add_product" class="btn btn-primary">Simpan Produk</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="editProductModalLabel">Edit Produk</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="manage_products.php" enctype="multipart/form-data" class="admin-form">
                <input type="hidden" id="edit_product_id" name="product_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_name" class="form-label">Nama Produk <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_price" class="form-label">Harga (Rp) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit_price" name="price" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_stock" class="form-label">Stok <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit_stock" name="stock" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_image" class="form-label">Gambar Produk</label>
                            <input type="file" class="form-control image-upload" id="edit_image" name="image" accept="image/*" data-preview="current_image">
                            <small class="text-muted">Upload gambar baru atau biarkan kosong untuk tetap menggunakan gambar lama.</small>
                            <div class="mt-2">
                                <img id="current_image" src="" alt="Current Image" class="img-thumbnail" style="max-height: 100px;">
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="edit_description" class="form-label">Deskripsi Produk <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="edit_description" name="description" rows="4" required></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                                <label class="form-check-label" for="edit_is_active">
                                    Produk Aktif
                                </label>
                                <small class="text-muted d-block">Jika dicentang, produk akan ditampilkan di website.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="update_product" class="btn btn-info">Perbarui Produk</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Product Modal -->
<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteProductModalLabel">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Anda yakin ingin menghapus produk <strong id="delete_product_name"></strong>?</p>
                <p class="text-danger">Tindakan ini tidak dapat dibatalkan dan akan menghapus seluruh data produk dari sistem.</p>
            </div>
            <div class="modal-footer">
                <form method="post" action="manage_products.php">
                    <input type="hidden" id="delete_product_id" name="product_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="delete_product" class="btn btn-danger">Hapus Produk</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Include footer -->
<?php include 'includes/footer.php'; ?>

<!-- Custom JavaScript -->

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Image preview functionality
        const imageInputs = document.querySelectorAll('.image-upload');
        imageInputs.forEach(input => {
            input.addEventListener('change', function() {
                const previewId = this.getAttribute('data-preview');
                const preview = document.getElementById(previewId);
                
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });
        });
        
        // Edit product modal
        const editButtons = document.querySelectorAll('.edit-btn');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const description = this.getAttribute('data-description');
                const price = this.getAttribute('data-price');
                const stock = this.getAttribute('data-stock');
                const active = this.getAttribute('data-active');
                const image = this.getAttribute('data-image');
                
                document.getElementById('edit_product_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_price').value = price;
                document.getElementById('edit_stock').value = stock;
                document.getElementById('edit_is_active').checked = active == 1;
                document.getElementById('current_image').src = '../img/products/' + image;
            });
        });
        
        // Delete product modal
        const deleteButtons = document.querySelectorAll('.delete-btn');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                document.getElementById('delete_product_id').value = id;
                document.getElementById('delete_product_name').textContent = name;
            });
        });
    });
</script>
<script src="js/dashboard.js"></script>