<?php
$page_title = "Laporan";
include '../includes/header.php';
requireLogin();
requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Get date range from query params
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Revenue Report
$revenue_query = "SELECT 
                    DATE(booking_date) as date,
                    COUNT(*) as total_bookings,
                    SUM(total_amount) as total_revenue,
                    SUM(CASE WHEN booking_status = 'confirmed' THEN total_amount ELSE 0 END) as confirmed_revenue
                  FROM bookings 
                  WHERE DATE(booking_date) BETWEEN ? AND ?
                  GROUP BY DATE(booking_date)
                  ORDER BY date DESC";
$stmt = $db->prepare($revenue_query);
$stmt->execute([$start_date, $end_date]);
$revenue_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Route Performance
$route_query = "SELECT 
                    br.route_name,
                    br.origin,
                    br.destination,
                    COUNT(b.id) as total_bookings,
                    SUM(b.total_amount) as total_revenue,
                    AVG(b.total_amount) as avg_revenue
                FROM bookings b
                JOIN bus_schedules bs ON b.schedule_id = bs.id
                JOIN bus_routes br ON bs.route_id = br.id
                WHERE DATE(b.booking_date) BETWEEN ? AND ?
                GROUP BY br.id
                ORDER BY total_revenue DESC";
$stmt = $db->prepare($route_query);
$stmt->execute([$start_date, $end_date]);
$route_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Bus Performance
$bus_query = "SELECT 
                buses.bus_number,
                buses.bus_type,
                COUNT(b.id) as total_bookings,
                SUM(b.total_amount) as total_revenue,
                AVG(bs.available_seats) as avg_available_seats
              FROM bookings b
              JOIN bus_schedules bs ON b.schedule_id = bs.id
              JOIN buses ON bs.bus_id = buses.id
              WHERE DATE(b.booking_date) BETWEEN ? AND ?
              GROUP BY buses.id
              ORDER BY total_revenue DESC";
$stmt = $db->prepare($bus_query);
$stmt->execute([$start_date, $end_date]);
$bus_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary Statistics
$summary_query = "SELECT 
                    COUNT(*) as total_bookings,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_booking_value,
                    SUM(CASE WHEN booking_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                    SUM(CASE WHEN booking_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings
                  FROM bookings 
                  WHERE DATE(booking_date) BETWEEN ? AND ?";
$stmt = $db->prepare($summary_query);
$stmt->execute([$start_date, $end_date]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);
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
                        <a class="nav-link" href="bookings.php">
                            <i class="fas fa-ticket-alt"></i> Kelola Booking
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="reports.php">
                            <i class="fas fa-chart-bar"></i> Laporan
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <h2><i class="fas fa-chart-bar"></i> Laporan & Analisis</h2>
            
            <!-- Date Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Tanggal Mulai</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Tanggal Akhir</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block">
                                <i class="fas fa-filter"></i> Filter Laporan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h4><?php echo $summary['total_bookings']; ?></h4>
                            <p class="mb-0">Total Booking</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h4>Rp <?php echo number_format($summary['total_revenue'], 0, ',', '.'); ?></h4>
                            <p class="mb-0">Total Pendapatan</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h4>Rp <?php echo number_format($summary['avg_booking_value'], 0, ',', '.'); ?></h4>
                            <p class="mb-0">Rata-rata Booking</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h4><?php echo round(($summary['confirmed_bookings'] / max($summary['total_bookings'], 1)) * 100, 1); ?>%</h4>
                            <p class="mb-0">Tingkat Konfirmasi</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Revenue Chart -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-line"></i> Pendapatan Harian</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Total Booking</th>
                                            <th>Pendapatan Total</th>
                                            <th>Pendapatan Terkonfirmasi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($revenue_data as $data): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($data['date'])); ?></td>
                                            <td><?php echo $data['total_bookings']; ?></td>
                                            <td>Rp <?php echo number_format($data['total_revenue'], 0, ',', '.'); ?></td>
                                            <td>Rp <?php echo number_format($data['confirmed_revenue'], 0, ',', '.'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Route Performance -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-route"></i> Performa Rute</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Rute</th>
                                            <th>Booking</th>
                                            <th>Pendapatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($route_performance as $route): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($route['route_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($route['origin'] . ' - ' . $route['destination']); ?></small>
                                            </td>
                                            <td><?php echo $route['total_bookings']; ?></td>
                                            <td>Rp <?php echo number_format($route['total_revenue'], 0, ',', '.'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bus Performance -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-bus"></i> Performa Bus</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Bus</th>
                                            <th>Booking</th>
                                            <th>Pendapatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bus_performance as $bus): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($bus['bus_number']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($bus['bus_type']); ?></small>
                                            </td>
                                            <td><?php echo $bus['total_bookings']; ?></td>
                                            <td>Rp <?php echo number_format($bus['total_revenue'], 0, ',', '.'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Export & Backup Options -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-download"></i> Export & Backup Data</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Export Laporan:</h6>
                            <p>Export laporan dalam berbagai format:</p>
                            <button class="btn btn-success me-2">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </button>
                            <button class="btn btn-danger me-2">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </button>
                            <button class="btn btn-info">
                                <i class="fas fa-print"></i> Print Laporan
                            </button>
                        </div>
                        <div class="col-md-6">
                            <h6>Backup Database:</h6>
                            <p>Backup seluruh data sistem ke file SQL:</p>
                            <a href="backup.php" class="btn btn-primary me-2">
                                <i class="fas fa-database"></i> Backup Full Database
                            </a>
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-outline-primary dropdown-toggle" type="button" id="backupDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-table"></i> Backup Tabel
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="backupDropdown">
                                    <li><a class="dropdown-item" href="backup.php?table=bookings">Data Booking</a></li>
                                    <li><a class="dropdown-item" href="backup.php?table=users">Data User</a></li>
                                    <li><a class="dropdown-item" href="backup.php?table=buses">Data Bus</a></li>
                                    <li><a class="dropdown-item" href="backup.php?table=bus_schedules">Data Jadwal</a></li>
                                    <li><a class="dropdown-item" href="backup.php?table=payment_transactions">Data Transaksi</a></li>
                                </ul>
                            </div>
                            <a href="restore.php" class="btn btn-outline-warning">
                                <i class="fas fa-undo"></i> Restore Database
                            </a>
                            <div class="form-text mt-2">
                                <i class="fas fa-info-circle"></i> Backup terakhir: 
                                <?php
                                $backup_file = '../backups/last_backup_time.txt';
                                if (file_exists($backup_file)) {
                                    echo date('d/m/Y H:i:s', filemtime($backup_file));
                                } else {
                                    echo 'Belum pernah';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
