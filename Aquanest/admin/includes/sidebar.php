<?php
// File: includes/sidebar.php

/**
 * Modern Sidebar for Aquanest Admin Panel
 */
?>
<style>
    /* CSS untuk sidebar scrollable */
    .sidebar {
        height: 100vh; /* Tinggi mengikuti tinggi viewport */
        overflow-y: auto; /* Menambahkan scrollbar vertikal ketika konten melebihi tinggi */
        display: flex;
        flex-direction: column;
    }

    /* Pastikan elemen konten sidebar bisa di-scroll */
    .sidebar-menu-container {
        flex: 1;
        overflow-y: auto;
    }

    /* Tambahan CSS agar scrollbar lebih baik */
    .sidebar::-webkit-scrollbar {
        width: 5px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.05);
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.2);
        border-radius: 10px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(0, 0, 0, 0.3);
    }
</style>
<!-- Sidebar HTML Structure -->
<div class="sidebar">
    <div class="sidebar-logo">
        <img src="../img/logo.jpg" alt="Aquanest Logo">
        <span>Aquanest</span>
    </div>
    
    <div class="sidebar-user">
        <div class="sidebar-user-avatar">
            A
        </div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name">Admin</div>
            <div class="sidebar-user-role">Administrator</div>
        </div>
    </div>
    
        <!-- <button class="sidebar-action-btn">
            <i class="fas fa-plus"></i>
            <span>Tambah Pesanan</span>
        </button> -->
    
    <!-- Semua konten menu yang bisa di-scroll diletakkan di dalam div ini -->
    <div class="sidebar-menu-container">
        <div class="sidebar-section">MENU UTAMA</div>
        
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item">
                <a href="dashboard.php" class="sidebar-menu-link active">
                    <div class="sidebar-menu-icon">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <span class="sidebar-menu-text">Dashboard</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="manage_orders.php" class="sidebar-menu-link ">
                    <div class="sidebar-menu-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <span class="sidebar-menu-text">Kelola Pesanan</span>
                    <span class="badge-notify">7</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="manage_products.php" class="sidebar-menu-link ">
                    <div class="sidebar-menu-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <span class="sidebar-menu-text">Kelola Produk</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="manage_customers.php" class="sidebar-menu-link">
                    <div class="sidebar-menu-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <span class="sidebar-menu-text">Kelola Pelanggan</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-section">PEMBAYARAN & LAPORAN</div>
        
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item">
                <a href="view_payment.php" class="sidebar-menu-link">
                    <div class="sidebar-menu-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <span class="sidebar-menu-text">Verifikasi Pembayaran</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="reports.php" class="sidebar-menu-link">
                    <div class="sidebar-menu-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <span class="sidebar-menu-text">Laporan</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
    <a href="admin_couriers.php" class="sidebar-menu-link">
        <div class="sidebar-menu-icon">
            <i class="fas fa-motorcycle"></i>
        </div>
        <span class="sidebar-menu-text">Kelola Kurir</span>
    </a>
</li>
        </ul>
        
        <div class="sidebar-section">PENGATURAN</div>
        
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item">
                <a href="settings.php" class="sidebar-menu-link">
                    <div class="sidebar-menu-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <span class="sidebar-menu-text">Pengaturan</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="../index.php" target="_blank" class="sidebar-menu-link">
                    <div class="sidebar-menu-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <span class="sidebar-menu-text">Lihat Website</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="logout.php" class="sidebar-menu-link">
                    <div class="sidebar-menu-icon">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                    <span class="sidebar-menu-text">Logout</span>
                </a>
            </li>
        </ul>
    </div>
    
    <div class="sidebar-footer">
        <div class="sidebar-version">Admin Panel v1.0</div>
    </div>
    
    <div class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-chevron-left"></i>
    </div>
</div>

<!-- Mobile Menu Toggle Button -->
<button class="mobile-menu-toggle" id="mobileMenuToggle">
    <i class="fas fa-bars"></i>
</button>

<!-- Backdrop for mobile -->
<div class="sidebar-backdrop"></div>

<!-- JavaScript for Sidebar Functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const backdrop = document.querySelector('.sidebar-backdrop');
    
    // Toggle sidebar collapse
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            // Change icon based on state
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.classList.remove('fa-chevron-left');
                icon.classList.add('fa-chevron-right');
            } else {
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-left');
            }
        });
    }
    
    // Mobile menu toggle
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.add('mobile-show');
            backdrop.classList.add('show');
        });
    }
    
    // Close sidebar when clicking outside on mobile
    if (backdrop) {
        backdrop.addEventListener('click', function() {
            sidebar.classList.remove('mobile-show');
            backdrop.classList.remove('show');
        });
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 991.98) {
            sidebar.classList.remove('mobile-show');
            backdrop.classList.remove('show');
        }
    });
    
    // Add click handler for quick action button
    const actionBtn = document.querySelector('.sidebar-action-btn');
    if (actionBtn) {
        actionBtn.addEventListener('click', function() {
            window.location.href = 'add_order.php';
        });
    }
});
</script>