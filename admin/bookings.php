<?php
$page_title = "Kelola Booking";
include '../includes/header.php';
requireLogin();
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle booking status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_status'])) {
        $booking_id = (int)$_POST['booking_id'];
        $new_status = $_POST['booking_status'];
        
        $query = "UPDATE bookings SET booking_status = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$new_status, $booking_id])) {
            $success = "Status booking berhasil diupdate!";
        } else {
            $error = "Gagal mengupdate status booking!";
        }
    }
}

// Get bookings with filters
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$where_conditions = [];
$params = [];

if (!empty($filter_status)) {
    $where_conditions[] = "b.booking_status = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $where_conditions[] = "(b.booking_code LIKE ? OR u.full_name LIKE ? OR b.passenger_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$query = "SELECT b.*, u.full_name as user_name, u.email, 
                 bs.departure_time, bs.arrival_time, bs.schedule_date,
                 br.route_name, br.origin, br.destination,
                 bus.bus_number, bus.bus_type
          FROM bookings b
          JOIN users u ON b.user_id = u.id
          JOIN bus_schedules bs ON b.schedule_id = bs.id
          JOIN bus_routes br ON bs.route_id = br.id
          JOIN buses bus ON bs.bus_id = bus.id
          $where_clause
          ORDER BY b.booking_date DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2">
            <div class="sidebar p-3">
                <h6 class="text-muted">MENU ADMIN</h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage-buses.php">
                            <i class="fas fa-bus"></i> Kelola Bus
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="bookings.php">
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
            <h2><i class="fas fa-ticket-alt"></i> Kelola Booking</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">Semua Status</option>
                                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Cari</label>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Kode booking, nama penumpang..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Bookings Table -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> Daftar Booking (<?php echo count($bookings); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (count($bookings) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Kode Booking</th>
                                        <th>Penumpang</th>
                                        <th>Rute & Jadwal</th>
                                        <th>Bus</th>
                                        <th>Kursi</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($booking['booking_code']); ?></strong><br>
                                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($booking['booking_date'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($booking['passenger_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($booking['user_name']); ?></small><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($booking['passenger_phone']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($booking['route_name']); ?><br>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y', strtotime($booking['schedule_date'])); ?> | 
                                                <?php echo date('H:i', strtotime($booking['departure_time'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($booking['bus_number']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($booking['bus_type']); ?></small>
                                        </td>
                                        <td><?php echo $booking['total_seats']; ?> kursi</td>
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
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#updateStatusModal<?php echo $booking['id']; ?>">
                                                Update
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <!-- Update Status Modal -->
                                    <div class="modal fade" id="updateStatusModal<?php echo $booking['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Update Status Booking</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Kode Booking</label>
                                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($booking['booking_code']); ?>" readonly>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Penumpang</label>
                                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($booking['passenger_name']); ?>" readonly>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Status Saat Ini</label>
                                                            <input type="text" class="form-control" value="<?php echo ucfirst($booking['booking_status']); ?>" readonly>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Status Baru</label>
                                                            <select class="form-select" name="booking_status" required>
                                                                <option value="pending" <?php echo $booking['booking_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="confirmed" <?php echo $booking['booking_status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                                <option value="cancelled" <?php echo $booking['booking_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                                <option value="completed" <?php echo $booking['booking_status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                            <h5>Tidak ada booking ditemukan</h5>
                            <p class="text-muted">Booking akan muncul di sini setelah ada penumpang yang memesan tiket.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
