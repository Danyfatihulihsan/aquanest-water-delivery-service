<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$cartItemCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aquanest - <?php echo isset($pageTitle) ? $pageTitle : 'Detail Produk'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        /* Custom styles for navbar */
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .navbar-brand i {
            color: #0d6efd;
        }
        
        .navbar-nav .nav-link {
            margin: 0 10px;
            transition: color 0.3s ease;
        }
        
        .navbar-nav .nav-link:hover {
            color: #0d6efd !important;
        }
        
        .badge-cart {
            position: absolute;
            top: -5px;
            right: -10px;
            background-color: #dc3545;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.75rem;
        }
        
        .search-form {
            min-width: 300px;
        }
        
        @media (max-width: 768px) {
            .search-form {
                min-width: auto;
                margin: 10px 0;
            }
        }
        
        .dropdown-menu {
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<!-- Top Bar -->
<div class="bg-light py-2 d-none d-md-block">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <small class="text-muted">
                    <i class="fas fa-phone me-2"></i>+62 812-3456-7890
                    <span class="mx-2">|</span>
                    <i class="fas fa-envelope me-2"></i>info@aquanest.com
                </small>
            </div>
            <div class="col-md-6 text-end">
                <small class="text-muted">
                    <i class="fas fa-truck me-2"></i>Gratis Ongkir untuk pembelian diatas Rp 500.000
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Main Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container">
        <!-- Logo -->
        <a class="navbar-brand" href="../index.php">
            <i class="fas fa-fish"></i> Aquanest
        </a>
        
        <!-- Mobile Toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Navbar Content -->
        <div class="collapse navbar-collapse" id="navbarMain">
            <!-- Search Form -->
            <form class="d-flex mx-auto search-form" action="../search.php" method="GET">
                <div class="input-group">
                    <input type="text" class="form-control" name="q" placeholder="Cari produk..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                    <button class="btn btn-outline-primary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
            
            <!-- Right Menu -->
            <ul class="navbar-nav ms-auto align-items-center">
                <!-- Back to Products -->
                <li class="nav-item">
                    <a class="nav-link" href="../products.php">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </li>
                
                <!-- Cart -->
                <li class="nav-item position-relative">
                    <a class="nav-link" href="../cart.php">
                        <i class="fas fa-shopping-cart"></i>
                        <?php if ($cartItemCount > 0): ?>
                            <span class="badge badge-cart"><?php echo $cartItemCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <!-- User Account -->
                <?php if ($isLoggedIn): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo $_SESSION['user_name'] ?? 'User'; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user me-2"></i>Profil</a></li>
                            <li><a class="dropdown-item" href="../orders.php"><i class="fas fa-box me-2"></i>Pesanan</a></li>
                            <li><a class="dropdown-item" href="../wishlist.php"><i class="fas fa-heart me-2"></i>Wishlist</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../login.php">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Category Bar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary py-2">
    <div class="container">
        <button class="navbar-toggler w-100" type="button" data-bs-toggle="collapse" data-bs-target="#categoryNav">
            <span class="me-2">Kategori</span>
            <i class="fas fa-chevron-down"></i>
        </button>
        
        <div class="collapse navbar-collapse" id="categoryNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link text-white" href="../products.php">
                        <i class="fas fa-th me-1"></i> Semua Produk
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="../category.php?cat=ikan">
                        <i class="fas fa-fish me-1"></i> Ikan Hias
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="../category.php?cat=tanaman">
                        <i class="fas fa-leaf me-1"></i> Tanaman
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="../category.php?cat=akuarium">
                        <i class="fas fa-square me-1"></i> Akuarium
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="../category.php?cat=filter">
                        <i class="fas fa-filter me-1"></i> Filter
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="../category.php?cat=pakan">
                        <i class="fas fa-cookie-bite me-1"></i> Pakan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="../category.php?cat=aksesori">
                        <i class="fas fa-cog me-1"></i> Aksesori
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Breadcrumb (will be populated by detail.php) -->
<nav aria-label="breadcrumb" class="bg-light py-2">
    <div class="container">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="../index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="../products.php" class="text-decoration-none">Produk</a></li>
            <?php if (isset($productCategory)): ?>
                <li class="breadcrumb-item"><a href="../category.php?cat=<?php echo urlencode($productCategory); ?>" class="text-decoration-none"><?php echo htmlspecialchars($productCategory); ?></a></li>
            <?php endif; ?>
            <?php if (isset($productName)): ?>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($productName); ?></li>
            <?php endif; ?>
        </ol>
    </div>
</nav>