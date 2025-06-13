/**
 * Aquanest Admin - Responsive JS
 * Script untuk meningkatkan responsivitas dashboard pada berbagai ukuran layar
 */

// Pengaturan untuk gambar responsif dan lazy loading
class ResponsiveImageHandler {
    constructor() {
        this.images = document.querySelectorAll('img:not(.lazy-loaded)');
        this.lazyImages = document.querySelectorAll('img[data-src]');
        this.init();
    }
    
    init() {
        this.setupLazyLoading();
        this.handleImageErrors();
        this.makeImagesResponsive();
    }
    
    setupLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const image = entry.target;
                        if (image.dataset.src) {
                            image.src = image.dataset.src;
                            image.removeAttribute('data-src');
                            image.classList.add('lazy-loaded');
                            observer.unobserve(image);
                        }
                    }
                });
            }, {
                rootMargin: '50px 0px', // Muat gambar sedikit sebelum masuk viewport
                threshold: 0.01
            });
            
            this.lazyImages.forEach(img => {
                imageObserver.observe(img);
                
                // Tambahkan kelas untuk animasi fade in
                img.classList.add('lazy-load');
                
                // Tambahkan placeholder sementara
                const placeholder = document.createElement('div');
                placeholder.className = 'img-placeholder';
                placeholder.style.width = img.width ? `${img.width}px` : '100%';
                placeholder.style.height = img.height ? `${img.height}px` : '200px';
                
                // Masukkan placeholder sebelum gambar
                if (img.parentNode) {
                    img.parentNode.insertBefore(placeholder, img);
                    
                    // Hapus placeholder saat gambar dimuat
                    img.addEventListener('load', () => {
                        if (placeholder.parentNode) {
                            placeholder.parentNode.removeChild(placeholder);
                        }
                        img.classList.add('loaded');
                    });
                }
            });
        } else {
            // Fallback untuk browser yang tidak mendukung IntersectionObserver
            this.lazyImages.forEach(img => {
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                }
            });
        }
    }
    
    handleImageErrors() {
        this.images.forEach(img => {
            img.addEventListener('error', function() {
                // Jika gambar gagal dimuat, ganti dengan placeholder
                if (!this.classList.contains('error-handled')) {
                    this.classList.add('error-handled');
                    
                    // Tentukan placeholder berdasarkan jenis gambar
                    if (this.classList.contains('product-image')) {
                        this.src = '../img/products/default.jpg';
                    } else if (this.classList.contains('user-image')) {
                        this.src = '../img/users/default.jpg';
                    } else if (this.classList.contains('logo')) {
                        this.src = '../img/logo-placeholder.png';
                    } else {
                        this.src = '../img/placeholder.jpg';
                    }
                    
                    // Jika path relatif tidak berfungsi, coba path absolut
                    this.addEventListener('error', function() {
                        if (this.classList.contains('product-image')) {
                            this.src = 'img/products/default.jpg';
                        } else if (this.classList.contains('user-image')) {
                            this.src = 'img/users/default.jpg';
                        } else if (this.classList.contains('logo')) {
                            this.src = 'img/logo-placeholder.png';
                        } else {
                            this.src = 'img/placeholder.jpg';
                        }
                    });
                }
            });
        });
    }
    
    makeImagesResponsive() {
        // Pastikan semua gambar memiliki max-width: 100% dan height: auto
        const style = document.createElement('style');
        style.textContent = `
            img {
                max-width: 100%;
                height: auto;
                transition: opacity 0.3s ease;
            }
            
            .img-placeholder {
                background-color: #f1f3f4;
                border-radius: 4px;
                animation: pulse 1.5s infinite;
            }
            
            @keyframes pulse {
                0% { opacity: 0.6; }
                50% { opacity: 0.8; }
                100% { opacity: 0.6; }
            }
            
            .lazy-load {
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            
            .lazy-load.loaded {
                opacity: 1;
            }
            
            .card-img-top {
                aspect-ratio: 16/9;
                object-fit: cover;
            }
            
            .product-thumb {
                aspect-ratio: 1/1;
                object-fit: cover;
            }
        `;
        document.head.appendChild(style);
    }
}

