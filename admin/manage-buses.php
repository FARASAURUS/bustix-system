<?php
$page_title = "Kelola Bus";
include '../includes/header.php';
requireLogin();
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_schedule'])) {
        $bus_id = (int)$_POST['bus_id'];
        $route_id = (int)$_POST['route_id'];
        $departure_time = $_POST['departure_time'];
        $arrival_time = $_POST['arrival_time'];
        $price = (float)$_POST['price'];
        $schedule_date = $_POST['schedule_date'];
        
        // Get bus capacity for available seats
        $capacity_query = "SELECT capacity FROM buses WHERE id = ?";
        $stmt = $db->prepare($capacity_query);
        $stmt->execute([$bus_id]);
        $bus = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bus) {
            $query = "INSERT INTO bus_schedules (bus_id, route_id, departure_time, arrival_time, price, available_seats, schedule_date) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$bus_id, $route_id, $departure_time, $arrival_time, $price, $bus['capacity'], $schedule_date])) {
                $success = "Jadwal bus berhasil ditambahkan!";
            } else {
                $error = "Gagal menambahkan jadwal bus!";
            }
        } else {
            $error = "Bus tidak ditemukan!";
        }
    }
}

// Get buses
$buses_query = "SELECT * FROM buses ORDER BY bus_number";
$stmt = $db->prepare($buses_query);
$stmt->execute();
$buses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get routes
$routes_query = "SELECT * FROM bus_routes ORDER BY route_name";
$stmt = $db->prepare($routes_query);
$stmt->execute();
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get schedules
$schedules_query = "SELECT bs.*, b.bus_number, b.bus_type, br.route_name, br.origin, br.destination
                    FROM bus_schedules bs
                    JOIN buses b ON bs.bus_id = b.id
                    JOIN bus_routes br ON bs.route_id = br.id
                    ORDER BY bs.schedule_date DESC, bs.departure_time";
$stmt = $db->prepare($schedules_query);
$stmt->execute();
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                        <a class="nav-link active" href="manage-buses.php">
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-bus"></i> Kelola Bus & Jadwal</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                    <i class="fas fa-plus"></i> Tambah Jadwal
                </button>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Bus List -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-list"></i> Daftar Bus</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>No. Bus</th>
                                            <th>Tipe</th>
                                            <th>Kapasitas</th>
                                            <th>Fasilitas</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($buses as $bus): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($bus['bus_number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($bus['bus_type']); ?></td>
                                            <td><?php echo $bus['capacity']; ?> kursi</td>
                                            <td><?php echo htmlspecialchars($bus['facilities']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $bus['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($bus['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Schedules List -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-calendar"></i> Jadwal Bus</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Bus</th>
                                    <th>Rute</th>
                                    <th>Waktu</th>
                                    <th>Harga</th>
                                    <th>Kursi Tersedia</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedules as $schedule): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($schedule['schedule_date'])); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($schedule['bus_number']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($schedule['bus_type']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($schedule['route_name']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($schedule['origin'] . ' - ' . $schedule['destination']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo date('H:i', strtotime($schedule['departure_time'])); ?> - 
                                        <?php echo date('H:i', strtotime($schedule['arrival_time'])); ?>
                                    </td>
                                    <td>Rp <?php echo number_format($schedule['price'], 0, ',', '.'); ?></td>
                                    <td><?php echo $schedule['available_seats']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $schedule['status'] === 'active' ? 'success' : 
                                                ($schedule['status'] === 'cancelled' ? 'danger' : 'secondary'); 
                                        ?>">
                                            <?php echo ucfirst($schedule['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary">Edit</button>
                                        <button class="btn btn-sm btn-outline-danger btn-delete">Hapus</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Schedule Modal -->
<div class="modal fade" id="addScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Jadwal Bus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bus</label>
                            <select class="form-select" name="bus_id" required>
                                <option value="">Pilih Bus</option>
                                <?php foreach ($buses as $bus): ?>
                                    <?php if ($bus['status'] === 'active'): ?>
                                    <option value="<?php echo $bus['id']; ?>">
                                        <?php echo htmlspecialchars($bus['bus_number'] . ' - ' . $bus['bus_type']); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rute</label>
                            <select class="form-select" name="route_id" required>
                                <option value="">Pilih Rute</option>
                                <?php foreach ($routes as $route): ?>
                                <option value="<?php echo $route['id']; ?>">
                                    <?php echo htmlspecialchars($route['route_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tanggal</label>
                            <input type="date" class="form-control" name="schedule_date" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Waktu Berangkat</label>
                            <input type="time" class="form-control" name="departure_time" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Waktu Tiba</label>
                            <input type="time" class="form-control" name="arrival_time" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Harga Tiket</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" name="price" min="0" step="1000" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add_schedule" class="btn btn-primary">Tambah Jadwal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
