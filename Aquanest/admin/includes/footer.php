<?php
// File: includes/footer.php

/**
 * Modern footer for Aquanest Admin Panel
 */
?>
        <!-- Footer -->
        <footer class="footer mt-auto py-3">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-0">&copy; <?php echo date('Y'); ?> Aquanest. All rights reserved.</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="mb-0">Admin Panel v1.0</p>
                    </div>
                </div>
            </div>
        </footer>
    </div> 
     <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    
    <?php if ($page_title == 'Dashboard'): ?>
    <!-- Dashboard Charts Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // All chart code here
    });
    </script>
    <?php endif; ?>
    
    <!-- Custom JavaScript -->
    <script>
    // Flash message handling with SweetAlert2
    <?php if (isset($_SESSION['flash_message']) && isset($_SESSION['flash_type'])): ?>
        Swal.fire({
            icon: '<?php echo $_SESSION['flash_type'] === 'success' ? 'success' : ($_SESSION['flash_type'] === 'danger' ? 'error' : $_SESSION['flash_type']); ?>',
            title: '<?php echo $_SESSION['flash_title'] ?? ($_SESSION['flash_type'] === 'success' ? 'Berhasil!' : 'Perhatian!'); ?>',
            text: '<?php echo $_SESSION['flash_message']; ?>',
            timer: 3000,
            timerProgressBar: true
        });
        <?php 
        // Clear flash message
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        unset($_SESSION['flash_title']);
        ?>
    <?php endif; ?>
    
    // Confirm delete functionality
    document.querySelectorAll('.confirm-delete').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.getAttribute('href');
            const itemName = this.getAttribute('data-item-name') || 'item';
            
            Swal.fire({
                title: 'Konfirmasi Hapus',
                text: `Apakah Anda yakin ingin menghapus ${itemName} ini?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        });
    });
    
    // Add any additional common JavaScript here
    </script>
    
    <?php if (isset($page_specific_js)): ?>
    <!-- Page Specific JavaScript -->
    <script src="<?php echo $page_specific_js; ?>"></script>
    <?php endif; ?>
</body>
</html>