<?php
// Start session
session_start();

// Include database connection
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Define variables
$new_password = $confirm_password = "";
$new_password_err = $confirm_password_err = $token_err = $success_msg = "";
$token = "";

// Check for token
if (empty($_GET["token"])) {
    $token_err = "Token tidak valid.";
} else {
    $token = trim($_GET["token"]);
    
    // Verify token
    $sql = "SELECT user_id, token, expiry FROM password_reset WHERE token = :token";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bindParam(":token", $token, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() == 1) {
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $user_id = $row["user_id"];
                    $expiry = $row["expiry"];
                    
                    // Check if token has expired
                    if (strtotime($expiry) < time()) {
                        $token_err = "Token telah kedaluwarsa. Silakan minta reset password baru.";
                    }
                }
            } else {
                $token_err = "Token tidak valid.";
            }
        } else {
            $token_err = "Terjadi kesalahan. Silakan coba lagi nanti.";
        }
        
        unset($stmt);
    }
}

// Process form
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($token_err)) {
    
    // Validate new password
    if (empty(trim($_POST["new_password"]))) {
        $new_password_err = "Silakan masukkan password baru.";
    } elseif (strlen(trim($_POST["new_password"])) < 6) {
        $new_password_err = "Password harus memiliki minimal 6 karakter.";
    } else {
        $new_password = trim($_POST["new_password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Silakan konfirmasi password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($new_password_err) && ($new_password != $confirm_password)) {
            $confirm_password_err = "Password tidak cocok.";
        }
    }
    
    // Check input errors before updating
    if (empty($new_password_err) && empty($confirm_password_err) && empty($token_err)) {
        // Get user_id from token
        $sql = "SELECT user_id FROM password_reset WHERE token = :token";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bindParam(":token", $token, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() == 1) {
                    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $user_id = $row["user_id"];
                        
                        // Update password
                        $update_sql = "UPDATE users SET password = :password WHERE user_id = :user_id";
                        
                        if ($update_stmt = $conn->prepare($update_sql)) {
                            $param_password = password_hash($new_password, PASSWORD_DEFAULT);
                            
                            $update_stmt->bindParam(":password", $param_password, PDO::PARAM_STR);
                            $update_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                            
                            if ($update_stmt->execute()) {
                                // Delete token
                                $delete_sql = "DELETE FROM password_reset WHERE user_id = :user_id";
                                $delete_stmt = $conn->prepare($delete_sql);
                                $delete_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                                $delete_stmt->execute();
                                
                                $success_msg = "Password berhasil diubah.";
                            } else {
                                $token_err = "Terjadi kesalahan. Silakan coba lagi nanti.";
                            }
                        }
                    }
                }
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
    <title>Aquanest | Reset Password</title>
    <style>
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
            margin-bottom: 12px;
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
            text-decoration: none;
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
        
        .field-error {
            color: #D32F2F;
            font-size: 12px;
            margin-top: -8px;
            margin-bottom: 10px;
            margin-left: 5px;
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
            <h2>Reset Password</h2>
            <p>Buat password baru untuk akun Anda</p>
        </div>
        
        <div class="card-body">
            <?php if (!empty($token_err)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo $token_err; ?></div>
                </div>
                <a href="forgot-password.php" class="btn">
                    <i class="fas fa-paper-plane"></i> Minta Reset Password Baru
                </a>
            <?php elseif (!empty($success_msg)): ?>
                <div class="success-animation">
                    <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                        <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                        <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                    </svg>
                </div>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div>Password berhasil diubah.</div>
                </div>
                <a href="login.php" class="btn">
                    <i class="fas fa-sign-in-alt"></i> Masuk Sekarang
                </a>
            <?php else: ?>
                <p style="margin-bottom: 20px; color: #666; font-size: 14px;">
                    Silakan buat password baru untuk akun Anda.
                </p>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?token=" . $token); ?>" method="post">
                    <div class="input-group">
                        <div class="input-field">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="new_password" placeholder="Password Baru" required />
                        </div>
                        <?php if (!empty($new_password_err)): ?>
                            <div class="field-error">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $new_password_err; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="input-group">
                        <div class="input-field">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="confirm_password" placeholder="Konfirmasi Password" required />
                        </div>
                        <?php if (!empty($confirm_password_err)): ?>
                            <div class="field-error">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $confirm_password_err; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-key"></i> Reset Password
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