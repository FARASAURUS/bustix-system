<?php
$page_title = "Dashboard Admin";
include '../includes/header.php';
requireLogin();
requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Get statistics
$stats = [];

// Total bookings
$query = "SELECT COUNT(*) as total FROM bookings";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total revenue
$query = "SELECT SUM(total_amount) as total FROM bookings WHERE booking_status = 'confirmed'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Pending bookings
$query = "SELECT COUNT(*) as total FROM bookings WHERE booking_status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['pending_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Active buses
$query = "SELECT COUNT(*) as total FROM buses WHERE status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['active_buses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Recent bookings
$query = "SELECT b.*, u.full_name, br.route_name, bus.bus_number
          FROM bookings b
          JOIN users u ON b.user_id = u.id
          JOIN bus_schedules bs ON b.schedule_id = bs.id
          JOIN bus_routes br ON bs.route_id = br.id
          JOIN buses bus ON bs.bus_id = bus.id
          ORDER BY b.booking_date DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2">
            <div class="sidebar p-3">
                <h6 class="text-muted">MENU ADMIN</h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage-buses.php">
                            <i class="fas fa-bus"></i> Kelola Bus
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="bookings.php">
                            <i class="fas fa-ticket-alt"></i> Kelola Booking
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar"></i> Laporan
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <h2><i class="fas fa-tachometer-alt"></i> Dashboard Admin</h2>
            <p class="text-muted">Selamat datang di panel admin busTix</p>
            
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
                                    <h4>Rp <?php echo number_format($stats['total_revenue'], 0, ',', '.'); ?></h4>
                                    <p class="mb-0">Total Pendapatan</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-money-bill-wave fa-2x"></i>
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
                                    <p class="mb-0">Booking Pending</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $stats['active_buses']; ?></h4>
                                    <p class="mb-0">Bus Aktif</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-bus fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Bookings -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Booking Terbaru</h5>
                </div>
                <div class="card-body">
                    <?php if (count($recent_bookings) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Kode Booking</th>
                                        <th>Penumpang</th>
                                        <th>Rute</th>
                                        <th>Bus</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Tanggal</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_bookings as $booking): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($booking['booking_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($booking['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['route_name']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['bus_number']); ?></td>
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
                                        <td><?php echo date('d/m/Y H:i', strtotime($booking['booking_date'])); ?></td>
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
                        <div class="text-center mt-3">
                            <a href="bookings.php" class="btn btn-primary">Lihat Semua Booking</a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                            <h5>Belum ada booking</h5>
                            <p class="text-muted">Booking akan muncul di sini setelah ada penumpang yang memesan tiket.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '..includes/footer.php'; ?>
