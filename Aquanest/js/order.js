// JavaScript untuk halaman Order - Aquanest

document.addEventListener('DOMContentLoaded', function() {
    // Inisialisasi elemen-elemen
    const productSelect = document.getElementById('product_id');
    const quantityInput = document.getElementById('quantity');
    const subtotalText = document.getElementById('subtotal');
    
    // Fungsi untuk memperbarui subtotal
    function updateSubtotal() {
        if (productSelect && productSelect.selectedIndex > 0) {
            const price = parseFloat(productSelect.options[productSelect.selectedIndex].dataset.price);
            const quantity = parseInt(quantityInput.value);
            const subtotal = price * quantity;
            
            // Format sebagai Rupiah
            const formatter = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            });
            
            subtotalText.textContent = formatter.format(subtotal);
        } else {
            subtotalText.textContent = 'Rp 0';
        }
    }
    
    // Update ketika produk atau jumlah berubah
    if (productSelect) {
        productSelect.addEventListener('change', function() {
            const option = productSelect.options[productSelect.selectedIndex];
            if (option && option.dataset.max) {
                quantityInput.max = option.dataset.max;
                if (parseInt(quantityInput.value) > parseInt(option.dataset.max)) {
                    quantityInput.value = option.dataset.max;
                }
            }
            updateSubtotal();
        });
    }
    
    if (quantityInput) {
        quantityInput.addEventListener('change', updateSubtotal);
        quantityInput.addEventListener('input', updateSubtotal);
    }
    
    // Kalkulasi awal
    updateSubtotal();
    
    // Konfirmasi penghapusan item keranjang
    const deleteButtons = document.querySelectorAll('.btn-danger');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Apakah Anda yakin ingin menghapus produk ini?')) {
                e.preventDefault();
            }
        });
    });
    
    // Form validasi
    const checkoutForm = document.querySelector('.checkout-form form');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(event) {
            const name = document.getElementById('name').value;
            const phone = document.getElementById('phone').value;
            const address = document.getElementById('address').value;
            const terms = document.getElementById('terms').checked;
            
            if (!name || !phone || !address) {
                event.preventDefault();
                alert('Mohon lengkapi semua bidang yang wajib diisi!');
            } else if (!terms) {
                event.preventDefault();
                alert('Anda harus menyetujui syarat dan ketentuan untuk melanjutkan.');
            }
        });
    }
    
    // Fungsi untuk SweetAlert2 jika flash message ada
    // Catatan: Kode ini akan diganti secara dinamis oleh PHP
    // Silakan tambahkan kembali di order.php jika perlu
});