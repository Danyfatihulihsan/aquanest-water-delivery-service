<?php
session_start();
require_once '../includes/functions.php';

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        switch ($_POST['action']) {
            case 'update_profile':
                if (!empty($_POST['new_password'])) {
                    if (!password_verify($_POST['current_password'], $_SESSION['admin_password'])) {
                        throw new Exception('Current password is incorrect');
                    }
                    
                    $password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                    $sql = "UPDATE admins SET password = ? WHERE admin_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$password_hash, $_SESSION['admin_id']]);
                }
                
                $sql = "UPDATE admins SET name = ?, email = ? WHERE admin_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    sanitize($_POST['name']),
                    sanitize($_POST['email']),
                    $_SESSION['admin_id']
                ]);
                
                $_SESSION['admin_name'] = $_POST['name'];
                setFlashMessage('success', 'Profile updated successfully');
                break;
                
            case 'update_delivery':
                $sql = "UPDATE settings SET value = ? WHERE name = ?";
                $stmt = $conn->prepare($sql);
                
                $settings = [
                    'delivery_fee' => (float)$_POST['delivery_fee'],
                    'min_order_amount' => (float)$_POST['min_order_amount'],
                    'delivery_hours_start' => $_POST['delivery_hours_start'],
                    'delivery_hours_end' => $_POST['delivery_hours_end']
                ];
                
                foreach ($settings as $name => $value) {
                    $stmt->execute([$value, $name]);
                }
                
                setFlashMessage('success', 'Delivery settings updated successfully');
                break;
                
            case 'update_payment':
                $sql = "UPDATE settings SET value = ? WHERE name = ?";
                $stmt = $conn->prepare($sql);
                
                $settings = [
                    'bank_account_name' => $_POST['bank_account_name'],
                    'bank_account_number' => $_POST['bank_account_number'],
                    'bank_name' => $_POST['bank_name'],
                    'qris_merchant_id' => $_POST['qris_merchant_id']
                ];
                
                foreach ($settings as $name => $value) {
                    $stmt->execute([sanitize($value), $name]);
                }
                
                setFlashMessage('success', 'Payment settings updated successfully');
                break;
                
            case 'update_notification':
                $sql = "UPDATE settings SET value = ? WHERE name = ?";
                $stmt = $conn->prepare($sql);
                
                $settings = [
                    'whatsapp_number' => $_POST['whatsapp_number'],
                    'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
                    'whatsapp_notifications' => isset($_POST['whatsapp_notifications']) ? '1' : '0'
                ];
                
                foreach ($settings as $name => $value) {
                    $stmt->execute([sanitize($value), $name]);
                }
                
                setFlashMessage('success', 'Notification settings updated successfully');
                break;
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error in settings.php: " . $e->getMessage());
        setFlashMessage('error', $e->getMessage() ?: 'An error occurred. Please try again.');
    }
    
    // Redirect to prevent form resubmission
    header("Location: settings.php");
    exit();
}

