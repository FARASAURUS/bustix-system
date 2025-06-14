<footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-bus"></i> busTix</h5>
                    <p>Sistem manajemen tiket bus terpercaya untuk perjalanan Anda.</p>
                </div>
                <div class="col-md-6">
                    <h6>Kontak</h6>
                    <p><i class="fas fa-phone"></i> +62 123 456 7890</p>
                    <p><i class="fas fa-envelope"></i> info@bustix.com</p>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> busTix. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
        
        // Confirm delete actions
        $('.btn-delete').click(function(e) {
            if (!confirm('Apakah Anda yakin ingin menghapus data ini?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
