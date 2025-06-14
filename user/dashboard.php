<?php
$page_title = "Dashboard User";
include '../includes/header.php';
requireLogin();

if (isAdmin()) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get user bookings
$query = "SELECT b.*, bs.departure_time, bs.arrival_time, bs.schedule_date, 
                 br.route_name, br.origin, br.destination, bus.bus_number, bus.bus_type
          FROM bookings b
          JOIN bus_schedules bs ON b.schedule_id = bs.id
          JOIN bus_routes br ON bs.route_id = br.id
          JOIN buses bus ON bs.bus_id = bus.id
          WHERE b.user_id = ?
          ORDER BY b.booking_date DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get booking statistics
$stats_query = "SELECT 
                    COUNT(*) as total_bookings,
                    SUM(CASE WHEN booking_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                    SUM(CASE WHEN booking_status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
                    SUM(CASE WHEN booking_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings
                FROM bookings WHERE user_id = ?";
$stmt = $db->prepare($stats_query);
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h2><i class="fas fa-tachometer-alt"></i> Dashboard User</h2>
            <p class="text-muted">Selamat datang, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $stats['total_bookings']; ?></h4>
                            <p class="mb-0">Total Booking</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-ticket-alt fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $stats['confirmed_bookings']; ?></h4>
                            <p class="mb-0">Terkonfirmasi</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $stats['pending_bookings']; ?></h4>
                            <p class="mb-0">Menunggu</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $stats['cancelled_bookings']; ?></h4>
                            <p class="mb-0">Dibatalkan</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-times-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-bolt"></i> Aksi Cepat</h5>
                </div>
                <div class="card-body">
                    <a href="booking.php" class="btn btn-primary me-2">
                        <i class="fas fa-plus"></i> Pesan Tiket Baru
                    </a>
                    <a href="bookings.php" class="btn btn-outline-primary">
                        <i class="fas fa-list"></i> Lihat Semua Booking
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Bookings -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Booking Terbaru</h5>
                </div>
                <div class="card-body">
                    <?php if (count($bookings) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Kode Booking</th>
                                        <th>Rute</th>
                                        <th>Tanggal</th>
                                        <th>Bus</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($booking['booking_code']); ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($booking['origin'] . ' - ' . $booking['destination']); ?>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($booking['schedule_date'])); ?><br>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($booking['departure_time'])); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($booking['bus_number']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($booking['bus_type']); ?></small>
                                        </td>
                                        <td>Rp <?php echo number_format($booking['total_amount'], 0, ',', '.'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $booking['booking_status'] === 'confirmed' ? 'success' : 
                                                    ($booking['booking_status'] === 'pending' ? 'warning' : 
                                                    ($booking['booking_status'] === 'cancelled' ? 'danger' : 'secondary')); 
                                            ?>">
                                                <?php echo ucfirst($booking['booking_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="booking-detail.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                Detail
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                            <h5>Belum ada booking</h5>
                            <p class="text-muted">Mulai perjalanan Anda dengan memesan tiket pertama!</p>
                            <a href="booking.php" class="btn btn-primary">Pesan Tiket Sekarang</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
