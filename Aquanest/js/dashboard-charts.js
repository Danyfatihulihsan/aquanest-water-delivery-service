/**
 * Aquanest Admin - Dashboard Charts
 * This script handles the rendering of charts on the dashboard
 */

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Check if Chart.js is available
    if (typeof Chart === 'undefined') {
        console.error('Error: Chart.js library not loaded');
        document.querySelectorAll('.chart-container').forEach(function(container) {
            container.innerHTML = '<div class="alert alert-danger">Error: Chart.js library not loaded. Please check your internet connection or try again later.</div>';
        });
        return;
    }

    // Initialize charts
    initializeOrdersChart();
    initializeProductsChart();
});

/**
 * Initialize the Orders and Revenue Chart
 */
function initializeOrdersChart() {
    var ordersChartElement = document.getElementById('ordersChart');
    
    // Check if chart element exists
    if (!ordersChartElement) {
        console.warn('Orders chart element not found');
        return;
    }
    
    try {
        // Get chart data from data attributes
        var labels = [];
        var ordersData = [];
        var revenueData = [];
        
        try {
            labels = JSON.parse(ordersChartElement.getAttribute('data-labels') || '["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"]');
        } catch (e) {
            console.warn('Error parsing chart labels, using defaults');
            labels = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
        }
        
        try {
            ordersData = JSON.parse(ordersChartElement.getAttribute('data-orders') || '[0, 0, 0, 0, 0, 0, 0]');
        } catch (e) {
            console.warn('Error parsing orders data, using defaults');
            ordersData = [0, 0, 0, 0, 0, 0, 0];
        }
        
        try {
            revenueData = JSON.parse(ordersChartElement.getAttribute('data-revenue') || '[0, 0, 0, 0, 0, 0, 0]');
        } catch (e) {
            console.warn('Error parsing revenue data, using defaults');
            revenueData = [0, 0, 0, 0, 0, 0, 0];
        }
        
        // Create chart
        var ctx = ordersChartElement.getContext('2d');
        window.ordersChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Order Count',
                    data: ordersData,
                    backgroundColor: 'rgba(48, 85, 211, 0.8)',
                    borderColor: 'rgba(48, 85, 211, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
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
        
        // Add toggle functionality if the toggle element exists
        var toggleButton = document.getElementById('toggleChartView');
        if (toggleButton) {
            toggleButton.addEventListener('click', function(e) {
                e.preventDefault();
                toggleOrdersChart();
            });
        }
        
        console.log('Orders chart initialized successfully');
    } catch (error) {
        console.error('Failed to initialize orders chart:', error);
        if (ordersChartElement.parentNode) {
            ordersChartElement.parentNode.innerHTML = '<div class="alert alert-danger">Failed to load chart. Error: ' + error.message + '</div>';
        }
    }
}

/**
 * Toggle between Orders and Revenue data
 */
function toggleOrdersChart() {
    if (!window.ordersChart) return;
    
    var chart = window.ordersChart;
    var toggleButton = document.getElementById('toggleChartView');
    
    if (chart.data.datasets[0].label === 'Order Count') {
        // Switch to revenue
        chart.data.datasets[0].label = 'Revenue';
        chart.data.datasets[0].data = JSON.parse(document.getElementById('ordersChart').getAttribute('data-revenue') || '[0, 0, 0, 0, 0, 0, 0]');
        chart.data.datasets[0].backgroundColor = 'rgba(34, 197, 94, 0.8)';
        chart.data.datasets[0].borderColor = 'rgba(34, 197, 94, 1)';
        
        if (toggleButton) {
            toggleButton.innerText = 'Show Orders';
        }
    } else {
        // Switch to orders
        chart.data.datasets[0].label = 'Order Count';
        chart.data.datasets[0].data = JSON.parse(document.getElementById('ordersChart').getAttribute('data-orders') || '[0, 0, 0, 0, 0, 0, 0]');
        chart.data.datasets[0].backgroundColor = 'rgba(48, 85, 211, 0.8)';
        chart.data.datasets[0].borderColor = 'rgba(48, 85, 211, 1)';
        
        if (toggleButton) {
            toggleButton.innerText = 'Show Revenue';
        }
    }
    
    chart.update();
}

/**
 * Initialize the Products Chart
 */
