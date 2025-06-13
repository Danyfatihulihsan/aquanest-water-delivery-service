<?php
session_start();

// Include database connection & functions
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Jika sudah login, langsung redirect ke index atau halaman yang diminta
if (isset($_SESSION['user_id'])) {
    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] . '.php' : 'index.php';
    header("Location: $redirect");
    exit();
}

// Inisialisasi variabel
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Jika form login dikirim
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    // Ambil dan validasi input
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (empty($username)) {
        $username_err = "Silakan masukkan username.";
    }
    if (empty($password)) {
        $password_err = "Silakan masukkan password.";
    }

    if (empty($username_err) && empty($password_err)) {
        $sql = "SELECT user_id, username, password, name FROM users WHERE username = :username";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bindParam(":username", $username, PDO::PARAM_STR);
            if ($stmt->execute() && $stmt->rowCount() == 1) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (password_verify($password, $row["password"])) {
                    $_SESSION["loggedin"] = true;
                    $_SESSION["user_id"] = $row["user_id"];
                    $_SESSION["username"] = $row["username"];
                    $_SESSION["name"] = $row["name"];

                    // Redirect setelah login
                    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] . '.php' : 'index.php';
                    header("Location: $redirect");
                    exit();
                } else {
                    $login_err = "Username atau password salah.";
                }
            } else {
                $login_err = "Username atau password salah.";
            }
        }
    }
}

// Notifikasi sukses registrasi
$registration_success = "";
if (isset($_GET['registration_success']) && $_GET['registration_success'] == '1') {
    $registration_success = "Pendaftaran berhasil! Silakan login.";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
    <link rel="stylesheet" href="css/loginregister.css" />
    <title>Aquanest | Masuk</title>
</head>
<body>
    <div class="container" id="container">
        <div class="form-container sign-up">
            <!-- Sign Up Form (we'll leave it here for the animation but point to register.php) -->
            <form action="register.php" method="post">
                <h1>Registrasi</h1>
                <div class="social-icons">
                    <a href="https://www.instagram.com/" class="icon"><i class="fa-brands fa-instagram"></i></a>
                    <a href="https://web.facebook.com/" class="icon"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="https://www.youtube.com/" class="icon"><i class="fa-brands fa-youtube"></i></a>
                </div>
                <span>atau daftar dengan email</span>
                <input type="text" name="name" placeholder="Nama Lengkap" required />
                <input type="text" name="username" placeholder="Username" required />
                <input type="email" name="email" placeholder="Email" required />
                <input type="password" name="password" placeholder="Sandi" required />
                <input type="password" name="confirm_password" placeholder="Konfirmasi sandi" required />
                <button type="submit" name="register">Daftar</button>
            </form>
        </div>
        <div class="form-container sign-in">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <h1>Login</h1>
                
                <?php if (!empty($login_err)): ?>
                    <div class="alert alert-danger"><?php echo $login_err; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($registration_success)): ?>
                    <div class="alert alert-success"><?php echo $registration_success; ?></div>
                <?php endif; ?>
                
                <div class="social-icons">
                    <a href="https://www.instagram.com/" class="icon"><i class="fa-brands fa-instagram"></i></a>
                    <a href="https://web.facebook.com/" class="icon"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="https://www.youtube.com/" class="icon"><i class="fa-brands fa-youtube"></i></a>
                </div>
                <span>atau login dengan email dan password</span>
                <input type="text" name="username" placeholder="Username" value="<?php echo $username; ?>" required />
                <input type="password" name="password" placeholder="Sandi" required />
                <a href="forgot-password.php">Lupa Password?</a>
                <button type="submit" name="login">Masuk</button>
            </form>
        </div>
        <div class="toggle-container">
            <div class="toggle">
                <div class="toggle-panel toggle-left">
                    <h1>Selamat Datang Kembali!</h1>
                    <p>Silahkan Login Untuk Mengakses Aquanest</p>
                    <button class="hidden" id="login">Masuk</button>
                </div>
                <div class="toggle-panel toggle-right">
                    <h1>Selamat Datang!</h1>
                    <p>Silahkan Mengisi Data Untuk Mengakses Aquanest</p>
                    <button class="hidden" id="register">Daftar</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="js/loginregister.js"></script>
    <script src="js/navbar.js"></script>
</body>
</html>