// Get admin profile
$stmt = $conn->prepare("SELECT * FROM admins WHERE admin_id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();

// Get current settings
$stmt = $conn->query("SELECT name, value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['name']] = $row['value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Aquanest Admin</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1">
            <!-- Top Bar -->
            <div class="h-16 bg-white shadow-sm flex items-center justify-between px-6">
                <h2 class="text-lg font-medium text-gray-900">Settings</h2>
                
                <div class="flex items-center">
                    <span class="text-sm text-gray-600">Welcome, <?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                </div>
            </div>

            <!-- Content -->
            <div class="p-6">
                <?php if (isset($_SESSION['flash'])): ?>
                    <div class="mb-4 rounded-md p-4 <?= $_SESSION['flash']['type'] === 'success' ? 'bg-green-50' : 'bg-red-50' ?>">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <?php if ($_SESSION['flash']['type'] === 'success'): ?>
                                    <i class="fas fa-check-circle text-green-400"></i>
                                <?php else: ?>
                                    <i class="fas fa-exclamation-circle text-red-400"></i>
                                <?php endif; ?>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm <?= $_SESSION['flash']['type'] === 'success' ? 'text-green-800' : 'text-red-800' ?>">
                                    <?= $_SESSION['flash']['message'] ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php unset($_SESSION['flash']); ?>
                <?php endif; ?>

                <div class="grid grid-cols-1 gap-6">
                    <!-- Profile Settings -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Profile Settings</h3>
                        </div>
                        
                        <div class="p-6">
                            <form action="settings.php" method="POST" class="space-y-6">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Name</label>
                                        <input type="text" 
                                               name="name" 
                                               value="<?= htmlspecialchars($admin['name']) ?>"
                                               required 
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                      focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Email</label>
                                        <input type="email" 
                                               name="email" 
                                               value="<?= htmlspecialchars($admin['email']) ?>"
                                               required 
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                      focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Current Password</label>
                                        <input type="password" 
                                               name="current_password" 
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                      focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">New Password</label>
                                        <input type="password" 
                                               name="new_password" 
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                      focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                </div>
                                
                                <div class="flex justify-end">
                                    <button type="submit" 
                                            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 
                                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                        Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Delivery Settings -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Delivery Settings</h3>
                        </div>
                        
                        <div class="p-6">
                            <form action="settings.php" method="POST" class="space-y-6">
                                <input type="hidden" name="action" value="update_delivery">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Delivery Fee</label>
                                        <input type="number" 
                                               name="delivery_fee" 
                                               value="<?= $settings['delivery_fee'] ?? '' ?>"
                                               required 
                                               min="0" 
                                               step="0.01"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                      focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Minimum Order Amount</label>
                                        <input type="number" 
                                               name="min_order_amount" 
                                               value="<?= $settings['min_order_amount'] ?? '' ?>"
                                               required 
                                               min="0" 
                                               step="0.01"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                      focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Delivery Hours Start</label>
                                        <input type="time" 
                                               name="delivery_hours_start" 
                                               value="<?= $settings['delivery_hours_start'] ?? '' ?>"
                                               required 
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                      focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Delivery Hours End</label>
                                        <input type="time" 
                                               name="delivery_hours_end" 
                                               value="<?= $settings['delivery_hours_end'] ?? '' ?>"
                                               required 
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                      focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                </div>
                                
                                <div class="flex justify-end">
                                    <button type="submit" 
                                            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 
                                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                        Update Delivery Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Payment Settings -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Payment Settings</h3>
                        </div>
                        
                        <div class="p-6">
                            <form action="settings.php" method="POST" class="space-y-6">
                                <input type="hidden" name="action" value="update_payment">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Bank Name</label>
                                        <input type="text" 
                                               name="bank_name" 
                                               value="<?= htmlspecialchars($settings['bank_name'] ?? '') ?>"
                                               required 
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                      focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Bank Account Name</label>
                                        <input type="text" 
                                               name="bank_account_name" 
                                               value="<?= htmlspecialchars($settings['bank_account_name'] ?? '') ?>"
                                               required 
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                      focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Bank Account Number</label>
                                        <input type="text" 
                                               name="bank_account_number" 
                                               value="<?= htmlspecialchars($settings['bank_account_number'] ?? '') ?>"
                                               required 
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                      focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">QRIS Merchant ID</label>
                                        <input type="text" 
                                               name="qris_merchant_id" 
                                               value="<?= htmlspecialchars($settings['qris_merchant_id'] ?? '') ?>"
                                               required 
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                      focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                </div>
                                
                                <div class="flex justify-end">
                                    <button type="submit" 
                                            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 
                                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                        Update Payment Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Notification Settings -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Notification Settings</h3>
                        </div>
                        
                        <div class="p-6">
                            <form action="settings.php" method="POST" class="space-y-6">
                                <input type="hidden" name="action" value="update_notification">
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">WhatsApp Number</label>
                                        <input type="text" 
                                               name="whatsapp_number" 
                                               value="<?= htmlspecialchars($settings['whatsapp_number'] ?? '') ?>"
                                               required 
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                                      focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    
                                    <div class="flex items-center">
                                        <input type="checkbox" 
                                               name="email_notifications" 
                                               id="email_notifications"
                                               <?= ($settings['email_notifications'] ?? '') === '1' ? 'checked' : '' ?>
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 
                                                      border-gray-300 rounded">
                                        <label for="email_notifications" class="ml-2 block text-sm text-gray-900">
                                            Enable Email Notifications
                                        </label>
                                    </div>
                                    
                                    <div class="flex items-center">
                                        <input type="checkbox" 
                                               name="whatsapp_notifications" 
                                               id="whatsapp_notifications"
                                               <?= ($settings['whatsapp_notifications'] ?? '') === '1' ? 'checked' : '' ?>
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 
                                                      border-gray-300 rounded">
                                        <label for="whatsapp_notifications" class="ml-2 block text-sm text-gray-900">
                                            Enable WhatsApp Notifications
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end">
                                    <button type="submit" 
                                            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 
                                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                        Update Notification Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
