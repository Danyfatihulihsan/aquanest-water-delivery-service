console.log("Chart diagnostic script running...");

// Function to run when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM fully loaded");
    
    // Check if Chart.js is available
    console.log("Chart.js available:", typeof Chart !== 'undefined');
    
    // Check for chart containers and canvas elements
    const orderChartContainer = document.querySelector('.chart-container');
    const orderChartEl = document.getElementById('ordersChart');
    const productChartEl = document.getElementById('productsChart');
    
    console.log("Order chart container exists:", !!orderChartContainer);
    console.log("Container height:", orderChartContainer ? window.getComputedStyle(orderChartContainer).height : 'N/A');
    console.log("Order chart canvas exists:", !!orderChartEl);
    console.log("Product chart canvas exists:", !!productChartEl);
    
    // Check if data attributes exist
    if (orderChartEl) {
        console.log("Order chart data attributes:");
        console.log("- data-labels:", orderChartEl.getAttribute('data-labels'));
        console.log("- data-orders:", orderChartEl.getAttribute('data-orders'));
        console.log("- data-revenue:", orderChartEl.getAttribute('data-revenue'));
    }
    
    if (productChartEl) {
        console.log("Product chart data attributes:");
        console.log("- data-labels:", productChartEl.getAttribute('data-labels'));
        console.log("- data-orders:", productChartEl.getAttribute('data-orders'));
        console.log("- data-revenue:", productChartEl.getAttribute('data-revenue'));
    }
    
    // Try creating minimal charts with hardcoded data
    console.log("Attempting to create minimal charts...");
    
    try {
        if (typeof Chart !== 'undefined' && orderChartEl) {
            new Chart(orderChartEl.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Test Data',
                        data: [12, 19, 3, 5, 2, 3, 7],
                        backgroundColor: 'rgba(48, 85, 211, 0.8)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            console.log("Minimal order chart created successfully");
        }
        
        if (typeof Chart !== 'undefined' && productChartEl) {
            new Chart(productChartEl.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Red', 'Blue', 'Yellow', 'Green', 'Purple'],
                    datasets: [{
                        label: 'Test Data',
                        data: [12, 19, 3, 5, 2],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 206, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            console.log("Minimal product chart created successfully");
        }
    } catch (error) {
        console.error("Error creating minimal charts:", error);
    }
});