<?php
// Start session
session_start();

// Include database connection
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if already logged in
if (isset($_SESSION['admin_id'])) {
    redirect('dashboard.php');
}

// Define variables
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    
    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Silakan masukkan username.";
    } else {
        $username = trim($_POST["username"]);
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Silakan masukkan password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if (empty($username_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT admin_id, username, password, name, email FROM admin WHERE username = :username";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables
            $param_username = $username;
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
            
            // Execute the statement
            if ($stmt->execute()) {
                // Check if username exists
                if ($stmt->rowCount() == 1) {
                    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $id = $row["admin_id"];
                        $username = $row["username"];
                        $db_password = $row["password"];
                        $name = $row["name"];
                        $email = $row["email"];
                        
                        // Check if the input password matches the stored password (either hashed or direct)
                        if (password_verify($password, $db_password) || $password === $db_password) {
                            // Password correct, start session
                            $_SESSION["loggedin"] = true;
                            $_SESSION["admin_id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["name"] = $name;
                            $_SESSION["email"] = $email;
                            $_SESSION["role"] = "admin"; // Hardcoded role as admin
                            
                            // Redirect to admin dashboard
                            redirect('dashboard.php');
                        } else {
                            // Password incorrect
                            $login_err = "Username atau password yang dimasukkan salah.";
                        }
                    }
                } else {
                    // Username doesn't exist
                    $login_err = "Username atau password yang dimasukkan salah.";
                }
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
    <link rel="stylesheet" href="../css/loginregister.css" />
    <title>Aquanest | Admin Login</title>
    <style>
        .alert {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .admin-badge {
            background-color: #e7f3ff;
            color: #0d6efd;
            padding: 5px 10px;
            border-radius: 15px;
            display: inline-block;
            margin-left: 10px;
            font-size: 0.8em;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container" id="container">
        <div class="form-container sign-in">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <h1>Admin Login <span class="admin-badge"><i class="fas fa-user-shield"></i> ADMIN</span></h1>
                
                <?php if (!empty($login_err)): ?>
                    <div class="alert alert-danger"><?php echo $login_err; ?></div>
                <?php endif; ?>
                
                <div class="social-icons">
                    <a href="https://www.instagram.com/" class="icon"><i class="fa-brands fa-instagram"></i></a>
                    <a href="https://web.facebook.com/" class="icon"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="https://www.youtube.com/" class="icon"><i class="fa-brands fa-youtube"></i></a>
                </div>
                <span>login dengan kredensial admin</span>
                <input type="text" name="username" placeholder="Username Admin" value="<?php echo $username; ?>" required />
                <input type="password" name="password" placeholder="Password Admin" required />
                <button type="submit" name="login">Masuk ke Dashboard</button>
                <div style="margin-top: 15px; text-align: center;">
                    <a href="../index.php" style="text-decoration: none; color: #444;">
                        <i class="fas fa-home"></i> Kembali ke Halaman Utama
                    </a>
                </div>
            </form>
        </div>
        <div class="toggle-container">
            <div class="toggle">
                <div class="toggle-panel toggle-right">
                    <h1>Halaman Admin</h1>
                    <p>Silahkan login untuk mengakses panel administrasi Aquanest</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Prevent toggling to sign-up form since this is admin login only
        document.addEventListener('DOMContentLoaded', function() {
            // Remove any event listeners from the original script
            const registerBtn = document.getElementById('register');
            if (registerBtn) {
                const newRegisterBtn = registerBtn.cloneNode(true);
                registerBtn.parentNode.replaceChild(newRegisterBtn, registerBtn);
            }
            
            const loginBtn = document.getElementById('login');
            if (loginBtn) {
                const newLoginBtn = loginBtn.cloneNode(true);
                loginBtn.parentNode.replaceChild(newLoginBtn, loginBtn);
            }
            
            // Force sign-in mode
            const container = document.getElementById('container');
            if (container.classList.contains('active')) {
                container.classList.remove('active');
            }
        });
    </script>
    <script src="../js/loginregister.js"></script>
    <script src="../js/navbar.js"></script>
</body>
</html>