// Responsif table handler
class ResponsiveTableHandler {
    constructor() {
        this.tables = document.querySelectorAll('table');
        this.init();
    }
    
    init() {
        this.wrapTablesInResponsiveDiv();
        this.setupMobilePriority();
        this.makeTablesResponsive();
    }
    
    wrapTablesInResponsiveDiv() {
        this.tables.forEach(table => {
            // Jika tabel belum dibungkus dalam div responsive
            if (!table.parentElement.classList.contains('table-responsive')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'table-responsive';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            }
        });
    }
    
    setupMobilePriority() {
        const mobileTables = document.querySelectorAll('table.mobile-responsive');
        mobileTables.forEach(table => {
            // Tambahkan atribut data-priority ke header kolom
            const headers = table.querySelectorAll('th');
            headers.forEach((header, index) => {
                if (!header.hasAttribute('data-priority')) {
                    // Default, semua kolom ditampilkan di desktop
                    header.setAttribute('data-priority', 'desktop');
                }
            });
            
            // Pada mobile, sembunyikan kolom yang tidak penting
            if (window.innerWidth < 768) {
                headers.forEach((header, index) => {
                    if (header.dataset.priority !== 'high') {
                        header.classList.add('d-none', 'd-md-table-cell');
                        
                        // Sembunyikan juga sel-sel di kolom terkait
                        const rows = table.querySelectorAll('tbody tr');
                        rows.forEach(row => {
                            if (row.cells[index]) {
                                row.cells[index].classList.add('d-none', 'd-md-table-cell');
                            }
                        });
                    }
                });
            }
        });
    }
    
    makeTablesResponsive() {
        // Tambahkan CSS untuk tabel responsif
        const style = document.createElement('style');
        style.textContent = `
            .table-responsive {
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
            }
            
            .table-responsive::-webkit-scrollbar {
                height: 6px;
            }
            
            .table-responsive::-webkit-scrollbar-thumb {
                background-color: rgba(0, 0, 0, 0.2);
                border-radius: 3px;
            }
            
            /* Card mode pada mobile untuk tabel tertentu */
            @media (max-width: 575.98px) {
                .table-to-cards thead {
                    display: none;
                }
                
                .table-to-cards, 
                .table-to-cards tbody,
                .table-to-cards tr {
                    display: block;
                    width: 100%;
                }
                
                .table-to-cards tr {
                    margin-bottom: 1rem;
                    border: 1px solid #e9ecef;
                    border-radius: 0.25rem;
                    overflow: hidden;
                }
                
                .table-to-cards td {
                    display: flex;
                    justify-content: space-between;
                    text-align: right;
                    border: none;
                    border-bottom: 1px solid #e9ecef;
                }
                
                .table-to-cards td:last-child {
                    border-bottom: none;
                }
                
                .table-to-cards td::before {
                    content: attr(data-label);
                    font-weight: 600;
                    text-align: left;
                    padding-right: 1rem;
                }
            }
        `;
        document.head.appendChild(style);
        
        // Konversi tabel yang ada kelas 'table-to-cards' ke tampilan kartu pada mobile
        const cardTables = document.querySelectorAll('table.table-to-cards');
        cardTables.forEach(table => {
            const headers = table.querySelectorAll('th');
            const rows = table.querySelectorAll('tbody tr');
            
            // Tambahkan data-label ke setiap sel sesuai dengan header
            rows.forEach(row => {
                Array.from(row.cells).forEach((cell, index) => {
                    if (headers[index]) {
                        cell.setAttribute('data-label', headers[index].textContent);
                    }
                });
            });
        });
    }
}

