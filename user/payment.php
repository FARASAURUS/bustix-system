<?php
$page_title = "Pembayaran";
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
$booking_code = isset($_GET['booking_code']) ? $_GET['booking_code'] : '';

if (empty($booking_code)) {
    header("Location: dashboard.php");
    exit();
}

// Get booking details
$query = "SELECT b.*, bs.departure_time, bs.arrival_time, bs.schedule_date, bs.price,
                 br.route_name, br.origin, br.destination, 
                 bus.bus_number, bus.bus_type
          FROM bookings b
          JOIN bus_schedules bs ON b.schedule_id = bs.id
          JOIN bus_routes br ON bs.route_id = br.id
          JOIN buses bus ON bs.bus_id = bus.id
          WHERE b.booking_code = ? AND b.user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$booking_code, $_SESSION['user_id']]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header("Location: dashboard.php");
    exit();
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_payment'])) {
    $payment_method = $_POST['payment_method'];
    
    if (empty($payment_method)) {
        $error = "Pilih metode pembayaran!";
    } else {
        // Simulate payment processing
        $transaction_code = 'TRX' . date('YmdHis') . rand(1000, 9999);
        
        try {
            $db->beginTransaction();
            
            // Insert payment transaction
            $payment_query = "INSERT INTO payment_transactions (booking_id, transaction_code, amount, payment_method, payment_status, payment_date) 
                             VALUES (?, ?, ?, ?, 'success', NOW())";
            $stmt = $db->prepare($payment_query);
            $stmt->execute([$booking['id'], $transaction_code, $booking['total_amount'], $payment_method]);
            
            // Update booking status
            $update_query = "UPDATE bookings SET payment_status = 'paid', booking_status = 'confirmed' WHERE id = ?";
            $stmt = $db->prepare($update_query);
            $stmt->execute([$booking['id']]);
            
            $db->commit();
            $success = "Pembayaran berhasil! Booking Anda telah dikonfirmasi.";
            
            // Refresh booking data
            $stmt = $db->prepare($query);
            $stmt->execute([$booking_code, $_SESSION['user_id']]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $db->rollback();
            $error = "Terjadi kesalahan saat memproses pembayaran: " . $e->getMessage();
        }
    }
}
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <h2><i class="fas fa-credit-card"></i> Pembayaran Tiket</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Booking Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-ticket-alt"></i> Detail Booking</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Kode Booking:</strong></td>
                                    <td><?php echo htmlspecialchars($booking['booking_code']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Penumpang:</strong></td>
                                    <td><?php echo htmlspecialchars($booking['passenger_name']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>No. Telepon:</strong></td>
                                    <td><?php echo htmlspecialchars($booking['passenger_phone']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Rute:</strong></td>
                                    <td><?php echo htmlspecialchars($booking['route_name']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Tanggal:</strong></td>
                                    <td><?php echo date('d/m/Y', strtotime($booking['schedule_date'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Waktu:</strong></td>
                                    <td><?php echo date('H:i', strtotime($booking['departure_time'])); ?> - <?php echo date('H:i', strtotime($booking['arrival_time'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Bus:</strong></td>
                                    <td><?php echo htmlspecialchars($booking['bus_number'] . ' (' . $booking['bus_type'] . ')'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Jumlah Kursi:</strong></td>
                                    <td><?php echo $booking['total_seats']; ?> kursi</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Status Booking:</h6>
                            <span class="badge bg-<?php 
                                echo $booking['booking_status'] === 'confirmed' ? 'success' : 
                                    ($booking['booking_status'] === 'pending' ? 'warning' : 'secondary'); 
                            ?> fs-6">
                                <?php echo ucfirst($booking['booking_status']); ?>
                            </span>
                        </div>
                        <div class="col-md-6 text-end">
                            <h4 class="text-primary">Total: Rp <?php echo number_format($booking['total_amount'], 0, ',', '.'); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($booking['payment_status'] === 'pending'): ?>
            <!-- Payment Form -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-credit-card"></i> Pilih Metode Pembayaran</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card payment-method">
                                    <div class="card-body text-center">
                                        <input type="radio" name="payment_method" value="bank_transfer" id="bank_transfer" class="form-check-input">
                                        <label for="bank_transfer" class="form-check-label d-block">
                                            <i class="fas fa-university fa-2x text-primary mb-2"></i>
                                            <h6>Transfer Bank</h6>
                                            <small class="text-muted">BCA, Mandiri, BNI, BRI</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="card payment-method">
                                    <div class="card-body text-center">
                                        <input type="radio" name="payment_method" value="e_wallet" id="e_wallet" class="form-check-input">
                                        <label for="e_wallet" class="form-check-label d-block">
                                            <i class="fas fa-mobile-alt fa-2x text-success mb-2"></i>
                                            <h6>E-Wallet</h6>
                                            <small class="text-muted">GoPay, OVO, DANA</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="card payment-method">
                                    <div class="card-body text-center">
                                        <input type="radio" name="payment_method" value="credit_card" id="credit_card" class="form-check-input">
                                        <label for="credit_card" class="form-check-label d-block">
                                            <i class="fas fa-credit-card fa-2x text-warning mb-2"></i>
                                            <h6>Kartu Kredit</h6>
                                            <small class="text-muted">Visa, Mastercard</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="card payment-method">
                                    <div class="card-body text-center">
                                        <input type="radio" name="payment_method" value="virtual_account" id="virtual_account" class="form-check-input">
                                        <label for="virtual_account" class="form-check-label d-block">
                                            <i class="fas fa-receipt fa-2x text-info mb-2"></i>
                                            <h6>Virtual Account</h6>
                                            <small class="text-muted">VA BCA, Mandiri, BNI</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Informasi:</strong> Ini adalah simulasi pembayaran. Dalam implementasi nyata, 
                            Anda akan diarahkan ke gateway pembayaran yang sebenarnya.
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="process_payment" class="btn btn-primary btn-lg">
                                <i class="fas fa-credit-card"></i> Bayar Sekarang - Rp <?php echo number_format($booking['total_amount'], 0, ',', '.'); ?>
                            </button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Payment Completed -->
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h4>Pembayaran Berhasil!</h4>
                    <p class="text-muted">Booking Anda telah dikonfirmasi dan tiket siap digunakan.</p>
                    
                    <div class="d-grid gap-2 col-md-6 mx-auto">
                        <a href="booking-detail.php?id=<?php echo $booking['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-ticket-alt"></i> Lihat E-Ticket
                        </a>
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-tachometer-alt"></i> Kembali ke Dashboard
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.payment-method {
    cursor: pointer;
    transition: all 0.3s ease;
}

.payment-method:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.payment-method input[type="radio"] {
    display: none;
}

.payment-method input[type="radio"]:checked + label {
    color: #007bff;
}

.payment-method input[type="radio"]:checked ~ .card {
    border-color: #007bff;
    background-color: #f8f9ff;
}
</style>

<script>
$(document).ready(function() {
    $('.payment-method').click(function() {
        $(this).find('input[type="radio"]').prop('checked', true);
        $('.payment-method').removeClass('border-primary bg-light');
        $(this).addClass('border-primary bg-light');
    });
});
</script>

<?php include '../includes/footer.php'; ?>
