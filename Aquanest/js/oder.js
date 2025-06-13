/**
 * Order Page JavaScript
 */
document.addEventListener('DOMContentLoaded', function() {
    // Calculate subtotal when product or quantity changes
    const productSelect = document.getElementById('product_id');
    const quantityInput = document.getElementById('quantity');
    const subtotalDisplay = document.getElementById('subtotal');
    
    function calculateSubtotal() {
        if (productSelect.value) {
            const selectedOption = productSelect.options[productSelect.selectedIndex];
            const price = parseFloat(selectedOption.dataset.price);
            const quantity = parseInt(quantityInput.value);
            
            if (!isNaN(price) && !isNaN(quantity)) {
                const subtotal = price * quantity;
                subtotalDisplay.textContent = formatRupiah(subtotal);
                
                // Set max quantity based on stock
                const maxStock = parseInt(selectedOption.dataset.max);
                quantityInput.max = maxStock;
                
                // Adjust quantity if it exceeds max stock
                if (quantity > maxStock) {
                    quantityInput.value = maxStock;
                    calculateSubtotal();
                }
            }
        } else {
            subtotalDisplay.textContent = 'Rp 0';
        }
    }
    
    if (productSelect && quantityInput) {
        productSelect.addEventListener('change', calculateSubtotal);
        quantityInput.addEventListener('input', calculateSubtotal);
        
        // Calculate initial subtotal
        calculateSubtotal();
    }
    
    // Format number to Rupiah
    function formatRupiah(number) {
        return 'Rp ' + number.toFixed(0).replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
    }
    
    // Payment method selection
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const paymentDetails = document.querySelectorAll('.payment-details');
    
    if (paymentMethods.length > 0) {
        // Hide all payment details initially except the default
        paymentDetails.forEach(detail => {
            if (detail.id !== 'credit_card_details') {
                detail.style.display = 'none';
            }
        });
        
        // Handle payment method change
        paymentMethods.forEach(method => {
            method.addEventListener('change', function() {
                // Hide all payment details
                paymentDetails.forEach(detail => {
                    detail.style.display = 'none';
                });
                
                // Show the selected payment details
                const selectedDetail = document.getElementById(this.value + '_details');
                if (selectedDetail) {
                    selectedDetail.style.display = 'block';
                }
                
                // Apply selected style to payment cards
                document.querySelectorAll('.payment-card').forEach(card => {
                    card.classList.remove('selected');
                });
                
                this.nextElementSibling.classList.add('selected');
            });
        });
        
        // Apply selected style to default payment method
        const checkedMethod = document.querySelector('input[name="payment_method"]:checked');
        if (checkedMethod) {
            checkedMethod.nextElementSibling.classList.add('selected');
        }
    }
    
    // Form validation for checkout
    const checkoutForm = document.querySelector('form[name="checkout"]');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(event) {
            const requiredFields = checkoutForm.querySelectorAll('[required]');
            let valid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!valid) {
                event.preventDefault();
                showErrorAlert('Mohon lengkapi semua field yang wajib diisi.');
            }
        });
    }
    
    // Function to show error alert
    function showErrorAlert(message) {
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: message,
        });
    }
});