<?php
// Start session
session_start();

// Include database connection and functions
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Set page title
$page_title = "Tentang Kami";

// Get testimonials for the about page
try {
    $stmt = $conn->query("SELECT * FROM testimonials WHERE is_active = TRUE ORDER BY RAND() LIMIT 3");
    $testimonials = $stmt->fetchAll();
} catch (PDOException $e) {
    $testimonials = [];
}

// Get team members
$team_members = [
    [
        'name' => 'Dany Fatihul Ihsan',
        'position' => 'Founder & CEO',
        'bio' => 'Dany mendirikan Aquanest pada tahun 2019 dengan visi menyediakan air minum berkualitas untuk keluarga Indonesia.',
        'image' => 'img/admin.jpg'
    ]
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aquanest - <?php echo $page_title; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <!-- <link href="css/index.css" rel="stylesheet"> -->
    <link href="css/about.css" rel="stylesheet">
    <link href="css/navbar.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
</head>
<body>
<!-- Navbar -->
<?php include 'includes/navbar.php'; ?>

<!-- Hero Section -->
<section class="hero-about">
    <div class="floating-bubble"></div>
    <div class="floating-bubble"></div>
    <div class="floating-bubble"></div>
    <div class="floating-bubble"></div>
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6" data-aos="fade-right" data-aos-duration="1000">
                <h1 class="display-4 fw-bold mb-0">Tentang Aquanest</h1>
                <p class="lead mb-4">Kami berkomitmen untuk menyediakan air minum berkualitas terbaik untuk kesehatan keluarga Indonesia.</p>
                <div class="d-flex gap-3">
                    <a href="#story" class="btn btn-light">Cerita Kami</a>
                    <a href="#process" class="btn btn-outline-light">Proses Produksi</a>
                </div>
            </div>
            <div class="col-lg-6 mt-5 mt-lg-0" data-aos="fade-left" data-aos-duration="1000" data-aos-delay="200">
                <img src="img/air-terjun.jpeg" class="img-fluid" alt="Aquanest Facility">
            </div>
        </div>
    </div>
</section>

<!-- Our Story Section -->
<section id="story" class="story-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-5 mb-lg-0" data-aos="fade-right" data-aos-duration="1000">
                <img src="img/border.jpg" class="img-fluid rounded-3 shadow-lg" alt="Aquanest Story">
            </div>
            <div class="col-lg-6" data-aos="fade-left" data-aos-duration="1000" data-aos-delay="200">
                <h5 class="text-primary fw-bold">CERITA KAMI</h5>
                <h2 class="display-6 fw-bold mb-4">Perjalanan Membangun Aquanest</h2>
                <p class="mb-4">Aquanest didirikan pada tahun 2022 oleh Dany Fatihul Ihsan, seorang pengusaha dengan visi menyediakan air minum berkualitas premium untuk masyarakat. Berawal dari keprihatinan akan kualitas air minum yang beredar di pasaran, Budi memulai penelitian mendalam tentang proses filtrasi dan purifikasi air.</p>
                <p class="mb-4">Dengan dukungan tim ahli, Aquanest mengembangkan sistem filtrasi bertingkat yang mampu menghasilkan air minum murni tanpa menghilangkan mineral penting yang dibutuhkan tubuh. Sejak saat itu, kami terus bertumbuh menjadi perusahaan air minum terpercaya di Indonesia.</p>
                <p>Hari ini, Aquanest melayani ribuan keluarga dan bisnis dengan produk air minum berkualitas dan layanan pengiriman yang andal.</p>
                
                <div class="mt-5">
                    <div class="row">
                        <div class="col-6 col-md-4 mb-4 text-center">
                            <h2 class="fw-bold text-primary">2022</h2>
                            <p class="mb-0">Tahun Didirikan</p>
                        </div>
                        <div class="col-6 col-md-4 mb-4 text-center">
                            <h2 class="fw-bold text-primary">10.000+</h2>
                            <p class="mb-0">Pelanggan Setia</p>
                        </div>
                        <div class="col-6 col-md-4 mb-4 text-center">
                            <h2 class="fw-bold text-primary">6</h2>
                            <p class="mb-0">Tahap Pemurnian</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Our Vision & Mission -->
<section class="vision-section">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up" data-aos-duration="1000">
            <h5 class="text-primary fw-bold">VISI & MISI</h5>
            <h2 class="display-6 fw-bold mb-4">Tujuan Kami</h2>
            <p class="lead col-lg-8 mx-auto">Menjadi perusahaan air minum terdepan dengan standar kualitas tertinggi dan kepuasan pelanggan sebagai prioritas utama.</p>
        </div>
        
        <div class="row">
            <div class="col-lg-6 mb-4" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="100">
                <div class="vision-card p-5">
                    <div class="icon-circle mb-4">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h3 class="fw-bold text-center mb-4">Visi Kami</h3>
                    <p class="mb-4">Menjadi perusahaan air minum terdepan yang dikenal karena kualitas, kebersihan, dan komitmen terhadap kesehatan masyarakat Indonesia.</p>
                    <ul class="list-unstyled">
                        <li class="mb-3 d-flex align-items-center">
                            <i class="fas fa-check-circle text-primary me-3"></i>
                            <span>Menyediakan air minum terbaik</span>
                        </li>
                        <li class="mb-3 d-flex align-items-center">
                            <i class="fas fa-check-circle text-primary me-3"></i>
                            <span>Menjangkau seluruh lapisan masyarakat</span>
                        </li>
                        <li class="d-flex align-items-center">
                            <i class="fas fa-check-circle text-primary me-3"></i>
                            <span>Mendukung gaya hidup sehat</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="200">
                <div class="vision-card p-5">
                    <div class="icon-circle mb-4">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <h3 class="fw-bold text-center mb-4">Misi Kami</h3>
                    <p class="mb-4">Menghadirkan produk air minum berkualitas dengan teknologi modern dan layanan pelanggan yang luar biasa.</p>
                    <ul class="list-unstyled">
                        <li class="mb-3 d-flex align-items-center">
                            <i class="fas fa-check-circle text-primary me-3"></i>
                            <span>Menerapkan standar kebersihan tertinggi</span>
                        </li>
                        <li class="mb-3 d-flex align-items-center">
                            <i class="fas fa-check-circle text-primary me-3"></i>
                            <span>Memberikan pelayanan cepat dan ramah</span>
                        </li>
                        <li class="mb-3 d-flex align-items-center">
                            <i class="fas fa-check-circle text-primary me-3"></i>
                            <span>Inovasi berkelanjutan</span>
                        </li>
                        <li class="d-flex align-items-center">
                            <i class="fas fa-check-circle text-primary me-3"></i>
                            <span>Bertanggung jawab terhadap lingkungan</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Our Process -->
<section id="process" class="process-section">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up" data-aos-duration="1000">
            <h5 class="text-primary fw-bold">PROSES KAMI</h5>
            <h2 class="display-6 fw-bold mb-4">Cara Kami Mengolah Air</h2>
            <p class="lead col-lg-8 mx-auto">Setiap tetes air Aquanest melalui 6 tahap pemurnian untuk memastikan kualitas terbaik</p>
        </div>
        
        <div class="row">
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="100">
                <div class="process-card p-4">
                    <div class="process-number">1</div>
                    <div class="p-3">
                        <h4 class="fw-bold mb-3">Seleksi Sumber Air</h4>
                        <p class="mb-0">Kami memilih sumber air terbaik dari pegunungan yang belum tercemar dengan kandungan mineral alami yang baik untuk tubuh.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="200">
                <div class="process-card p-4">
                    <div class="process-number">2</div>
                    <div class="p-3">
                        <h4 class="fw-bold mb-3">Filtrasi Awal</h4>
                        <p class="mb-0">Tahap awal pemurnian untuk menyaring partikel besar dan kotoran menggunakan filter berteknologi tinggi.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="300">
                <div class="process-card p-4">
                    <div class="process-number">3</div>
                    <div class="p-3">
                        <h4 class="fw-bold mb-3">Filtrasi Karbon Aktif</h4>
                        <p class="mb-0">Proses penyerapan bahan kimia, bau, dan rasa menggunakan teknologi karbon aktif berkualitas tinggi.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="400">
                <div class="process-card p-4">
                    <div class="process-number">4</div>
                    <div class="p-3">
                        <h4 class="fw-bold mb-3">Reverse Osmosis</h4>
                        <p class="mb-0">Teknologi pembersihan tingkat molekuler yang menghilangkan kontaminan mikroskopis dan bakteri.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="500">
                <div class="process-card p-4">
                    <div class="process-number">5</div>
                    <div class="p-3">
                        <h4 class="fw-bold mb-3">Sterilisasi UV</h4>
                        <p class="mb-0">Pemurnian dengan sinar UV untuk memastikan air terbebas dari mikroorganisme berbahaya.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="600">
                <div class="process-card p-4">
                    <div class="process-number">6</div>
                    <div class="p-3">
                        <h4 class="fw-bold mb-3">Pengujian Kualitas</h4>
                        <p class="mb-0">Setiap batch produksi diuji secara ketat di laboratorium untuk memastikan standar kualitas tertinggi.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Our Team -->
<section class="team-section">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up" data-aos-duration="1000">
            <h5 class="text-primary fw-bold">TIM KAMI</h5>
            <h2 class="display-6 fw-bold mb-4">Bertemu dengan Orang-orang di Balik Aquanest</h2>
            <p class="lead col-lg-8 mx-auto">Tim ahli yang berdedikasi untuk menghadirkan air minum terbaik untuk Anda</p>
        </div>
        
        <div class="row">
            <?php foreach ($team_members as $index => $member): ?>
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="<?php echo 100 * $index; ?>">
                <div class="team-card">
                    <div class="team-image">
                        <img src="<?php echo $member['image']; ?>" alt="<?php echo $member['name']; ?>">
                    </div>
                    <div class="team-info">
                        <h4 class="fw-bold"><?php echo $member['name']; ?></h4>
                        <p class="text-primary"><?php echo $member['position']; ?></p>
                        <p class="small mb-4"><?php echo $member['bio']; ?></p>
                        <div class="team-social">
                            <a href="#"><i class="fab fa-linkedin-in"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="testimonial-section">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up" data-aos-duration="1000">
            <h5 class="text-primary fw-bold">TESTIMONI</h5>
            <h2 class="display-6 fw-bold mb-4">Apa Kata Pelanggan Kami</h2>
            <p class="lead col-lg-8 mx-auto">Dengarkan langsung dari pelanggan yang telah merasakan manfaat Aquanest</p>
        </div>
        
        <div class="row">
            <?php foreach ($testimonials as $index => $testimonial): ?>
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="<?php echo 100 * $index; ?>">
                <div class="testimonial-card">
                    <div class="testimonial-img">
                        <?php if (!empty($testimonial['image'])): ?>
                            <img src="img/testimonials/<?php echo $testimonial['image']; ?>" alt="<?php echo $testimonial['name']; ?>">
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center bg-primary h-100 w-100">
                                <i class="fas fa-user text-white"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="testimonial-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= $testimonial['rating']): ?>
                                <i class="fas fa-star"></i>
                            <?php elseif ($i - 0.5 <= $testimonial['rating']): ?>
                                <i class="fas fa-star-half-alt"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <div class="testimonial-content">
                        <p>"<?php echo $testimonial['content']; ?>"</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="testimonial-author-info">
                            <h5 class="fw-bold mb-0"><?php echo $testimonial['name']; ?></h5>
                            <p class="text-muted mb-0"><?php echo $testimonial['position']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="faq-section">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up" data-aos-duration="1000">
            <h5 class="text-primary fw-bold">FAQ</h5>
            <h2 class="display-6 fw-bold mb-4">Pertanyaan Umum</h2>
            <p class="lead col-lg-8 mx-auto">Temukan jawaban untuk pertanyaan yang sering diajukan tentang Aquanest</p>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-lg-10" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="100">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingOne">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                Apa yang membuat Aquanest berbeda dari produk air minum lainnya?
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                <p>Aquanest menggunakan teknologi filtrasi bertingkat dengan 6 tahapan pemurnian untuk memastikan kualitas air terbaik. Kami juga menyeimbangkan kandungan mineral dalam air untuk mendukung kesehatan. Selain itu, layanan pengiriman kami cepat dan tepat waktu, dengan opsi berlangganan yang fleksibel sesuai kebutuhan Anda.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingTwo">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                Bagaimana cara memesan air Aquanest?
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                <p>Anda dapat memesan air Aquanest melalui beberapa cara:</p>
                                <ul>
                                    <li>Melalui website kami dengan mengklik tombol "Pesan Sekarang"</li>
                                    <li>Menghubungi customer service kami di (0818) 0607-9131</li>
                                    <li>Melalui Website yang bisa di akses Melalui Google</li>
                                    <li>Mengirim pesan WhatsApp ke 0895-3000-7056</li>
                                </ul>
                                <p>Kami akan mengantarkan pesanan Anda dalam waktu 24 jam untuk area Jakarta dan sekitarnya.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingThree">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                Berapa lama proses pengiriman Aquanest?
                            </button>
                        </h2>
                        <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                <p>Waktu pengiriman bervariasi tergantung lokasi Anda:</p>
                                <ul>
                                    <li>Cibitung dan sekitarnya: Pengiriman dalam 12 jam</li>
                                    <li>Kota-kota besar di Pulau Jawa: 1-2 hari kerja</li>
                                    <li>Kota lainnya di Indonesia: 2-3 hari kerja</li>
                                </ul>
                                <p>Untuk pelanggan berlangganan, kami menjadwalkan pengiriman rutin sesuai dengan pilihan Anda (mingguan, dua mingguan, atau bulanan) sehingga Anda tidak perlu khawatir kehabisan stok air minum.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="cta-section">
    <div class="floating-bubble"></div>
    <div class="floating-bubble"></div>
    <div class="floating-bubble"></div>
    <div class="floating-bubble"></div>
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8 mb-4 mb-lg-0" data-aos="fade-right" data-aos-duration="1000">
                <h2 class="display-6 fw-bold mb-3">Siap untuk mencoba air berkualitas Aquanest?</h2>
                <p class="lead mb-0">Pesan sekarang dan rasakan perbedaannya, atau hubungi kami untuk informasi lebih lanjut.</p>
            </div>
            <div class="col-lg-4 text-lg-end" data-aos="fade-left" data-aos-duration="1000" data-aos-delay="200">
                <a href="order.php" class="btn btn-light btn-lg me-2 mb-2 mb-md-0">Pesan Sekarang</a>
                <a href="contact.php" class="btn btn-outline-light btn-lg">Hubungi Kami</a>
            </div>
        </div>
    </div>
</section>

<!-- Include footer -->
<?php include 'includes/footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<!-- AOS Animation JS -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({
        once: true
    });
</script>
 <script src="js/navbar.js"></script>
</body>
</html>