// Chart responsif handler
class ResponsiveChartHandler {
    constructor() {
        this.chartContainers = document.querySelectorAll('.chart-container');
        this.init();
    }
    
    init() {
        this.setupResponsiveCharts();
        this.handleResize();
    }
    
    setupResponsiveCharts() {
        // Tambahkan CSS untuk kontainer chart responsif
        const style = document.createElement('style');
        style.textContent = `
            .chart-container {
                position: relative;
                margin: auto;
                height: 300px;
                width: 100%;
            }
            
            @media (max-width: 767.98px) {
                .chart-container {
                    height: 250px;
                }
            }
            
            @media (max-width: 575.98px) {
                .chart-container {
                    height: 200px;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    handleResize() {
        // Resize chart saat ukuran window berubah
        window.addEventListener('resize', function() {
            if (typeof Chart !== 'undefined' && Chart.instances) {
                Chart.instances.forEach(instance => {
                    instance.resize();
                });
            }
        });
    }
}

// Mobile sidebar handler
class MobileSidebarHandler {
    constructor() {
        this.sidebar = document.getElementById('sidebar');
        this.sidebarToggle = document.getElementById('sidebarToggle');
        this.sidebarBackdrop = document.getElementById('sidebarBackdrop');
        this.init();
    }
    
    init() {
        if (this.sidebar && this.sidebarToggle) {
            this.setupSidebarToggle();
            this.handleClickOutside();
            this.updateSidebarOnResize();
        }
    }
    
    setupSidebarToggle() {
        this.sidebarToggle.addEventListener('click', () => {
            this.toggleSidebar();
        });
        
        // Jika backdrop ada, tambahkan event listener
        if (this.sidebarBackdrop) {
            this.sidebarBackdrop.addEventListener('click', () => {
                this.toggleSidebar();
            });
        }
    }
    
    toggleSidebar() {
        this.sidebar.classList.toggle('show');
        
        if (this.sidebarBackdrop) {
            this.sidebarBackdrop.classList.toggle('show');
        }
        
        // Toggle ikon menu
        const icon = this.sidebarToggle.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-times');
        }
    }
    
    handleClickOutside() {
        // Tutup sidebar jika klik di luar sidebar pada mobile
        document.addEventListener('click', (event) => {
            const isMobile = window.innerWidth < 768;
            const isClickInsideSidebar = this.sidebar.contains(event.target);
            const isClickOnToggle = this.sidebarToggle.contains(event.target);
            
            if (isMobile && !isClickInsideSidebar && !isClickOnToggle && this.sidebar.classList.contains('show')) {
                this.toggleSidebar();
            }
        });
    }
    
    updateSidebarOnResize() {
        // Reset sidebar saat ukuran layar berubah
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768 && this.sidebar.classList.contains('show')) {
                this.sidebar.classList.remove('show');
                
                if (this.sidebarBackdrop) {
                    this.sidebarBackdrop.classList.remove('show');
                }
                
                // Reset ikon
                const icon = this.sidebarToggle.querySelector('i');
                if (icon && icon.classList.contains('fa-times')) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
        });
    }
}

// Inisialisasi semua handler saat DOM dimuat
document.addEventListener('DOMContentLoaded', function() {
    // Hilangkan loading spinner
    const loadingSpinner = document.getElementById('loadingSpinner');
    if (loadingSpinner) {
        loadingSpinner.style.display = 'none';
    }
    
    // Tampilkan body
    document.body.style.opacity = '1';
    
    // Inisialisasi handler
    new ResponsiveImageHandler();
    new ResponsiveTableHandler();
    new ResponsiveChartHandler();
    new MobileSidebarHandler();
    
    // Animasi untuk card statistik
    const statsCards = document.querySelectorAll('.stats-card');
    statsCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Flash messages auto-hide
    window.setTimeout(function() {
        const alertMessages = document.querySelectorAll('.alert-dismissible');
        alertMessages.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});