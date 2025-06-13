<?php
// Start session
session_start();

// Include database connection and functions
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Set page title
$page_title = "Hubungi Kami";

// Process contact form
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    // Get form data
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);
    
    // Validate form data
    if (empty($name) || empty($email) || empty($message)) {
        $error_message = 'Mohon lengkapi semua bidang yang wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Mohon masukkan alamat email yang valid.';
    } else {
        // Save to database
        try {
            $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, phone, subject, message, created_at) 
                                  VALUES (:name, :email, :phone, :subject, :message, NOW())");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':message', $message);
            $stmt->execute();
            
            // Send notification email to admin (optional)
            // mail('admin@aquanest.com', 'Pesan Kontak Baru: ' . $subject, $message);
            
            $success_message = 'Terima kasih! Pesan Anda telah dikirim. Tim kami akan menghubungi Anda segera.';
            
            // Clear form data
            $name = $email = $phone = $subject = $message = '';
            
        } catch (PDOException $e) {
            $error_message = 'Terjadi kesalahan saat mengirim pesan. Silakan coba lagi.';
        }
    }
}

// Get company information
$company_info = [
    'address' => 'Jl. Griya Family 4 No.1, Sarimukti, Kec. Cibitung, Kabupaten Bekasi, Jawa Barat 17520',
    'phone' => '(0818) 0607-9131',
    'mobile' => '0895-3000-7056',
    'email' => 'info@aquanest.com',
    'hours' => 'Senin - Jumat: 08.00 - 17.00 WIB<br>Sabtu: 08.00 - 15.00 WIB',
    'maps_embed' => 'https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d247.9056548698826!2d107.08525026571073!3d-6.1988365942669725!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e0!3m2!1sid!2sid!4v1746869609740!5m2!1sid!2sid'
];

// Include header
include 'includes/navbar.php';
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
    <link href="css/navbar.css" rel="stylesheet">
    <link href="css/contact.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
</head>
<body>

<!-- Hero Section -->
<section class="contact-hero">
    <div class="floating-bubble"></div>
    <div class="floating-bubble"></div>
    <div class="floating-bubble"></div>
    <div class="floating-bubble"></div>
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6" data-aos="fade-right" data-aos-duration="1000">
                <h1 class="display-4 fw-bold mb-4">Hubungi Kami</h1>
                <p class="lead mb-4">Kami siap melayani pertanyaan, saran, atau kebutuhan Anda terkait produk air minum Aquanest.</p>
                <div class="d-flex gap-3">
                    <a href="#contact-form" class="btn btn-light">Kirim Pesan</a>
                    <a href="order.php" class="btn btn-outline-light">Pesan Sekarang</a>
                </div>
            </div>
            <div class="col-lg-6 mt-5 mt-lg-0" data-aos="fade-left" data-aos-duration="1000" data-aos-delay="200">
                <img src="img/aquanest-customerService.jpg" class="img-fluid rounded-3 shadow-lg" alt="Aquanest Customer Service">
            </div>
        </div>
    </div>
</section>

<!-- Contact Info Cards -->
<section class="info-cards-section">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="100">
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3>Alamat Kami</h3>
                    <p><?php echo $company_info['address']; ?></p>
                    <a href="https://goo.gl/maps/YourGoogleMapsLink" target="_blank" class="info-link">Lihat di Maps <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
            
            <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="200">
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-phone-alt"></i>
                    </div>
                    <h3>Telepon Kami</h3>
                    <p>Telepon: <?php echo $company_info['phone']; ?><br>
                    WhatsApp: <?php echo $company_info['mobile']; ?></p>
                    <a href="https://wa.me/<?php echo str_replace(['-', ' ', '(', ')', '+'], '', $company_info['mobile']); ?>" target="_blank" class="info-link">Chat WhatsApp <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
            
            <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="300">
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h3>Email Kami</h3>
                    <p><?php echo $company_info['email']; ?></p>
                    <p>Jam operasional:<br><?php echo $company_info['hours']; ?></p>
                    <a href="mailto:<?php echo $company_info['email']; ?>" class="info-link">Kirim Email <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Form Section -->
