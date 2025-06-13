<nav class="custom-navbar">
    <div class="navbar-container">
        <!-- Logo -->
        <a href="index.php" class="navbar-logo">
            <img src="img/logo.jpg" alt="Aquanest">
            <span>Aquanest</span>
        </a>
        
        <!-- Hamburger Menu for Mobile -->
        <div class="navbar-toggle" id="navbar-toggle">
            <span></span>
            <span></span>
            <span></span>
        </div>
        
        <!-- Navigation Links and User Actions -->
        <div class="navbar-content" id="navbar-content">
            <!-- Main Navigation -->
            <ul class="navbar-menu">
                <li class="navbar-item">
                    <a href="index.php" class="navbar-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Beranda</span>
                    </a>
                </li>
                <li class="navbar-item">
                    <a href="products.php" class="navbar-link <?php echo (basename($_SERVER['PHP_SELF']) == 'products.php') ? 'active' : ''; ?>">
                        <i class="fas fa-water"></i>
                        <span>Produk</span>
                    </a>
                </li>
                <li class="navbar-item">
                    <a href="order.php" class="navbar-link <?php echo (basename($_SERVER['PHP_SELF']) == 'order.php') ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-basket"></i>
                        <span>Pesan</span>
                    </a>
                </li>
                <li class="navbar-item">
                    <a href="about.php" class="navbar-link <?php echo (basename($_SERVER['PHP_SELF']) == 'about.php') ? 'active' : ''; ?>">
                        <i class="fas fa-info-circle"></i>
                        <span>Tentang Kami</span>
                    </a>
                </li>
                <li class="navbar-item">
                    <a href="contact.php" class="navbar-link <?php echo (basename($_SERVER['PHP_SELF']) == 'contact.php') ? 'active' : ''; ?>">
                        <i class="fas fa-envelope"></i>
                        <span>Kontak</span>
                    </a>
                </li>
            </ul>
            
            <!-- User Actions -->
            <div class="navbar-actions">
                <!-- Cart -->
                <a href="order.php" class="navbar-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if (!empty($_SESSION['cart'])): ?>
                    <span class="cart-count"><?php echo count($_SESSION['cart']); ?></span>
                    <?php endif; ?>
                </a>
                
                <!-- User Menu -->
                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-dropdown">
                    <button class="user-dropdown-toggle">
                        <i class="fas fa-user-circle"></i>
                        <span><?php echo $_SESSION['name']; ?></span>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </button>
                    <ul class="user-dropdown-menu">
                        <li>
                            <a href="profile.php">
                                <i class="fas fa-user"></i>
                                <span>Profil</span>
                            </a>
                        </li>
                        <li>
                            <a href="my-orders.php">
                                <i class="fas fa-shopping-bag"></i>
                                <span>Pesanan Saya</span>
                            </a>
                        </li>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <li>
                            <a href="admin/dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>
                                <span>Dashboard Admin</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="dropdown-divider"></li>
                        <li>
                            <a href="logout.php">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <?php else: ?>
                <div class="auth-buttons">
                    <a href="login.php" class="login-btn">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Login</span>
                    </a>
                    <a href="register.php" class="register-btn">
                        <i class="fas fa-user-plus"></i>
                        <span>Daftar</span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>