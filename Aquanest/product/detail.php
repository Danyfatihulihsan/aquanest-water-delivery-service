<?php
// Start session
session_start();

// Include database connection
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirect('../products.php');
}

// Get product details
$productId = $_GET['id'];
$product = getProductById($conn, $productId);

// If product not found
if (!$product) {
    setFlashMessage('danger', 'Produk tidak ditemukan.');
    redirect('../products.php');
}

// Get related products
$stmt = $conn->prepare("SELECT * FROM products WHERE product_id != :id AND is_active = TRUE ORDER BY RAND() LIMIT 4");
$stmt->bindParam(':id', $productId);
$stmt->execute();
$relatedProducts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product['name']; ?> - Aquanest</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/navbar.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navbar (with adjusted path) -->
    <?php include '../includes/navbar_detail.php'; ?>
    
    <!-- Product Detail Section -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <img src="../img/products/<?php echo $product['image']; ?>" class="img-fluid rounded" alt="<?php echo $product['name']; ?>">
                </div>
                <div class="col-md-6">
                    <h1><?php echo $product['name']; ?></h1>
                    <p class="text-muted mb-4">Kode Produk: AQNS-<?php echo str_pad($product['product_id'], 3, '0', STR_PAD_LEFT); ?></p>
                    <h2 class="text-primary mb-4"><?php echo formatRupiah($product['price']); ?></h2>
                    <p class="mb-4"><?php echo $product['description']; ?></p>
                    
                    <?php if ($product['stock'] > 0): ?>
                        <p class="text-success"><i class="fas fa-check-circle"></i> Stok tersedia (<?php echo $product['stock']; ?>)</p>
                    <?php else: ?>
                        <p class="text-danger"><i class="fas fa-times-circle"></i> Stok habis</p>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2 d-md-block mt-4">
                        <a href="../order.php?product=<?php echo $product['product_id']; ?>" class="btn btn-primary btn-lg">Pesan Sekarang</a>
                        <a href="#" class="btn btn-outline-secondary btn-lg ms-md-2" onclick="history.back()">Kembali</a>
                    </div>
                    
                    <div class="mt-5">
                        <h4>Keunggulan Produk</h4>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item"><i class="fas fa-tint text-primary me-2"></i> Air murni dengan pH seimbang</li>
                            <li class="list-group-item"><i class="fas fa-shield-alt text-primary me-2"></i> Dikemas secara higienis</li>
                            <li class="list-group-item"><i class="fas fa-award text-primary me-2"></i> Telah lulus uji laboratorium</li>
                            <li class="list-group-item"><i class="fas fa-check-double text-primary me-2"></i> Terjamin kebersihannya</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Product Description Details -->
    <section class="py-5 bg-light">
        <div class="container">
            <ul class="nav nav-tabs" id="productTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button" role="tab" aria-controls="description" aria-selected="true">Deskripsi</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="benefits-tab" data-bs-toggle="tab" data-bs-target="#benefits" type="button" role="tab" aria-controls="benefits" aria-selected="false">Manfaat</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="howto-tab" data-bs-toggle="tab" data-bs-target="#howto" type="button" role="tab" aria-controls="howto" aria-selected="false">Cara Penggunaan</button>
                </li>
            </ul>
            <div class="tab-content p-4 bg-white" id="productTabContent">
                <div class="tab-pane fade show active" id="description" role="tabpanel" aria-labelledby="description-tab">
                    <p><?php echo $product['description']; ?></p>
                    <p>Air minum Aquanest diproses menggunakan teknologi filtrasi modern yang memastikan air bebas dari kotoran, bakteri, dan zat berbahaya lainnya. Kami menggunakan sistem Reverse Osmosis (RO) yang mampu memfilter hingga tingkat molekuler, sehingga anda mendapatkan air yang benar-benar murni dan sehat.</p>
                    <p>Setiap produk Aquanest telah melalui proses sterilisasi UV dan ozonisasi untuk memastikan kebersihannya. Kami juga rutin melakukan pengujian kualitas air di laboratorium terakreditasi.</p>
                </div>
                <div class="tab-pane fade" id="benefits" role="tabpanel" aria-labelledby="benefits-tab">
                    <h4>Manfaat Air Minum Aquanest:</h4>
                    <ul>
                        <li>Membantu menjaga kesehatan tubuh</li>
                        <li>Meningkatkan fungsi metabolisme</li>
                        <li>Menjaga keseimbangan cairan tubuh</li>
                        <li>Membantu proses detoksifikasi alami tubuh</li>
                        <li>Menjaga kesehatan kulit</li>
                        <li>Mencegah dehidrasi</li>
                    </ul>
                    <p>Dengan mengonsumsi 8 gelas air putih setiap hari, Anda dapat menjaga kesehatan tubuh secara optimal. Air Aquanest membantu Anda memenuhi kebutuhan cairan harian dengan kualitas terbaik.</p>
                </div>
                <div class="tab-pane fade" id="howto" role="tabpanel" aria-labelledby="howto-tab">
                    <h4>Cara Penggunaan yang Tepat:</h4>
                    <ol>
                        <li>Simpan di tempat yang bersih dan sejuk</li>
                        <li>Hindari terkena sinar matahari langsung</li>
                        <li>Untuk galon, gunakan dispenser yang bersih dan rutin dibersihkan</li>
                        <li>Setelah dibuka, sebaiknya dikonsumsi dalam waktu 1-2 minggu</li>
                        <li>Pastikan tangan bersih sebelum menyentuh bagian yang bersentuhan dengan air</li>
                    </ol>
                    <p>Untuk pemesanan rutin, kami menyarankan untuk menjadwalkan pengiriman setiap 1-2 minggu sekali untuk menjaga ketersediaan air minum berkualitas di rumah atau kantor Anda.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Related Products -->
    <section class="py-5">
        <div class="container">
            <h2 class="mb-4">Produk Terkait</h2>
            <div class="row">
                <?php foreach ($relatedProducts as $related): ?>
                <div class="col-md-3 mb-4">
                    <div class="card h-100">
                        <img src="../img/products/<?php echo $related['image']; ?>" class="card-img-top" alt="<?php echo $related['name']; ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $related['name']; ?></h5>
                            <p class="card-text"><?php echo substr($related['description'], 0, 70); ?>...</p>
                            <p class="fw-bold"><?php echo formatRupiah($related['price']); ?></p>
                            <a href="detail.php?id=<?php echo $related['product_id']; ?>" class="btn btn-sm btn-outline-secondary">Detail</a>
                            <a href="../order.php?product=<?php echo $related['product_id']; ?>" class="btn btn-sm btn-primary">Pesan</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <!-- Footer (with adjusted path) -->
    <?php include '../includes/footer_detail.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>