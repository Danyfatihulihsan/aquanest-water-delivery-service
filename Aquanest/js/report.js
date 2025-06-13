/**
 * JavaScript untuk halaman laporan bisnis Aquanest
 */

document.addEventListener('DOMContentLoaded', function() {
    // Tentukan jenis laporan dari variabel global
    const reportType = window.reportData?.type || '';
    
    // Inisialisasi chart berdasarkan jenis laporan
    if (reportType === 'sales') {
        initSalesChart();
    }
    
    /**
     * Inisialisasi chart penjualan
     */
    function initSalesChart() {
        const salesData = window.reportData.salesData || [];
        const dailySalesCtx = document.getElementById('dailySalesChart');
        
        if (!dailySalesCtx || salesData.length === 0) return;
        
        const ctx = dailySalesCtx.getContext('2d');
        
        // Chart.js gradient background
        const gradientFill = ctx.createLinearGradient(0, 0, 0, 400);
        gradientFill.addColorStop(0, 'rgba(54, 162, 235, 0.3)');
        gradientFill.addColorStop(1, 'rgba(54, 162, 235, 0.0)');
        
        const dailySalesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: salesData.map(item => item.date),
                datasets: [
                    {
                        label: 'Total Penjualan (Rp)',
                        data: salesData.map(item => item.total),
                        backgroundColor: gradientFill,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        yAxisID: 'y',
                        barThickness: 'flex',
                        borderRadius: 4,
                    },
                    {
                        label: 'Jumlah Pesanan',
                        data: salesData.map(item => item.count),
                        type: 'line',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 3,
                        pointRadius: 4,
                        pointBackgroundColor: 'rgba(255, 99, 132, 1)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverRadius: 6,
                        fill: false,
                        tension: 0.2,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            font: {
                                family: "'Poppins', sans-serif",
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleFont: {
                            family: "'Poppins', sans-serif",
                            size: 13
                        },
                        bodyFont: {
                            family: "'Poppins', sans-serif",
                            size: 12
                        },
                        padding: 12,
                        cornerRadius: 8,
                        usePointStyle: true,
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Penjualan (Rp)',
                            font: {
                                family: "'Poppins', sans-serif",
                                size: 12
                            }
                        },
                        beginAtZero: true,
                        ticks: {
                            font: {
                                family: "'Poppins', sans-serif",
                                size: 11
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Jumlah Pesanan',
                            font: {
                                family: "'Poppins', sans-serif",
                                size: 12
                            }
                        },
                        grid: {
                            drawOnChartArea: false
                        },
                        beginAtZero: true,
                        ticks: {
                            font: {
                                family: "'Poppins', sans-serif",
                                size: 11
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                family: "'Poppins', sans-serif",
                                size: 11
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.03)'
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuart'
                }
            }
        });
    }
    
    // Fungsi untuk mencetak laporan
    window.printReport = function() {
        window.print();
    };
});