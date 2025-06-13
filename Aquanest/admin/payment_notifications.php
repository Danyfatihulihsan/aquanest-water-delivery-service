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

// FILE: admin/payment_notifications.php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Check session timeout (2 hours)
if (isset($_SESSION['admin_login_time']) && (time() - $_SESSION['admin_login_time']) > 7200) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/notification_functions.php';

// Handle payment approval/rejection
if (isset($_POST['action']) && isset($_POST['notification_id']) && isset($_POST['order_id'])) {
    $notificationId = (int)$_POST['notification_id'];
    $orderId = sanitize($_POST['order_id']);
    $action = $_POST['action'];
    $adminNotes = sanitize($_POST['admin_notes'] ?? '');
    
    if ($action === 'approve') {
        if (approvePayment($conn, $orderId, $notificationId, $adminNotes)) {
            setFlashMessage('success', "Pembayaran pesanan #{$orderId} telah disetujui.");
        } else {
            setFlashMessage('danger', 'Gagal menyetujui pembayaran. Silakan coba lagi.');
        }
    } elseif ($action === 'reject') {
        if (rejectPayment($conn, $orderId, $notificationId, $adminNotes)) {
            setFlashMessage('success', "Pembayaran pesanan #{$orderId} telah ditolak.");
        } else {
            setFlashMessage('danger', 'Gagal menolak pembayaran. Silakan coba lagi.');
        }
    }
    
    header('Location: payment_notifications.php');
    exit;
}

// Get notifications
$pendingNotifications = getPaymentNotifications($conn, 'pending');
$approvedNotifications = getPaymentNotifications($conn, 'approved');
$rejectedNotifications = getPaymentNotifications($conn, 'rejected');

