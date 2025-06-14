<?php
$page_title = "Detail Booking";
include '../includes/header.php';
requireLogin();

if (isAdmin()) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($booking_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

// Get booking details
$query = "SELECT b.*, bs.departure_time, bs.arrival_time, bs.schedule_date, bs.price,
                 br.route_name, br.origin, br.destination, 
                 bus.bus_number, bus.bus_type, bus.facilities
          FROM bookings b
          JOIN bus_schedules bs ON b.schedule_id = bs.id
          JOIN bus_routes br ON bs.route_id = br.id
          JOIN buses bus ON bs.bus_id = bus.id
          WHERE b.id = ? AND b.user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header("Location: dashboard.php");
    exit();
}

// Get payment transaction if exists
$payment_query = "SELECT * FROM payment_transactions WHERE booking_id = ? ORDER BY created_at DESC LIMIT 1";
$stmt = $db->prepare($payment_query);
$stmt->execute([$booking_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-ticket-alt"></i> Detail Booking</h2>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>

            <!-- E-Ticket -->
            <div class="card mb-4" id="e-ticket">
                <div class="card-header bg-primary text-white">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0"><i class="fas fa-bus"></i> busTix E-Ticket</h5>
                        </div>
                        <div class="col-auto">
                            <span class="badge bg-<?php 
                                echo $booking['booking_status'] === 'confirmed' ? 'success' : 
                                    ($booking['booking_status'] === 'pending' ? 'warning' : 
                                    ($booking['booking_status'] === 'cancelled' ? 'danger' : 'secondary')); 
                            ?> fs-6">
                                <?php echo ucfirst($booking['booking_status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Info Umum -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h4 class="text-primary"><?php echo htmlspecialchars($booking['booking_code']); ?></h4>
                            <p class="text-muted mb-0">Kode Booking</p>
                        </div>
                        <div class="col-md-6 text-end">
                            <h6><?php echo date('d F Y', strtotime($booking['booking_date'])); ?></h6>
                            <p class="text-muted mb-0">Tanggal Booking</p>
                        </div>
                    </div>

                    <!-- Rute -->
                    <div class="row mb-4 text-center">
                        <div class="col-md-4">
                            <h5><?php echo htmlspecialchars($booking['origin']); ?></h5>
                            <p class="text-muted"><?php echo date('H:i', strtotime($booking['departure_time'])); ?></p>
                            <small class="text-muted"><?php echo date('d/m/Y', strtotime($booking['schedule_date'])); ?></small>
                        </div>
                        <div class="col-md-4">
                            <i class="fas fa-arrow-right fa-2x text-primary mb-2"></i>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($booking['route_name']); ?></p>
                        </div>
                        <div class="col-md-4">
                            <h5><?php echo htmlspecialchars($booking['destination']); ?></h5>
                            <p class="text-muted"><?php echo date('H:i', strtotime($booking['arrival_time'])); ?></p>
                            <small class="text-muted"><?php echo date('d/m/Y', strtotime($booking['schedule_date'])); ?></small>
                        </div>
                    </div>

                    <hr>

                    <!-- Penumpang & Bus -->
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-user"></i> Informasi Penumpang</h6>
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td>Nama:</td>
                                    <td><strong><?php echo htmlspecialchars($booking['passenger_name']); ?></strong></td>
                                </tr>
                                <tr>
                                    <td>Telepon:</td>
                                    <td><?php echo htmlspecialchars($booking['passenger_phone']); ?></td>
                                </tr>
                                <tr>
                                    <td>Total Kursi:</td>
                                    <td><?php echo $booking['total_seats']; ?> kursi</td>
                                </tr>
                                <tr>
                                    <td>Kursi Dipesan:</td>
                                    <td>
                                        <?php
                                        if (!empty($booking['seat_numbers'])) {
                                            $seats = explode(',', $booking['seat_numbers']);
                                            foreach ($seats as $seat) {
                                                echo '<span class="badge bg-info text-white me-1">' . htmlspecialchars(trim($seat)) . '</span>';
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-bus"></i> Informasi Bus</h6>
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td>No. Bus:</td>
                                    <td><strong><?php echo htmlspecialchars($booking['bus_number']); ?></strong></td>
                                </tr>
                                <tr>
                                    <td>Tipe:</td>
                                    <td><?php echo htmlspecialchars($booking['bus_type']); ?></td>
                                </tr>
                                <tr>
                                    <td>Fasilitas:</td>
                                    <td><?php echo htmlspecialchars($booking['facilities']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <hr>

                    <!-- Pembayaran -->
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-credit-card"></i> Informasi Pembayaran</h6>
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td>Status Pembayaran:</td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $booking['payment_status'] === 'paid' ? 'success' : 
                                                ($booking['payment_status'] === 'pending' ? 'warning' : 'secondary'); 
                                        ?>">
                                            <?php echo ucfirst($booking['payment_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php if ($payment): ?>
                                <tr>
                                    <td>Metode:</td>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                </tr>
                                <tr>
                                    <td>Kode Transaksi:</td>
                                    <td><?php echo htmlspecialchars($payment['transaction_code']); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="col-md-6 text-end">
                            <h4 class="text-primary">Rp <?php echo number_format($booking['total_amount'], 0, ',', '.'); ?></h4>
                            <p class="text-muted">Total Pembayaran</p>
                        </div>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="card mt-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <?php if ($booking['payment_status'] === 'pending'): ?>
                                        <a href="payment.php?booking_code=<?php echo $booking['booking_code']; ?>" class="btn btn-primary">
                                            <i class="fas fa-credit-card"></i> Bayar Sekarang
                                        </a>
                                    <?php endif; ?>

                                    <?php if (in_array($booking['booking_status'], ['pending', 'confirmed'])): ?>
                                        <button class="btn btn-outline-danger" onclick="cancelBooking(<?php echo $booking['id']; ?>)">
                                            <i class="fas fa-times"></i> Batalkan Booking
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6 text-end">
                                    <button class="btn btn-outline-primary" onclick="printTicket()">
                                        <i class="fas fa-print"></i> Print E-Ticket
                                    </button>
                                    <button class="btn btn-outline-success" onclick="downloadTicket()">
                                        <i class="fas fa-download"></i> Download PDF
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Info -->
                    <div class="alert alert-info mt-4">
                        <h6><i class="fas fa-info-circle"></i> Informasi Penting:</h6>
                        <ul class="mb-0">
                            <li>Harap tiba di terminal 30 menit sebelum keberangkatan</li>
                            <li>Bawa identitas diri yang valid (KTP/SIM/Paspor)</li>
                            <li>E-ticket ini berlaku sebagai bukti pemesanan yang sah</li>
                            <li>Hubungi customer service jika ada pertanyaan: +62 123 456 7890</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script>
function printTicket() {
    var printContent = document.getElementById('e-ticket').innerHTML;
    var originalContent = document.body.innerHTML;
    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

function downloadTicket() {
    alert('Fitur download PDF akan segera tersedia!');
}

function cancelBooking(bookingId) {
    if (confirm('Apakah Anda yakin ingin membatalkan booking ini?')) {
        $.ajax({
            url: 'cancel-booking.php',
            method: 'POST',
            data: { booking_id: bookingId },
            dataType: 'json',
            success: function(response) {
                alert(response.message);
                if (response.success) {
                    location.reload();
                }
            },
            error: function(xhr, status, error) {
                alert('Terjadi kesalahan: ' + error);
            }
        });
    }
}
</script>

<?php include '../includes/footer.php'; ?>
