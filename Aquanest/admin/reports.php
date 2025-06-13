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
require_once 'includes/report_functions.php';

// Set page title
$page_title = "Laporan Bisnis";

// Check if user is logged in
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    // Redirect ke halaman login jika belum login
    setFlashMessage('warning', 'Silakan login terlebih dahulu.');
    redirect('login.php');
    exit;
}

// Get date range from request or set default (current month)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'sales';

// Get report data based on type
$reportData = null;
switch ($report_type) {
    case 'sales':
        $reportData = getSalesReport($conn, $start_date, $end_date);
        break;
    case 'customers':
        $reportData = getCustomerReport($conn, $start_date, $end_date);
        break;
    case 'delivery':
        $reportData = getDeliveryReport($conn, $start_date, $end_date);
        break;
}

// Include header
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Bisnis - Aquanest</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/dasboard.css">
    <link rel="stylesheet" href="../css/report.css">
</head>
<body>

<!-- Page Header -->
<div class="container-fluid py-4">
    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <h1 class="h3 fw-bold mb-0">
                <i class="fas fa-chart-line text-primary me-2"></i> Laporan Bisnis
            </h1>
            <p class="text-muted mt-2 mb-0">
                Analisis dan insight penting untuk pengambilan keputusan
            </p>
        </div>
        <div class="col-md-6 text-md-end">
            <button class="btn btn-primary print-hide" onclick="window.print()">
                <i class="fas fa-print me-1"></i> Cetak Laporan
            </button>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <!-- Report Controls -->
    <div class="card mb-4 print-hide">
        <div class="card-body">
            <form method="get" action="report.php" class="row g-3">
                <div class="col-md-3">
                    <label for="report_type" class="form-label">Jenis Laporan</label>
                    <div class="input-group">
                        <span class="input-group-text bg-primary text-white"><i class="fas fa-file-alt"></i></span>
                        <select id="report_type" name="report_type" class="form-select" onchange="this.form.submit()">
                            <option value="sales" <?php if ($report_type == 'sales') echo 'selected'; ?>>Laporan Penjualan</option>
                            <option value="customers" <?php if ($report_type == 'customers') echo 'selected'; ?>>Laporan Pelanggan</option>
                            <option value="delivery" <?php if ($report_type == 'delivery') echo 'selected'; ?>>Laporan Pengiriman</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Tanggal Mulai</label>
                    <div class="input-group">
                        <span class="input-group-text bg-primary text-white"><i class="fas fa-calendar-alt"></i></span>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">Tanggal Akhir</label>
                    <div class="input-group">
                        <span class="input-group-text bg-primary text-white"><i class="fas fa-calendar-alt"></i></span>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Terapkan Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Content -->
    <div class="report-content">
        <?php 
        // Include laporan berdasarkan jenisnya
        if ($reportData) {
            switch ($report_type) {
                case 'sales':
                    include 'includes/report_sales.php';
                    break;
                case 'customers':
                    include 'includes/report_customers.php';
                    break;
                case 'delivery':
                    include 'includes/report_delivery.php';
                    break;
            }
        } else {
            // Tampilkan pesan jika tidak ada data
            include 'includes/report_empty.php';
        }
        ?>
    </div>
</div>

<!-- Include footer -->
<?php include 'includes/footer.php'; ?>

<!-- Custom JavaScript -->
<script src="../js/report.js"></script>
<script src="../js/dashboard.js"></script>


</body>
</html>