// Get statistics
$stats = getAdminStatistics($conn);
$totalPending = count($pendingNotifications);
$totalProcessedToday = $stats['processed_today'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi Pembayaran - Admin Aquanest</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50">
    <!-- Admin Navbar -->
    <nav class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center mr-3">
                        <i class="fas fa-water text-white"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900">Admin Aquanest</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <?php if ($totalPending > 0): ?>
                        <span class="bg-red-500 text-white text-xs rounded-full h-6 w-6 flex items-center justify-center absolute -top-2 -right-2 animate-pulse">
                            <?php echo $totalPending; ?>
                        </span>
                        <?php endif; ?>
                        <i class="fas fa-bell text-gray-600 text-xl"></i>
                    </div>
                    <span class="text-gray-700">
                        <i class="fas fa-user-shield mr-1"></i>
                        <?php echo $_SESSION['admin_username']; ?>
                    </span>
                    <a href="logout.php" class="text-red-600 hover:text-red-800 transition-colors">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Flash Messages -->
        <?php if(isset($_SESSION['flash'])): ?>
        <div class="mb-6">
            <?php if($_SESSION['flash']['type'] == 'success'): ?>
            <div class="bg-green-50 border-l-4 border-green-500 rounded-lg p-4 flex items-center shadow-md">
                <div class="bg-green-500 rounded-full p-2 mr-4">
                    <i class="fas fa-check text-white"></i>
                </div>
                <div>
                    <h4 class="font-semibold text-green-800">Berhasil!</h4>
                    <p class="text-green-700"><?php echo $_SESSION['flash']['message']; ?></p>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-red-50 border-l-4 border-red-500 rounded-lg p-4 flex items-center shadow-md">
                <div class="bg-red-500 rounded-full p-2 mr-4">
                    <i class="fas fa-times text-white"></i>
                </div>
                <div>
                    <h4 class="font-semibold text-red-800">Error!</h4>
                    <p class="text-red-700"><?php echo $_SESSION['flash']['message']; ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Menunggu Verifikasi</p>
                        <p class="text-3xl font-bold text-red-600"><?php echo $totalPending; ?></p>
                    </div>
                    <div class="p-3 rounded-full bg-red-100">
                        <i class="fas fa-clock text-red-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Diproses Hari Ini</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo $totalProcessedToday; ?></p>
                    </div>
                    <div class="p-3 rounded-full bg-green-100">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Disetujui</p>
                        <p class="text-3xl font-bold text-blue-600"><?php echo count($approvedNotifications); ?></p>
                    </div>
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-thumbs-up text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Ditolak</p>
                        <p class="text-3xl font-bold text-yellow-600"><?php echo count($rejectedNotifications); ?></p>
                    </div>
                    <div class="p-3 rounded-full bg-yellow-100">
                        <i class="fas fa-times-circle text-yellow-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h3 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-bolt mr-2 text-yellow-500"></i>Aksi Cepat
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <button onclick="refreshPage()" class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                    <i class="fas fa-sync-alt text-blue-600 text-xl mr-3"></i>
                    <div class="text-left">
                        <p class="font-semibold text-blue-800">Refresh Notifikasi</p>
                        <p class="text-sm text-blue-600">Update data terbaru</p>
                    </div>
                </button>
                
                <a href="../order.php?tab=track" target="_blank" class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                    <i class="fas fa-external-link-alt text-green-600 text-xl mr-3"></i>
                    <div>
                        <p class="font-semibold text-green-800">Lihat Website</p>
                        <p class="text-sm text-green-600">Buka halaman customer</p>
                    </div>
                </a>
                
                <button onclick="toggleAutoRefresh()" id="autoRefreshBtn" class="flex items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                    <i class="fas fa-play text-purple-600 text-xl mr-3" id="autoRefreshIcon"></i>
                    <div>
                        <p class="font-semibold text-purple-800">Auto Refresh</p>
                        <p class="text-sm text-purple-600" id="autoRefreshText">Mulai auto refresh</p>
                    </div>
                </button>
            </div>
        </div>
        
        <!-- Pending Notifications -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8">
            <div class="bg-gradient-to-r from-red-600 to-red-800 px-6 py-4 text-white">
                <h2 class="text-xl font-bold flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Pembayaran Menunggu Verifikasi (<?php echo $totalPending; ?>)
                </h2>
            </div>
            
            <div class="p-6">
                <?php if (empty($pendingNotifications)): ?>
                <div class="text-center py-12">
                    <div class="w-24 h-24 bg-gray-100 rounded-full mx-auto flex items-center justify-center text-gray-400 mb-4">
                        <i class="fas fa-check-circle text-4xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">Tidak Ada Notifikasi Pending</h3>
                    <p class="text-gray-500">Semua pembayaran telah diverifikasi.</p>
                </div>
                <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($pendingNotifications as $notification): ?>
                    <div class="border border-gray-200 rounded-xl overflow-hidden hover:shadow-lg transition-shadow">
                        <div class="bg-yellow-50 px-6 py-4 border-b border-yellow-100">
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="font-semibold text-gray-800">Pesanan #<?php echo $notification['order_id']; ?></span>
                                    <span class="ml-2 px-3 py-1 bg-yellow-100 text-yellow-800 text-sm rounded-full">
                                        <i class="fas fa-clock mr-1"></i>Menunggu Verifikasi
                                    </span>
                                </div>
                                <span class="text-sm text-gray-500">
                                    <i class="fas fa-calendar mr-1"></i>
                                    <?php echo date('d M Y, H:i', strtotime($notification['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <!-- Customer Info -->
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                                        <i class="fas fa-user mr-2 text-blue-600"></i>
                                        Informasi Pelanggan
                                    </h4>
                                    <div class="space-y-2">
                                        <div class="flex">
                                            <span class="w-20 text-gray-600">Nama:</span>
                                            <span class="font-medium text-gray-800"><?php echo $notification['customer_name']; ?></span>
                                        </div>
                                        <div class="flex">
                                            <span class="w-20 text-gray-600">Telepon:</span>
                                            <span class="font-medium text-gray-800">
                                                <a href="tel:<?php echo $notification['customer_phone']; ?>" class="text-blue-600 hover:text-blue-800">
                                                    <?php echo $notification['customer_phone']; ?>
                                                </a>
                                            </span>
                                        </div>
                                        <div class="flex">
                                            <span class="w-20 text-gray-600">Total:</span>
                                            <span class="font-bold text-blue-700"><?php echo formatRupiah($notification['total_amount']); ?></span>
                                        </div>
                                        <div class="flex">
                                            <span class="w-20 text-gray-600">Metode:</span>
                                            <span class="font-medium text-gray-800">
                                                <i class="fas fa-university mr-1"></i>
                                                <?php echo ucfirst(str_replace('_', ' ', $notification['payment_method'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Payment Proof -->
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                                        <i class="fas fa-file-image mr-2 text-green-600"></i>
                                        Bukti Pembayaran
                                    </h4>
                                    <?php if ($notification['payment_proof']): ?>
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <?php 
                                        $fileExtension = pathinfo($notification['payment_proof'], PATHINFO_EXTENSION);
                                        $filePath = '../uploads/payment_proofs/' . $notification['payment_proof'];
                                        ?>
                                        
                                        <?php if (in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png'])): ?>
                                        <img src="<?php echo $filePath; ?>" alt="Bukti Pembayaran" class="w-full max-w-sm rounded-lg shadow-md mb-3 cursor-pointer" onclick="openImageModal('<?php echo $filePath; ?>')">
                                        <?php else: ?>
                                        <div class="flex items-center p-4 bg-gray-100 rounded-lg mb-3">
                                            <i class="fas fa-file-pdf text-red-600 text-2xl mr-3"></i>
                                            <div>
                                                <p class="font-medium text-gray-800"><?php echo $notification['payment_proof']; ?></p>
                                                <p class="text-sm text-gray-600">File PDF</p>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <a href="<?php echo $filePath; ?>" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors">
                                            <i class="fas fa-external-link-alt mr-2"></i>
                                            Lihat File
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Action Form -->
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <form method="post" action="payment_notifications.php" class="space-y-4">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                    <input type="hidden" name="order_id" value="<?php echo $notification['order_id']; ?>">
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fas fa-comment mr-1"></i>
                                            Catatan Admin (Opsional)
                                        </label>
                                        <textarea name="admin_notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Tambahkan catatan jika diperlukan..."></textarea>
                                    </div>
                                    
                                    <div class="flex gap-3">
                                        <button type="submit" name="action" value="approve" class="flex-1 bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-700 transition-colors flex items-center justify-center">
                                            <i class="fas fa-check mr-2"></i>
                                            Setujui Pembayaran
                                        </button>
                                        <button type="submit" name="action" value="reject" class="flex-1 bg-red-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-red-700 transition-colors flex items-center justify-center" onclick="return confirmReject()">
                                            <i class="fas fa-times mr-2"></i>
                                            Tolak Pembayaran
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="fixed inset-0 bg-black bg-opacity-75 hidden z-50 flex items-center justify-center p-4">
        <div class="relative max-w-4xl max-h-full">
            <button onclick="closeImageModal()" class="absolute top-4 right-4 text-white text-2xl z-10 hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
            <img id="modalImage" src="" alt="Bukti Pembayaran" class="max-w-full max-h-full rounded-lg">
        </div>
    </div>

    <script>
        let autoRefreshInterval;
        let isAutoRefresh = false;
        
        function refreshPage() {
            window.location.reload();
        }
        
        function toggleAutoRefresh() {
            const btn = document.getElementById('autoRefreshBtn');
            const icon = document.getElementById('autoRefreshIcon');
            const text = document.getElementById('autoRefreshText');
            
            if (isAutoRefresh) {
                clearInterval(autoRefreshInterval);
                isAutoRefresh = false;
                icon.classList.remove('fa-pause');
                icon.classList.add('fa-play');
                text.textContent = 'Mulai auto refresh';
                btn.classList.remove('bg-red-50', 'hover:bg-red-100');
                btn.classList.add('bg-purple-50', 'hover:bg-purple-100');
            } else {
                autoRefreshInterval = setInterval(refreshPage, 30000);
                isAutoRefresh = true;
                icon.classList.remove('fa-play');
                icon.classList.add('fa-pause');
                text.textContent = 'Stop auto refresh';
                btn.classList.remove('bg-purple-50', 'hover:bg-purple-100');
                btn.classList.add('bg-red-50', 'hover:bg-red-100');
            }
        }
        
        function confirmReject() {
            return confirm('Yakin ingin menolak pembayaran ini? Pastikan Anda sudah memeriksa bukti pembayaran dengan teliti.');
        }
        
        function openImageModal(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('imageModal').classList.remove('hidden');
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });
        
        // Show notification for new pending payments
        <?php if ($totalPending > 0): ?>
        console.log('<?php echo $totalPending; ?> pembayaran menunggu verifikasi');
        <?php endif; ?>
        
        // Auto-refresh notification (optional)
        // setTimeout(refreshPage, 60000); // Refresh every minute
    </script>
</body>
</html>