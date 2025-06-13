<?php
// Start session
session_start();

// Include database connection
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get all products
$products = getAllProducts($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produk Kami - Aquanest</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/navbar.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>
    
     <!-- Products Banner -->
     <div class="bg-primary text-white py-5">
        <div class="container">
            <h1 class="display-4 fw-bold">Produk Kami</h1>
            <p class="lead">Temukan berbagai produk air minum berkualitas untuk kebutuhan Anda</p>
        </div>
    </div>
    
    <!-- Products Section -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $product): ?>
                    <div class="col-md-3 mb-3">
                        <div class="card h-80">
                            <img src="img/products/<?php echo $product['image']; ?>" class="card-img-top" alt="<?php echo $product['name']; ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $product['name']; ?></h5>
                                <p class="card-text"><?php echo $product['description']; ?></p>
                                <p class="fw-bold"><?php echo formatRupiah($product['price']); ?></p>
                                <a href="product/detail.php?id=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-outline-secondary">Detail</a>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <a href="order.php?id=<?= $product_id ?>" class="btn btn-sm btn-primary">Pesan Sekarang</a>
                                <?php else: ?>
                                    <a href="login.php?redirect=login" class="btn btn-sm btn-primary">Login untuk Memesan</a>
                                <?php endif; ?>

                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <p>Tidak ada produk tersedia saat ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <!-- Water Process Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2>Proses Pengolahan Air Berkualitas</h2>
                    <p>Di Aquanest, kami menjamin setiap tetes air yang kami produksi melewati 6 tahap filtrasi ketat:</p>
                    <ol class="list-group list-group-numbered mb-4">
                        <li class="list-group-item">Penyaringan sedimen untuk menghilangkan partikel kasar</li>
                        <li class="list-group-item">Penyaringan karbon aktif untuk menghilangkan klorin dan bau</li>
                        <li class="list-group-item">Reverse Osmosis untuk memurnikan air dari mineral berbahaya</li>
                        <li class="list-group-item">Sterilisasi UV untuk membunuh bakteri dan virus</li>
                        <li class="list-group-item">Ozonisasi untuk menjaga kesegaran air</li>
                        <li class="list-group-item">Pengujian kualitas sebelum didistribusikan</li>
                    </ol>
                    <a href="about.php" class="btn btn-primary">Pelajari Lebih Lanjut</a>
                </div>
                <div class="col-md-6">
                    <img src="img/water-process.jpg" class="img-fluid rounded" alt="Proses Pengolahan Air">
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<script src="js/navbar.js"></script>