/**
 * Enhanced Dashboard JavaScript for Aquanest
 * Optimized for both mobile and desktop experiences
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips with mobile detection
    initializeTooltips();
    
    // Initialize charts
    initializeOrdersChart();
    initializeProductsChart();
    
    // Add event listeners for mobile sidebar
    setupMobileSidebar();
    
    // Setup responsive events
    handleResponsiveEvents();
    
    // Initialize lazy loading for better performance
    initializeLazyLoading();
});

/**
 * Initialize tooltips with mobile-specific behavior
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            trigger: 'ontouchstart' in document.documentElement ? 'click' : 'hover',
            boundary: 'window'
        });
    });
}

/**
 * Initialize the Orders Chart with mobile optimizations
 */
function initializeOrdersChart() {
    const ordersChartEl = document.getElementById('ordersChart');
    if (!ordersChartEl) return;
    
    const ctx = ordersChartEl.getContext('2d');
    const ordersData = JSON.parse(ordersChartEl.dataset.orders || '[]');
    const revenueData = JSON.parse(ordersChartEl.dataset.revenue || '[]');
    const weekLabels = JSON.parse(ordersChartEl.dataset.labels || '["Sen", "Sel", "Rab", "Kam", "Jum", "Sab", "Min"]');
    
    // Dynamically size font based on screen
    const fontSize = window.innerWidth < 768 ? 10 : 12;
    
    // Create responsive chart
    window.ordersChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: weekLabels,
            datasets: [{
                label: 'Jumlah Pesanan',
                data: ordersData,
                backgroundColor: 'rgba(78, 115, 223, 0.8)',
                borderColor: 'rgba(78, 115, 223, 1)',
                borderWidth: 1,
                barPercentage: window.innerWidth < 768 ? 0.7 : 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    precision: 0,
                    grid: {
                        drawBorder: false,
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        font: {
                            size: fontSize
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: fontSize
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: {
                            size: fontSize
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.formattedValue;
                        }
                    }
                }
            }
        }
    });
    
    // Toggle between Orders and Revenue
    const toggleChartViewBtn = document.getElementById('toggleChartView');
    if (toggleChartViewBtn) {
        toggleChartViewBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (window.ordersChart.data.datasets[0].label === 'Jumlah Pesanan') {
                window.ordersChart.data.datasets[0].label = 'Pendapatan (Rp)';
                window.ordersChart.data.datasets[0].data = revenueData;
                window.ordersChart.data.datasets[0].backgroundColor = 'rgba(40, 167, 69, 0.8)';
                window.ordersChart.data.datasets[0].borderColor = 'rgba(40, 167, 69, 1)';
                this.innerText = 'Tampilkan Pesanan';
            } else {
                window.ordersChart.data.datasets[0].label = 'Jumlah Pesanan';
                window.ordersChart.data.datasets[0].data = ordersData;
                window.ordersChart.data.datasets[0].backgroundColor = 'rgba(78, 115, 223, 0.8)';
                window.ordersChart.data.datasets[0].borderColor = 'rgba(78, 115, 223, 1)';
                this.innerText = 'Tampilkan Pendapatan';
            }
            
            window.ordersChart.update();
        });
    }
}

/**
 * Initialize Product Charts with device-specific optimizations
 */
