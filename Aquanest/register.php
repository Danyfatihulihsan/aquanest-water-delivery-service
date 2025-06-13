<?php
// Start session
session_start();

// Include database connection
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    redirect('index.php');
}

// Define variables
$username = $password = $name = $confirm_password = $email = "";
$username_err = $password_err = $name_err = $confirm_password_err = $email_err = "";

// Process registration form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    // Validate name
    if (empty(trim($_POST["name"]))) {
        $name_err = "Silakan masukkan nama lengkap.";
    } else {
        $name = trim($_POST["name"]);
    }
    
    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Silakan masukkan username.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', trim($_POST["username"]))) {
        $username_err = "Username hanya boleh berisi huruf, angka, dan underscore.";
    } else {
        // Check if username already exists
        $sql = "SELECT user_id FROM users WHERE username = :username";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables
            $param_username = trim($_POST["username"]);
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
            
            // Execute the statement
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    $username_err = "Username sudah digunakan.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                echo "Terjadi kesalahan. Silakan coba lagi.";
            }
            
            // Close statement
            unset($stmt);
        }
    }
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Silakan masukkan email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Format email tidak valid.";
    } else {
        // Check if email already exists
        $sql = "SELECT user_id FROM users WHERE email = :email";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables
            $param_email = trim($_POST["email"]);
            $stmt->bindParam(":email", $param_email, PDO::PARAM_STR);
            
            // Execute the statement
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    $email_err = "Email sudah digunakan.";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                echo "Terjadi kesalahan. Silakan coba lagi.";
            }
            
            // Close statement
            unset($stmt);
        }
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Silakan masukkan password.";     
    } elseif (!preg_match('/[0-9]/', trim($_POST["password"]))) {
        $password_err = "Password harus mengandung setidaknya satu angka.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Silakan konfirmasi password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password tidak cocok.";
        }
    }
    
    // Check input errors before inserting into database
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err) && empty($name_err) && empty($email_err)) {
        // Prepare an insert statement
        // PERUBAHAN DISINI: Menghapus kolom 'role' dari query INSERT
        $sql = "INSERT INTO users (username, password, name, email) VALUES (:username, :password, :name, :email)";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables
            $param_username = $username;
            $param_password = password_hash($password, PASSWORD_DEFAULT);
            $param_name = $name;
            $param_email = $email;
            
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
            $stmt->bindParam(":password", $param_password, PDO::PARAM_STR);
            $stmt->bindParam(":name", $param_name, PDO::PARAM_STR);
            $stmt->bindParam(":email", $param_email, PDO::PARAM_STR);
            
            // Execute the statement
            if ($stmt->execute()) {
                // Registration successful, redirect to login
                header("location: login.php?registration_success=1");
                exit();
            } else {
                echo "Terjadi kesalahan. Silakan coba lagi.";
            }
            
            // Close statement
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
    <link rel="stylesheet" href="css/loginregister.css" />
    <title>Aquanest | Registrasi</title>
</head>
<body>
    <div class="container right-panel-active" id="container">
        <div class="form-container sign-up">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <h1>Registrasi</h1>
                
                <?php if (!empty($username_err) || !empty($name_err) || !empty($password_err) || !empty($confirm_password_err) || !empty($email_err)): ?>
                    <div class="alert alert-danger">
                        <?php 
                            if (!empty($name_err)) echo $name_err . '<br>';
                            if (!empty($username_err)) echo $username_err . '<br>';
                            if (!empty($email_err)) echo $email_err . '<br>';
                            if (!empty($password_err)) echo $password_err . '<br>';
                            if (!empty($confirm_password_err)) echo $confirm_password_err;
                        ?>
                    </div>
                <?php endif; ?>
                
                <div class="social-icons">
                    <a href="https://www.instagram.com/" class="icon"><i class="fa-brands fa-instagram"></i></a>
                    <a href="https://web.facebook.com/" class="icon"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="https://www.youtube.com/" class="icon"><i class="fa-brands fa-youtube"></i></a>
                </div>
                <span>atau daftar dengan email</span>
                <input type="text" name="name" placeholder="Nama Lengkap" value="<?php echo $name; ?>" required />
                <input type="text" name="username" placeholder="Username" value="<?php echo $username; ?>" required />
                <input type="email" name="email" placeholder="Email" value="<?php echo $email; ?>" required />
                <input type="password" name="password" placeholder="Password" required />
                <input type="password" name="confirm_password" placeholder="Konfirmasi Password" required />
                <button type="submit" name="register">Daftar</button>
            </form>
        </div>
        <div class="form-container sign-in">
            <!-- Sign In Form (we leave it here for animation but it redirects to login.php) -->
            <form action="login.php" method="post">
                <h1>Login</h1>
                <div class="social-icons">
                    <a href="https://www.instagram.com/" class="icon"><i class="fa-brands fa-instagram"></i></a>
                    <a href="https://web.facebook.com/" class="icon"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="https://www.youtube.com/" class="icon"><i class="fa-brands fa-youtube"></i></a>
                </div>
                <span>atau login dengan email dan password</span>
                <input type="text" name="username" placeholder="Username" required />
                <input type="password" name="password" placeholder="Password" required />
                <a href="forgot-password.php">Lupa Password?</a>
                <button type="submit" name="login">Login</button>
            </form>
        </div>
        <div class="toggle-container">
            <div class="toggle">
                <div class="toggle-panel toggle-left">
                    <h1>Selamat Datang Kembali!</h1>
                    <p>Silahkan Login Untuk Mengakses Air Biru</p>
                    <button class="hidden" id="login">Sign In</button>
                </div>
                <div class="toggle-panel toggle-right">
                    <h1>Selamat Datang!</h1>
                    <p>Silahkan Mengisi Data Untuk Mengakses Air Biru</p>
                    <button class="hidden" id="register">Sign Up</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="js/loginregister.js"></script>
</body>
</html>