function initializeProductsChart() {
    var productsChartElement = document.getElementById('productsChart');
    
    // Check if chart element exists
    if (!productsChartElement) {
        console.warn('Products chart element not found');
        return;
    }
    
    try {
        // Get chart data from data attributes
        var labels = [];
        var productsData = [];
        var revenueData = [];
        
        try {
            labels = JSON.parse(productsChartElement.getAttribute('data-labels') || '[]');
        } catch (e) {
            console.warn('Error parsing product labels, using defaults');
            labels = ["19L Gallon", "1.5L Bottle", "600ml Bottle", "Cup Package", "Refill"];
        }
        
        try {
            productsData = JSON.parse(productsChartElement.getAttribute('data-orders') || '[]');
        } catch (e) {
            console.warn('Error parsing product data, using defaults');
            productsData = [25, 18, 12, 8, 5];
        }
        
        try {
            revenueData = JSON.parse(productsChartElement.getAttribute('data-revenue') || '[]');
        } catch (e) {
            console.warn('Error parsing product revenue data, using defaults');
            revenueData = productsData.map(function(val) { return val * 25000; });
        }
        
        // Use default data if no data available
        if (labels.length === 0 || productsData.length === 0) {
            labels = ["19L Gallon", "1.5L Bottle", "600ml Bottle", "Cup Package", "Refill"];
            productsData = [25, 18, 12, 8, 5];
            revenueData = [625000, 450000, 300000, 200000, 125000];
        }
        
        // Define chart colors
        var backgroundColors = [
            'rgba(48, 85, 211, 0.8)',
            'rgba(34, 197, 94, 0.8)',
            'rgba(59, 130, 246, 0.8)',
            'rgba(245, 158, 11, 0.8)',
            'rgba(239, 68, 68, 0.8)'
        ];
        
        var borderColors = [
            'rgba(48, 85, 211, 1)',
            'rgba(34, 197, 94, 1)',
            'rgba(59, 130, 246, 1)',
            'rgba(245, 158, 11, 1)',
            'rgba(239, 68, 68, 1)'
        ];
        
        // Determine chart type based on screen size
        var chartType = window.innerWidth < 768 ? 'bar' : 'doughnut';
        
        // Create chart
        var ctx = productsChartElement.getContext('2d');
        window.productsChart = new Chart(ctx, {
            type: chartType,
            data: {
                labels: labels,
                datasets: [{
                    label: 'Sales Count',
                    data: productsData,
                    backgroundColor: backgroundColors,
                    borderColor: borderColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: window.innerWidth < 768 ? 'bottom' : 'right'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var label = context.label || '';
                                var value = context.raw || 0;
                                return label + ': ' + value;
                            }
                        }
                    }
                },
                ...(chartType === 'bar' ? {
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                } : {})
            }
        });
        
        // Add toggle functionality if the toggle element exists
        var toggleButton = document.getElementById('toggleProductMetrics');
        if (toggleButton) {
            toggleButton.addEventListener('click', function(e) {
                e.preventDefault();
                toggleProductsChart();
            });
        }
        
        // Handle window resize to change chart type
        window.addEventListener('resize', function() {
            var newChartType = window.innerWidth < 768 ? 'bar' : 'doughnut';
            if (window.productsChart && window.productsChart.config.type !== newChartType) {
                recreateProductsChart(newChartType);
            }
        });
        
        console.log('Products chart initialized successfully');
    } catch (error) {
        console.error('Failed to initialize products chart:', error);
        if (productsChartElement.parentNode) {
            productsChartElement.parentNode.innerHTML = '<div class="alert alert-danger">Failed to load chart. Error: ' + error.message + '</div>';
        }
    }
}

/**
 * Toggle between Products Count and Revenue data
 */
function toggleProductsChart() {
    if (!window.productsChart) return;
    
    var chart = window.productsChart;
    var toggleButton = document.getElementById('toggleProductMetrics');
    
    if (chart.data.datasets[0].label === 'Sales Count') {
        // Switch to revenue
        chart.data.datasets[0].label = 'Revenue (in thousands)';
        chart.data.datasets[0].data = JSON.parse(document.getElementById('productsChart').getAttribute('data-revenue') || '[]');
        
        if (toggleButton) {
            toggleButton.innerText = 'Show Sales Count';
        }
    } else {
        // Switch to sales count
        chart.data.datasets[0].label = 'Sales Count';
        chart.data.datasets[0].data = JSON.parse(document.getElementById('productsChart').getAttribute('data-orders') || '[]');
        
        if (toggleButton) {
            toggleButton.innerText = 'Show Revenue';
        }
    }
    
    chart.update();
}

/**
 * Recreate products chart with a new type
 */
function recreateProductsChart(newType) {
    var productsChartElement = document.getElementById('productsChart');
    if (!productsChartElement || !window.productsChart) return;
    
    var currentData = window.productsChart.data;
    var currentOptions = window.productsChart.options;
    
    // Destroy current chart
    window.productsChart.destroy();
    
    // Update options based on new type
    if (newType === 'bar') {
        currentOptions.scales = {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        };
        currentOptions.plugins.legend.position = 'bottom';
    } else {
        delete currentOptions.scales;
        currentOptions.plugins.legend.position = 'right';
    }
    
    // Create new chart
    var ctx = productsChartElement.getContext('2d');
    window.productsChart = new Chart(ctx, {
        type: newType,
        data: currentData,
        options: currentOptions
    });
}

/**
 * Download chart data as CSV
 */
function downloadChartData(chartId, filename) {
    var chart = null;
    
    if (chartId === 'ordersChart' && window.ordersChart) {
        chart = window.ordersChart;
    } else if (chartId === 'productsChart' && window.productsChart) {
        chart = window.productsChart;
    }
    
    if (!chart) return;
    
    var labels = chart.data.labels;
    var datasets = chart.data.datasets;
    var csvContent = "data:text/csv;charset=utf-8,";
    
    // Add headers
    var headers = ["Labels"];
    datasets.forEach(function(ds) {
        headers.push(ds.label);
    });
    csvContent += headers.join(",") + "\n";
    
    // Add data rows
    for (var i = 0; i < labels.length; i++) {
        var row = [labels[i]];
        datasets.forEach(function(ds) {
            row.push(ds.data[i]);
        });
        csvContent += row.join(",") + "\n";
    }
    
    // Create download link
    var encodedUri = encodeURI(csvContent);
    var link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", filename || "chart-data.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Add event listeners for download buttons
document.addEventListener('DOMContentLoaded', function() {
    var downloadChartDataBtn = document.getElementById('downloadChartData');
    if (downloadChartDataBtn) {
        downloadChartDataBtn.addEventListener('click', function(e) {
            e.preventDefault();
            downloadChartData('ordersChart', 'orders-weekly-data.csv');
        });
    }
    
    var downloadProductDataBtn = document.getElementById('downloadProductData');
    if (downloadProductDataBtn) {
        downloadProductDataBtn.addEventListener('click', function(e) {
            e.preventDefault();
            downloadChartData('productsChart', 'top-products-data.csv');
        });
    }
});