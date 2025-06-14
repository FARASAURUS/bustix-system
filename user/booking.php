<?php
$page_title = "Pesan Tiket";
include '../includes/header.php';
requireLogin();

if (isAdmin()) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Get routes for step 1
$routes_query = "SELECT * FROM bus_routes ORDER BY route_name";
$stmt = $db->prepare($routes_query);
$stmt->execute();
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['search_schedule'])) {
        $route_id = (int)$_POST['route_id'];
        $travel_date = $_POST['travel_date'];
        
        if (empty($route_id) || empty($travel_date)) {
            $error = "Pilih rute dan tanggal perjalanan!";
        } elseif (strtotime($travel_date) < strtotime(date('Y-m-d'))) {
            $error = "Tanggal perjalanan tidak boleh di masa lalu!";
        } else {
            header("Location: booking.php?step=2&route_id=$route_id&date=$travel_date");
            exit();
        }
    } elseif (isset($_POST['book_ticket'])) {
        $schedule_id = (int)$_POST['schedule_id'];
        $passenger_name = trim($_POST['passenger_name']);
        $passenger_phone = trim($_POST['passenger_phone']);
        $seat_count = (int)$_POST['seat_count'];
        
        if (empty($passenger_name) || empty($passenger_phone) || $seat_count < 1) {
            $error = "Semua field harus diisi dengan benar!";
        } else {
            // Use stored procedure to create booking
            $stmt = $db->prepare("CALL buatBooking(?, ?, ?, ?, ?, ?, @booking_code, @result)");
            $seat_numbers = implode(',', range(1, $seat_count)); // Simplified seat assignment
            
            try {
                $stmt->execute([
                    $_SESSION['user_id'],
                    $schedule_id,
                    $passenger_name,
                    $passenger_phone,
                    $seat_numbers,
                    $seat_count
                ]);
                
                // Get the results
                $result_stmt = $db->query("SELECT @booking_code as booking_code, @result as result");
                $result = $result_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (strpos($result['result'], 'SUCCESS') !== false) {
                    $success = "Booking berhasil dibuat! Kode booking: " . $result['booking_code'];
                    header("Location: payment.php?booking_code=" . $result['booking_code']);
                    exit();
                } else {
                    $error = str_replace('ERROR: ', '', $result['result']);
                }
            } catch (Exception $e) {
                $error = "Terjadi kesalahan: " . $e->getMessage();
            }
        }
    }
}

// Get schedules for step 2
if ($step == 2 && isset($_GET['route_id']) && isset($_GET['date'])) {
    $route_id = (int)$_GET['route_id'];
    $travel_date = $_GET['date'];
    
    $schedules_query = "SELECT bs.*, br.route_name, br.origin, br.destination, 
                               b.bus_number, b.bus_type, b.facilities
                        FROM bus_schedules bs
                        JOIN bus_routes br ON bs.route_id = br.id
                        JOIN buses b ON bs.bus_id = b.id
                        WHERE bs.route_id = ? AND bs.schedule_date = ? AND bs.status = 'active'
                        ORDER BY bs.departure_time";
    $stmt = $db->prepare($schedules_query);
    $stmt->execute([$route_id, $travel_date]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h2><i class="fas fa-ticket-alt"></i> Pesan Tiket Bus</h2>
            
            <!-- Progress Steps -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="progress" style="height: 3px;">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo ($step * 50); ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <span class="badge <?php echo $step >= 1 ? 'bg-primary' : 'bg-secondary'; ?>">1. Pilih Rute</span>
                        <span class="badge <?php echo $step >= 2 ? 'bg-primary' : 'bg-secondary'; ?>">2. Pilih Jadwal</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($step == 1): ?>
    <!-- Step 1: Select Route and Date -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-route"></i> Pilih Rute dan Tanggal</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="route_id" class="form-label">Rute Perjalanan</label>
                                <select class="form-select" id="route_id" name="route_id" required>
                                    <option value="">Pilih Rute</option>
                                    <?php foreach ($routes as $route): ?>
                                    <option value="<?php echo $route['id']; ?>" 
                                            <?php echo (isset($_GET['route']) && $_GET['route'] == $route['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($route['route_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="travel_date" class="form-label">Tanggal Perjalanan</label>
                                <input type="date" class="form-control" id="travel_date" name="travel_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <button type="submit" name="search_schedule" class="btn btn-primary">
                            <i class="fas fa-search"></i> Cari Jadwal
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-info-circle"></i> Informasi</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success"></i> Pilih rute perjalanan</li>
                        <li><i class="fas fa-check text-success"></i> Tentukan tanggal keberangkatan</li>
                        <li><i class="fas fa-check text-success"></i> Lihat jadwal yang tersedia</li>
                        <li><i class="fas fa-check text-success"></i> Pesan tiket dengan mudah</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($step == 2): ?>
    <!-- Step 2: Select Schedule -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-calendar"></i> Jadwal Tersedia</h5>
                    <a href="booking.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
                <div class="card-body">
                    <?php if (count($schedules) > 0): ?>
                        <?php foreach ($schedules as $schedule): ?>
                        <div class="card mb-3 border">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-3">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($schedule['bus_number']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($schedule['bus_type']); ?></small>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h5><?php echo date('H:i', strtotime($schedule['departure_time'])); ?></h5>
                                            <small><?php echo htmlspecialchars($schedule['origin']); ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-2 text-center">
                                        <i class="fas fa-arrow-right text-primary"></i>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h5><?php echo date('H:i', strtotime($schedule['arrival_time'])); ?></h5>
                                            <small><?php echo htmlspecialchars($schedule['destination']); ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-1 text-end">
                                        <button type="button" class="btn btn-primary btn-sm" 
                                                data-bs-toggle="modal" data-bs-target="#bookingModal<?php echo $schedule['id']; ?>">
                                            Pesan
                                        </button>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <small><strong>Fasilitas:</strong> <?php echo htmlspecialchars($schedule['facilities']); ?></small>
                                    </div>
                                    <div class="col-md-3">
                                        <small><strong>Kursi Tersedia:</strong> <?php echo $schedule['available_seats']; ?></small>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <strong class="text-primary">Rp <?php echo number_format($schedule['price'], 0, ',', '.'); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Booking Modal -->
                        <div class="modal fade" id="bookingModal<?php echo $schedule['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Pesan Tiket</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Nama Penumpang</label>
                                                <input type="text" class="form-control" name="passenger_name" 
                                                       value="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">No. Telepon</label>
                                                <input type="tel" class="form-control" name="passenger_phone" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Jumlah Kursi</label>
                                                <select class="form-select" name="seat_count" required>
                                                    <?php for ($i = 1; $i <= min(5, $schedule['available_seats']); $i++): ?>
                                                    <option value="<?php echo $i; ?>"><?php echo $i; ?> Kursi</option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="alert alert-info">
                                                <strong>Detail Perjalanan:</strong><br>
                                                Rute: <?php echo htmlspecialchars($schedule['route_name']); ?><br>
                                                Tanggal: <?php echo date('d/m/Y', strtotime($travel_date)); ?><br>
                                                Waktu: <?php echo date('H:i', strtotime($schedule['departure_time'])); ?><br>
                                                Harga: Rp <?php echo number_format($schedule['price'], 0, ',', '.'); ?> per kursi
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" name="book_ticket" class="btn btn-primary">Pesan Sekarang</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5>Tidak ada jadwal tersedia</h5>
                            <p class="text-muted">Coba pilih tanggal lain atau rute berbeda.</p>
                            <a href="booking.php" class="btn btn-primary">Pilih Ulang</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