<section id="contact-form" class="contact-form-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-5 mb-lg-0" data-aos="fade-right" data-aos-duration="1000">
                <div class="contact-form-wrap">
                    <div class="section-heading mb-4">
                        <h5 class="text-primary fw-bold">KIRIM PESAN</h5>
                        <h2 class="display-6 fw-bold">Ada Pertanyaan Untuk Kami?</h2>
                        <p>Isi form di bawah dan tim kami akan segera menghubungi Anda.</p>
                    </div>
                    
                    <?php if(!empty($success_message)): ?>
                        <div class="alert alert-success mb-4" role="alert">
                            <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($error_message)): ?>
                        <div class="alert alert-danger mb-4" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="contact.php#contact-form">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($name) ? $name : ''; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? $email : ''; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Nomor Telepon</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo isset($phone) ? $phone : ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="subject" class="form-label">Subjek</label>
                                <select class="form-select" id="subject" name="subject">
                                    <option value="Pertanyaan Umum" <?php echo (isset($subject) && $subject == 'Pertanyaan Umum') ? 'selected' : ''; ?>>Pertanyaan Umum</option>
                                    <option value="Pemesanan" <?php echo (isset($subject) && $subject == 'Pemesanan') ? 'selected' : ''; ?>>Pemesanan</option>
                                    <option value="Keluhan" <?php echo (isset($subject) && $subject == 'Keluhan') ? 'selected' : ''; ?>>Keluhan</option>
                                    <option value="Saran" <?php echo (isset($subject) && $subject == 'Saran') ? 'selected' : ''; ?>>Saran</option>
                                    <option value="Lainnya" <?php echo (isset($subject) && $subject == 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="message" class="form-label">Pesan <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="message" name="message" rows="5" required><?php echo isset($message) ? $message : ''; ?></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="submit_contact" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i> Kirim Pesan
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-6" data-aos="fade-left" data-aos-duration="1000" data-aos-delay="200">
                <div class="map-wrapper">
                    <div class="ratio ratio-4x3">
                        <iframe src="<?php echo $company_info['maps_embed']; ?>" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                    <div class="map-info-box">
                        <h4>Kantor Pusat Aquanest</h4>
                        <p><i class="fas fa-map-marker-alt me-2"></i> <?php echo $company_info['address']; ?></p>
                        <p><i class="fas fa-phone-alt me-2"></i> <?php echo $company_info['phone']; ?></p>
                        <p><i class="fas fa-clock me-2"></i> <?php echo $company_info['hours']; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="faq-section">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up" data-aos-duration="1000">
            <h5 class="text-primary fw-bold">FAQ</h5>
            <h2 class="display-6 fw-bold mb-4">Pertanyaan Umum</h2>
            <p class="lead col-lg-8 mx-auto">Pertanyaan yang sering ditanyakan oleh pelanggan kami</p>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-lg-10" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="100">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingOne">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                Berapa lama waktu respons untuk pesan yang saya kirimkan?
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                <p>Tim customer service kami berusaha merespons semua pesan dalam waktu 24 jam pada hari kerja. Untuk pertanyaan atau keluhan mendesak, Anda dapat menghubungi kami langsung melalui telepon atau WhatsApp untuk respons yang lebih cepat.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingTwo">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                Bagaimana cara melaporkan masalah dengan produk Aquanest?
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                <p>Anda dapat melaporkan masalah dengan produk melalui beberapa cara:</p>
                                <ul>
                                    <li>Mengisi formulir kontak di halaman ini dengan memilih subjek "Keluhan"</li>
                                    <li>Menghubungi customer service kami di <?php echo $company_info['phone']; ?></li>
                                    <li>Mengirim pesan WhatsApp ke <?php echo $company_info['mobile']; ?></li>
                                    <li>Mengirim email ke <?php echo $company_info['email']; ?></li>
                                </ul>
                                <p>Mohon sertakan nomor pesanan, tanggal pengiriman, dan detail masalah untuk membantu kami menangani laporan Anda dengan lebih efisien.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingThree">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                Apakah ada outlet fisik Aquanest yang bisa saya kunjungi?
                            </button>
                        </h2>
                        <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                <p>Ya, kami memiliki beberapa outlet fisik yang bisa Anda kunjungi:</p>
                                <ul>
                                    <li>Outlet Pusat: <?php echo $company_info['address']; ?></li>
                                    <li>Outlet Bekasi Timur: Jl. Raya Jatimakmur No. 45, Bekasi Timur</li>
                                    <li>Outlet Bekasi Selatan: Jl. Kemakmuran No. 72, Bekasi Selatan</li>
                                </ul>
                                <p>Jam operasional outlet sama dengan jam kantor kami, yaitu <?php echo str_replace('<br>', ', ', $company_info['hours']); ?>. Untuk informasi lebih lanjut, silakan hubungi customer service kami.</p>
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
                <a href="#contact-form" class="btn btn-outline-light btn-lg">Hubungi Kami</a>
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