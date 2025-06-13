<?php
// Start session
session_start();

// Include database connection
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Load PHPMailer dengan cara manual (tanpa Composer)
require_once 'vendor/phpmailer/src/Exception.php';
require_once 'vendor/phpmailer/src/PHPMailer.php';
require_once 'vendor/phpmailer/src/SMTP.php';

// Import class yang diperlukan
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Load mail configuration
$mail_config = require_once 'includes/mail-config.php';

// Define variables
$email = "";
$email_err = $success_msg = "";

// Process form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Silakan masukkan email.";
    } else {
        $email = trim($_POST["email"]);
        
        // Check if email exists
        $sql = "SELECT user_id, username, email, name FROM users WHERE email = :email";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bindParam(":email", $email, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() == 1) {
                    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        // Generate token
                        $token = bin2hex(random_bytes(32));
                        $user_id = $row["user_id"];
                        $username = $row["username"];
                        $name = $row["name"] ?? $username; // Use name if available, otherwise username
                        
                        // Delete any existing tokens for this user
                        $delete_sql = "DELETE FROM password_reset WHERE user_id = :user_id";
                        $delete_stmt = $conn->prepare($delete_sql);
                        $delete_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                        $delete_stmt->execute();
                        
                        // Set expiry time to 1 hour from now
                        $expiry = date('Y-m-d H:i:s', time() + 3600);
                        
                        // Insert token into database
                        $insert_sql = "INSERT INTO password_reset (user_id, token, expiry) VALUES (:user_id, :token, :expiry)";
                        if ($insert_stmt = $conn->prepare($insert_sql)) {
                            $insert_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                            $insert_stmt->bindParam(":token", $token, PDO::PARAM_STR);
                            $insert_stmt->bindParam(":expiry", $expiry, PDO::PARAM_STR);
                            
                            if ($insert_stmt->execute()) {
                                // Periksa apakah server menggunakan HTTPS
                                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
                                
                                // Create reset link dengan protokol yang sesuai
                                $reset_link = $protocol . $_SERVER['HTTP_HOST'] . "/aquanest/reset-password.php?token=" . $token;
                                
                                // Send email via PHPMailer
                                $mail = new PHPMailer(true);
                                
                                try {
                                    // Server settings
                                    $mail->SMTPDebug = 0; // 0 = off (production), 1 = client messages, 2 = client and server messages
                                    $mail->isSMTP();
                                    $mail->Host       = $mail_config['host'];
                                    $mail->SMTPAuth   = true;
                                    $mail->Username   = $mail_config['username'];
                                    $mail->Password   = $mail_config['password'];
                                    $mail->SMTPSecure = $mail_config['encryption'];
                                    $mail->Port       = $mail_config['port'];
                                    $mail->CharSet    = 'UTF-8';
                                    
                                    // Recipients
                                    $mail->setFrom($mail_config['from_email'], $mail_config['from_name']);
                                    $mail->addAddress($email, $name);
                                    
                                    // Content
                                    $mail->isHTML(true);
                                    $mail->Subject = 'Reset Password Akun Aquanest';
                                    
                                    // Email template with modern design
                                    $mail_body = '
                                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                                        <div style="background-color: #1976D2; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0;">
                                            <h1 style="margin: 0; font-size: 24px;">Reset Password Aquanest</h1>
                                        </div>
                                        <div style="background-color: #ffffff; padding: 30px; border-left: 1px solid #ddd; border-right: 1px solid #ddd;">
                                            <p style="margin-top: 0; font-size: 16px;">Halo <strong>' . htmlspecialchars($name) . '</strong>,</p>
                                            
                                            <p style="font-size: 16px;">Kami menerima permintaan untuk reset password akun Aquanest Anda. Klik tombol di bawah ini untuk melanjutkan:</p>
                                            
                                            <div style="text-align: center; margin: 30px 0;">
                                                <a href="' . $reset_link . '" style="background-color: #1976D2; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; font-size: 16px;">Reset Password</a>
                                            </div>
                                            
                                            <p style="font-size: 14px; color: #666;">Jika Anda tidak meminta reset password, abaikan email ini atau hubungi tim dukungan kami.</p>
                                            
                                            <p style="font-size: 14px; color: #666;">Link reset password ini akan kedaluwarsa dalam 1 jam.</p>
                                            
                                            <p style="font-size: 14px; color: #666;">Jika tombol di atas tidak berfungsi, copy dan paste URL berikut ke browser Anda:</p>
                                            
                                            <p style="background-color: #f5f5f5; padding: 10px; border-radius: 4px; font-size: 12px; word-break: break-all;">' . $reset_link . '</p>
                                        </div>
                                        <div style="background-color: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 10px 10px; border-left: 1px solid #ddd; border-right: 1px solid #ddd; border-bottom: 1px solid #ddd;">
                                            <p style="margin: 0;">© ' . date('Y') . ' Aquanest. Semua hak dilindungi.</p>
                                        </div>
                                    </div>
                                    ';
                                    
                                    $mail->Body = $mail_body;
                                    $mail->AltBody = 'Halo ' . $name . ', Silakan reset password Aquanest Anda dengan mengunjungi: ' . $reset_link;
                                    
                                    $mail->send();
                                    $success_msg = "Link untuk reset password telah dikirim ke email Anda.";
                                } catch (Exception $e) {
                                    // Log error untuk debugging
                                    error_log("Email tidak dapat dikirim. Error: {$mail->ErrorInfo}");
                                    $email_err = "Terjadi kesalahan saat mengirim email. Silakan coba lagi nanti.";
                                }
                            } else {
                                $email_err = "Terjadi kesalahan. Silakan coba lagi nanti.";
                            }
                        }
                    }
                } else {
                    $email_err = "Tidak ada akun dengan email tersebut.";
                }
            } else {
                $email_err = "Terjadi kesalahan. Silakan coba lagi nanti.";
            }
            
            unset($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
    <title>Aquanest | Lupa Password</title>
    <style>
        /* CSS sama seperti sebelumnya */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f0f2f5;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .card {
            background-color: white;
            width: 400px;
            max-width: 100%;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
        }
        
        .card-header {
            background-color: #1976D2;
            color: white;
            padding: 25px 30px;
            position: relative;
            overflow: hidden;
        }
        
        .card-header .shapes {
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        
        .card-header .shape {
            position: absolute;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .card-header .shape-1 {
            width: 120px;
            height: 120px;
            top: -30px;
            right: -30px;
        }
        
        .card-header .shape-2 {
            width: 80px;
            height: 80px;
            bottom: -20px;
            right: 50px;
        }
        
        .card-header .shape-3 {
            width: 50px;
            height: 50px;
            bottom: 30px;
            right: 20px;
        }
        
        .card-header h2 {
            font-size: 24px;
            margin-bottom: 5px;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        
        .card-header p {
            font-size: 15px;
            opacity: 0.9;
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #666;
        }
        
        .input-field {
            position: relative;
        }
        
        .input-field i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #1976D2;
        }
        
        .input-field input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
            background-color: #f9f9f9;
        }
        
        .input-field input:focus {
            border-color: #1976D2;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.2);
            outline: none;
            background-color: white;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background-color: #1976D2;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn:hover {
            background-color: #1565C0;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(25, 118, 210, 0.3);
        }
        
        .btn i {
            font-size: 18px;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .back-link:hover {
            color: #1976D2;
        }
        
        .back-link i {
            margin-right: 5px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .alert-danger {
            background-color: #FFEBEE;
            color: #D32F2F;
            border-left: 4px solid #D32F2F;
        }
        
        .alert-success {
            background-color: #E8F5E9;
            color: #2E7D32;
            border-left: 4px solid #2E7D32;
        }
        
        .alert i {
            margin-top: 2px;
        }
        
        .success-animation {
            text-align: center;
            margin: 20px 0;
        }
        
        .checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: block;
            stroke-width: 2;
            stroke: #2E7D32;
            stroke-miterlimit: 10;
            margin: 0 auto;
            box-shadow: inset 0px 0px 0px #2E7D32;
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
        }
        
        .checkmark-circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 2;
            stroke-miterlimit: 10;
            stroke: #2E7D32;
            fill: none;
            animation: stroke .6s cubic-bezier(0.650, 0.000, 0.450, 1.000) forwards;
        }
        
        .checkmark-check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke .3s cubic-bezier(0.650, 0.000, 0.450, 1.000) .8s forwards;
        }
        
        @keyframes stroke {
            100% {
                stroke-dashoffset: 0;
            }
        }
        
        @keyframes scale {
            0%, 100% {
                transform: none;
            }
            50% {
                transform: scale3d(1.1, 1.1, 1);
            }
        }
        
        @keyframes fill {
            100% {
                box-shadow: inset 0px 0px 0px 30px #E8F5E9;
            }
        }
        
        @media (max-width: 480px) {
            .card {
                width: 100%;
            }
            
            .card-header {
                padding: 20px;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .input-field input {
                padding: 12px 12px 12px 40px;
                font-size: 15px;
            }
            
            .btn {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <div class="shapes">
                <div class="shape shape-1"></div>
                <div class="shape shape-2"></div>
                <div class="shape shape-3"></div>
            </div>
            <h2>Lupa Password</h2>
            <p>Masukkan email untuk reset password Anda</p>
        </div>
        
        <div class="card-body">
            <?php if (!empty($email_err)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo $email_err; ?></div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_msg)): ?>
                <div class="success-animation">
                    <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                        <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                        <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                    </svg>
                </div>
                <div class="alert alert-success">
                    <i class="fas fa-envelope"></i>
                    <div>Link untuk reset password telah dikirim ke email Anda.</div>
                </div>
                <a href="login.php" class="btn">
                    <i class="fas fa-sign-in-alt"></i> Kembali ke Login
                </a>
            <?php else: ?>
                <p style="margin-bottom: 20px; color: #666; font-size: 14px;">
                    Masukkan alamat email yang terhubung dengan akun Anda. Kami akan mengirimkan link untuk reset password ke email tersebut.
                </p>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="input-group">
                        <div class="input-field">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" placeholder="Email" value="<?php echo $email; ?>" required autocomplete="email" />
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-paper-plane"></i> Kirim Link Reset
                    </button>
                </form>
                
                <a href="login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Kembali ke halaman login
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>