function initializeProductsChart() {
    const productsChartEl = document.getElementById('productsChart');
    if (!productsChartEl) return;
    
    const ctx = productsChartEl.getContext('2d');
    const productLabels = JSON.parse(productsChartEl.dataset.labels || '[]');
    const productOrdersData = JSON.parse(productsChartEl.dataset.orders || '[]');
    const productRevenueData = JSON.parse(productsChartEl.dataset.revenue || '[]');
    
    // Choose optimal chart type based on device
    const chartType = window.innerWidth < 768 ? 'bar' : 'doughnut';
    const fontSize = window.innerWidth < 768 ? 10 : 12;
    
    // Chart colors
    const backgroundColors = [
        'rgba(78, 115, 223, 0.8)',
        'rgba(28, 200, 138, 0.8)',
        'rgba(246, 194, 62, 0.8)',
        'rgba(231, 74, 59, 0.8)',
        'rgba(54, 185, 204, 0.8)'
    ];
    
    const borderColors = [
        'rgba(78, 115, 223, 1)',
        'rgba(28, 200, 138, 1)',
        'rgba(246, 194, 62, 1)',
        'rgba(231, 74, 59, 1)',
        'rgba(54, 185, 204, 1)'
    ];
    
    // Create chart with appropriate options for device
    window.productsChart = new Chart(ctx, {
        type: chartType,
        data: {
            labels: productLabels,
            datasets: [{
                label: 'Jumlah Pesanan',
                data: productOrdersData,
                backgroundColor: backgroundColors,
                borderColor: borderColors,
                borderWidth: 1,
                barPercentage: 0.7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: window.innerWidth < 768 ? 'bottom' : 'right',
                    labels: {
                        boxWidth: window.innerWidth < 768 ? 10 : 15,
                        padding: window.innerWidth < 768 ? 5 : 10,
                        font: {
                            size: fontSize
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.formattedValue;
                            return label + ': ' + value;
                        }
                    }
                }
            },
            // Only apply scales configuration for bar charts
            ...(chartType === 'bar' ? {
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false,
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: {
                                size: fontSize
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: fontSize
                            }
                        }
                    }
                }
            } : {})
        }
    });
    
    // Toggle between product orders and revenue
    const toggleProductMetricsBtn = document.getElementById('toggleProductMetrics');
    if (toggleProductMetricsBtn) {
        toggleProductMetricsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (window.productsChart.data.datasets[0].label === 'Jumlah Pesanan') {
                window.productsChart.data.datasets[0].label = 'Pendapatan (Juta Rp)';
                window.productsChart.data.datasets[0].data = productRevenueData;
                this.innerText = 'Tampilkan Jumlah Pesanan';
            } else {
                window.productsChart.data.datasets[0].label = 'Jumlah Pesanan';
                window.productsChart.data.datasets[0].data = productOrdersData;
                this.innerText = 'Tampilkan Pendapatan';
            }
            
            window.productsChart.update();
        });
    }
}

/**
 * Setup mobile sidebar toggle functionality
 */
function setupMobileSidebar() {
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const content = document.querySelector('.content');
    
    if (!sidebarToggle || !sidebar) return;
    
    // Create backdrop for mobile
    const backdrop = document.createElement('div');
    backdrop.className = 'sidebar-backdrop';
    document.body.appendChild(backdrop);
    
    // Toggle sidebar on button click
    sidebarToggle.addEventListener('click', function(e) {
        e.preventDefault();
        sidebar.classList.toggle('show');
        backdrop.classList.toggle('show');
        document.body.classList.toggle('sidebar-open');
    });
    
    // Close sidebar when clicking outside
    backdrop.addEventListener('click', function() {
        sidebar.classList.remove('show');
        backdrop.classList.remove('show');
        document.body.classList.remove('sidebar-open');
    });
}

/**
 * Handle responsive events and chart resizing
 */
function handleResponsiveEvents() {
    // Debounce function for efficient resize handling
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this;
            const args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }
    
    // Handle window resize events
    window.addEventListener('resize', debounce(function() {
        // Rebuild the products chart if necessary based on screen width
        if (window.productsChart) {
            const isMobile = window.innerWidth < 768;
            const currentType = window.productsChart.config.type;
            
            if (isMobile && currentType !== 'bar') {
                rebuildProductsChart('bar');
            } else if (!isMobile && currentType !== 'doughnut') {
                rebuildProductsChart('doughnut');
            }
        }
        
        // Adjust other responsive elements as needed
        adjustTablesForMobile();
        
    }, 250));
    
    // Function to rebuild the products chart with a new type
    function rebuildProductsChart(newType) {
        if (!window.productsChart) return;
        
        const productsChartEl = document.getElementById('productsChart');
        if (!productsChartEl) return;
        
        const productLabels = JSON.parse(productsChartEl.dataset.labels || '[]');
        const currentData = window.productsChart.data.datasets[0].data;
        const currentLabel = window.productsChart.data.datasets[0].label;
        
        // Destroy existing chart
        window.productsChart.destroy();
        
        // Create new chart with appropriate type
        const ctx = productsChartEl.getContext('2d');
        const fontSize = window.innerWidth < 768 ? 10 : 12;
        
        // Chart colors
        const backgroundColors = [
            'rgba(78, 115, 223, 0.8)',
            'rgba(28, 200, 138, 0.8)',
            'rgba(246, 194, 62, 0.8)',
            'rgba(231, 74, 59, 0.8)',
            'rgba(54, 185, 204, 0.8)'
        ];
        
        const borderColors = [
            'rgba(78, 115, 223, 1)',
            'rgba(28, 200, 138, 1)',
            'rgba(246, 194, 62, 1)',
            'rgba(231, 74, 59, 1)',
            'rgba(54, 185, 204, 1)'
        ];
        
        window.productsChart = new Chart(ctx, {
            type: newType,
            data: {
                labels: productLabels,
                datasets: [{
                    label: currentLabel,
                    data: currentData,
                    backgroundColor: backgroundColors,
                    borderColor: borderColors,
                    borderWidth: 1,
                    barPercentage: 0.7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: window.innerWidth < 768 ? 'bottom' : 'right',
                        labels: {
                            boxWidth: window.innerWidth < 768 ? 10 : 15,
                            padding: window.innerWidth < 768 ? 5 : 10,
                            font: {
                                size: fontSize
                            }
                        }
                    }
                },
                ...(newType === 'bar' ? {
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false,
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                font: {
                                    size: fontSize
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: fontSize
                                }
                            }
                        }
                    }
                } : {})
            }
        });
    }
    
    // First run on page load
    adjustTablesForMobile();
}

