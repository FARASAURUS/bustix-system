<?php
$page_title = "Beranda";
include 'includes/header.php';

$database = new Database();
$db = $database->getConnection();

// Get available routes
$query = "SELECT * FROM bus_routes ORDER BY route_name";
$stmt = $db->prepare($query);
$stmt->execute();
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="hero-section">
    <div class="container text-center">
        <h1 class="display-4 mb-4">Selamat Datang di busTix</h1>
        <p class="lead mb-4">Sistem manajemen tiket bus yang mudah, cepat, dan terpercaya</p>
        <?php if (!isLoggedIn()): ?>
            <a href="auth/register.php" class="btn btn-light btn-lg me-3">Daftar Sekarang</a>
            <a href="auth/login.php" class="btn btn-outline-light btn-lg">Login</a>
        <?php else: ?>
            <a href="user/booking.php" class="btn btn-light btn-lg">Pesan Tiket Sekarang</a>
        <?php endif; ?>
    </div>
</div>

<div class="container my-5">
    <div class="row">
        <div class="col-lg-8">
            <h2 class="mb-4">Rute Populer</h2>
            <div class="row">
                <?php foreach ($routes as $route): ?>
                <div class="col-md-6 mb-4">
                    <div class="card card-hover h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-route text-primary"></i> 
                                <?php echo htmlspecialchars($route['route_name']); ?>
                            </h5>
                            <p class="card-text">
                                <strong>Dari:</strong> <?php echo htmlspecialchars($route['origin']); ?><br>
                                <strong>Ke:</strong> <?php echo htmlspecialchars($route['destination']); ?><br>
                                <strong>Jarak:</strong> <?php echo $route['distance_km']; ?> km<br>
                                <strong>Durasi:</strong> <?php echo $route['duration_hours']; ?> jam
                            </p>
                            <?php if (isLoggedIn() && !isAdmin()): ?>
                                <a href="user/booking.php?route=<?php echo $route['id']; ?>" class="btn btn-primary">
                                    Pesan Tiket
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle"></i> Informasi</h5>
                </div>
                <div class="card-body">
                    <h6>Keunggulan busTix:</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success"></i> Pemesanan online 24/7</li>
                        <li><i class="fas fa-check text-success"></i> Pembayaran yang aman</li>
                        <li><i class="fas fa-check text-success"></i> Konfirmasi instan</li>
                        <li><i class="fas fa-check text-success"></i> Customer service responsif</li>
                        <li><i class="fas fa-check text-success"></i> Berbagai pilihan bus</li>
                    </ul>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5><i class="fas fa-phone"></i> Bantuan</h5>
                </div>
                <div class="card-body">
                    <p>Butuh bantuan? Hubungi kami:</p>
                    <p><strong>Call Center:</strong><br>+62 123 456 7890</p>
                    <p><strong>Email:</strong><br>support@bustix.com</p>
                    <p><strong>Jam Operasional:</strong><br>24 jam setiap hari</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
