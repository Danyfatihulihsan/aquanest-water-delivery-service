<?php
// Start session
session_start();

// Include database connection
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get featured products
$stmt = $conn->query("SELECT * FROM products WHERE is_active = TRUE ORDER BY product_id DESC LIMIT 4");
$featuredProducts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aquanest - Depot Air Minum Berkualitas</title>
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
    
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-md-6">
                    <h1 class="display-4 fw-bold">Air Minum Murni & Sehat untuk Keluarga Anda</h1>
                    <p class="lead">Aquanest menyediakan air minum berkualitas tinggi dengan proses filtrasi modern untuk memastikan kesehatan Anda dan keluarga.</p>
                    <div class="mt-4">
                        <a href="products.php" class="btn btn-primary btn-lg me-2">Lihat Produk</a>
                        <a href="order.php" class="btn btn-outline-primary btn-lg">Pesan Sekarang</a>
                    </div>
                </div>
                <div class="col-md-6">
                    <img src="img/border.jpg" class="img-fluid rounded" alt="Aquanest Water">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Features Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center">
                <div class="col-12">
                    <h2 class="mb-5">Mengapa Memilih Aquanest?</h2>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-box p-4 bg-white rounded shadow-sm">
                        <i class="fas fa-tint fa-3x text-primary mb-3"></i>
                        <h3>Murni & Higienis</h3>
                        <p>Air kami diproses dengan teknologi filtrasi modern untuk memastikan kemurnian dan kebersihan.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-box p-4 bg-white rounded shadow-sm">
                        <i class="fas fa-truck fa-3x text-primary mb-3"></i>
                        <h3>Pengiriman Cepat</h3>
                        <p>Layanan pengiriman cepat dan tepat waktu ke rumah atau kantor Anda.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-box p-4 bg-white rounded shadow-sm">
                        <i class="fas fa-medal fa-3x text-primary mb-3"></i>
                        <h3>Kualitas Terjamin</h3>
                        <p>Air kami melewati pengujian kualitas ketat untuk memastikan standar tertinggi.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Featured Products -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Produk Unggulan Kami</h2>
            <div class="row">
                <?php foreach ($featuredProducts as $product): ?>
                <div class="col-md-3 mb-4">
                    <div class="card h-100">
                        <img src="img/products/<?php echo $product['image']; ?>" class="card-img-top" alt="<?php echo $product['name']; ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $product['name']; ?></h5>
                            <p class="card-text"><?php echo substr($product['description'], 0, 70); ?>...</p>
                            <p class="fw-bold"><?php echo formatRupiah($product['price']); ?></p>
                            <a href="order.php?product=<?php echo $product['product_id']; ?>" class="btn btn-primary">Pesan Sekarang</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a href="products.php" class="btn btn-outline-primary">Lihat Semua Produk</a>
            </div>
        </div>
    </section>
    
    <!-- Testimonial Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Apa Kata Pelanggan Kami</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="mb-3 text-warning">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                            <p class="card-text">"Air dari Aquanest sangat segar dan bersih. Pengirimannya juga cepat dan tepat waktu. Sangat merekomendasikan!"</p>
                            <div class="d-flex align-items-center">
                                <div class="ms-3">
                                    <h6 class="mb-0">Budi Santoso</h6>
                                    <small class="text-muted">Pelanggan Tetap</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="mb-3 text-warning">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                            <p class="card-text">"Kualitas airnya bagus dan pelayanannya ramah. Saya sudah berlangganan selama 2 tahun dan tidak pernah kecewa."</p>
                            <div class="d-flex align-items-center">
                                <div class="ms-3">
                                    <h6 class="mb-0">Siti Aminah</h6>
                                    <small class="text-muted">Ibu Rumah Tangga</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="mb-3 text-warning">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i>
                            </div>
                            <p class="card-text">"Proses pemesanan yang mudah dan airnya berkualitas. Stafnya juga ramah dan profesional. Terima kasih Aquanest!"</p>
                            <div class="d-flex align-items-center">
                                <div class="ms-3">
                                    <h6 class="mb-0">Ahmad Hidayat</h6>
                                    <small class="text-muted">Pengusaha</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section class="py-5 bg-primary text-white">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2>Siap Untuk Mencoba Air Berkualitas Kami?</h2>
                    <p class="lead">Pesan sekarang dan rasakan perbedaan kualitas air Aquanest.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="order.php" class="btn btn-light btn-lg">Pesan Sekarang</a>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/navbar.js"></script>
</body>
</html>