/**
 * Adjust tables for mobile display
 */
function adjustTablesForMobile() {
    const isMobile = window.innerWidth < 768;
    const tables = document.querySelectorAll('.admin-table');
    
    tables.forEach(table => {
        const mobileHideColumns = table.querySelectorAll('.mobile-hide');
        
        mobileHideColumns.forEach(col => {
            col.style.display = isMobile ? 'none' : '';
        });
    });
}

/**
 * Initialize lazy loading for better performance
 */
function initializeLazyLoading() {
    const lazyImages = [].slice.call(document.querySelectorAll("img.lazy"));
    
    if ("IntersectionObserver" in window) {
        let lazyImageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    let lazyImage = entry.target;
                    lazyImage.src = lazyImage.dataset.src;
                    lazyImage.classList.remove("lazy");
                    lazyImageObserver.unobserve(lazyImage);
                }
            });
        });
        
        lazyImages.forEach(function(lazyImage) {
            lazyImageObserver.observe(lazyImage);
        });
    } else {
        // Fallback for browsers without IntersectionObserver support
        lazyImages.forEach(function(lazyImage) {
            lazyImage.src = lazyImage.dataset.src;
            lazyImage.classList.remove("lazy");
        });
    }
}

/**
 * Setup download functionality for charts
 */
document.addEventListener('DOMContentLoaded', function() {
    // Setup download for Orders Chart
    const downloadChartDataBtn = document.getElementById('downloadChartData');
    if (downloadChartDataBtn) {
        downloadChartDataBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!window.ordersChart) return;
            
            // Prepare CSV data
            const labels = window.ordersChart.data.labels;
            const datasets = window.ordersChart.data.datasets;
            let csvContent = "data:text/csv;charset=utf-8,";
            
            // Add headers
            let headers = ["Hari"];
            datasets.forEach(ds => {
                headers.push(ds.label);
            });
            csvContent += headers.join(",") + "\n";
            
            // Add data rows
            for (let i = 0; i < labels.length; i++) {
                let row = [labels[i]];
                datasets.forEach(ds => {
                    row.push(ds.data[i]);
                });
                csvContent += row.join(",") + "\n";
            }
            
            // Create download link
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "pesanan_mingguan.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    }
    
    // Setup download for Products Chart
    const downloadProductDataBtn = document.getElementById('downloadProductData');
    if (downloadProductDataBtn) {
        downloadProductDataBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!window.productsChart) return;
            
            // Prepare CSV data
            const labels = window.productsChart.data.labels;
            const datasets = window.productsChart.data.datasets;
            let csvContent = "data:text/csv;charset=utf-8,";
            
            // Add headers
            let headers = ["Produk"];
            datasets.forEach(ds => {
                headers.push(ds.label);
            });
            csvContent += headers.join(",") + "\n";
            
            // Add data rows
            for (let i = 0; i < labels.length; i++) {
                let row = [labels[i]];
                datasets.forEach(ds => {
                    row.push(ds.data[i]);
                });
                csvContent += row.join(",") + "\n";
            }
            
            // Create download link
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "produk_terlaris.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    }
});