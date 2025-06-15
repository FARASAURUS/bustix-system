# 🚌 bustix-system (Sistem Pemesanan Tiket Bus)

Proyek ini merupakan sistem informasi pemesanan tiket bus berbasis PHP dan MySQL. Dirancang dengan pendekatan modular dan prinsip integritas data tinggi menggunakan **stored procedure**, **trigger**, **transaction**, dan **event scheduler** SQL. Cocok untuk skala UMKM transportasi dengan manajemen armada dan jadwal perjalanan.

![Dashboard](assets/dashboard.png)

---

## 📌 Detail Konsep

### ⚠️ Disclaimer

Peran dari **stored procedure**, **trigger**, **transaction**, dan **scheduler** pada proyek ini dikembangkan untuk memenuhi skenario sistem tiket bus. Implementasi bisa berbeda pada sistem lain tergantung skema bisnis yang diterapkan.

---
### 🔁 Transaction – Menjamin Konsistensi Saat Booking & Pembayaran
Dalam sistem pemesanan tiket seperti Bustix, transaction digunakan untuk memastikan bahwa seluruh proses booking (mulai dari pemesanan, validasi, hingga pembayaran) dilakukan dalam satu kesatuan logis. Jika salah satu langkah gagal, maka seluruh proses dibatalkan menggunakan ROLLBACK.

🧠 Tujuan:
✅ Menghindari booking ganda

✅ Menghindari overbooking

✅ Menjaga konsistensi antara booking dan pembayaran

✅ Mencegah data sebagian masuk

🧪 Struktur Transaction di MySQL

```sql
START TRANSACTION;

-- Simpan data booking
INSERT INTO bookings (...) VALUES (...);

-- Kurangi jumlah kursi
UPDATE bus_schedules SET available_seats = available_seats - 1 WHERE id = ...;

-- Simpan transaksi pembayaran
INSERT INTO payment_transactions (...) VALUES (...);

-- Jika semuanya berhasil:
COMMIT;

-- Jika ada error di salah satu langkah:
ROLLBACK;
```

💻 Implementasi Transaction di PHP (booking.php)

```php
$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Ambil semua rute
$routes_query = "SELECT * FROM bus_routes ORDER BY route_name";
$stmt = $db->prepare($routes_query);
$stmt->execute();
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form
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
            $seat_numbers = implode(',', range(1, $seat_count)); // dummy seat assign

            // 🚨 Booking dijalankan melalui stored procedure yang berisi transaksi
            $stmt = $db->prepare("CALL buatBooking(?, ?, ?, ?, ?, ?, @booking_code, @result)");
            try {
                $stmt->execute([
                    $_SESSION['user_id'],
                    $schedule_id,
                    $passenger_name,
                    $passenger_phone,
                    $seat_numbers,
                    $seat_count
                ]);

                // Ambil hasil dari prosedur
                $result_stmt = $db->query("SELECT @booking_code AS booking_code, @result AS result");
                $result = $result_stmt->fetch(PDO::FETCH_ASSOC);

                $result_msg = $result['result'] ?? '';
                $booking_code = $result['booking_code'] ?? '';

                if (is_string($result_msg) && strpos($result_msg, 'SUCCESS') !== false) {
                    header("Location: payment.php?booking_code=" . urlencode((string)$booking_code));
                    exit();
                } else {
                    $error = is_string($result_msg)
                        ? str_replace('ERROR: ', '', $result_msg)
                        : 'Gagal memproses booking. Silakan coba lagi.';
                }
            } catch (Exception $e) {
                $error = "Terjadi kesalahan saat booking: " . $e->getMessage();
            }
        }
    }
}
```


### 🧠 Stored Procedure

Procedure digunakan untuk mengelola proses pemesanan tiket, pembayaran, dan pembatalan secara langsung dari sisi database, menjamin konsistensi data.

![Procedure](assets/procudure.png)

Contoh: `buatBooking`

1. buatBooking(p_booking_code)
Menandai status pembayaran booking menjadi paid dan mencatat histori pembayaran.

```sql
DELIMITER $$

CREATE PROCEDURE buatBooking(
    IN p_booking_code VARCHAR(20)
)
BEGIN
    UPDATE bookings 
    SET payment_status = 'paid' 
    WHERE booking_code = p_booking_code;

    INSERT INTO booking_history (booking_id, action_type, booking_status, payment_status)
    SELECT id, 'pembayaran', booking_status, 'paid' 
    FROM bookings WHERE booking_code = p_booking_code;
END $$

DELIMITER ;
```

```php
// Ambil koneksi database
$database = new Database();
$db = $database->getConnection();

$userId = $_SESSION['user_id'];
$scheduleId = $_POST['schedule_id'];
$passengerName = $_POST['passenger_name'];
$passengerPhone = $_POST['passenger_phone'];
$seats = $_POST['seats']; // array
$seatNumbers = implode(',', $seats);
$totalSeats = count($seats);

try {
    $stmt = $db->prepare("CALL buatBooking(?, ?, ?, ?, ?, ?, @booking_code, @result)");
    $stmt->execute([
        $userId,
        $scheduleId,
        $passengerName,
        $passengerPhone,
        $seatNumbers,
        $totalSeats
    ]);

    $output = $db->query("SELECT @booking_code AS booking_code, @result AS result")->fetch();

    if (strpos($output['result'], 'SUCCESS') !== false) {
        echo "✅ Booking berhasil. Kode: " . $output['booking_code'];
    } else {
        echo "❌ Gagal: " . $output['result'];
    }
} catch (PDOException $e) {
    echo "❌ Error SQL: " . $e->getMessage();
}
```

2. batalkanBooking(p_booking_code)
Membatalkan booking dan menyimpan status ke histori.

```sql
DELIMITER $$

CREATE PROCEDURE batalkanBooking(
    IN p_booking_code VARCHAR(20)
)
BEGIN
    UPDATE bookings 
    SET booking_status = 'cancelled' 
    WHERE booking_code = p_booking_code;

    INSERT INTO booking_history (booking_id, action_type, booking_status, payment_status)
    SELECT id, 'pembatalan', 'cancelled', payment_status
    FROM bookings WHERE booking_code = p_booking_code;
END $$

DELIMITER ;
```
```php
$bookingCode = $_GET['booking_code']; // dari URL

try {
    $stmt = $db->prepare("CALL batalkanBooking(?)");
    $stmt->execute([$bookingCode]);

    echo "Booking $bookingCode berhasil dibatalkan.";
} catch (PDOException $e) {
    echo "Error saat pembatalan: " . $e->getMessage();
}
```

3. getBookingHistory(p_user_id)
Menampilkan riwayat booking yang dilakukan oleh user tertentu.

```sql
DELIMITER $$

DROP PROCEDURE IF EXISTS getBookingHistory $$
CREATE PROCEDURE getBookingHistory(
    IN p_user_id INT
)
BEGIN
    SELECT b.booking_code, bh.action_type, bh.booking_status, bh.payment_status, bh.timestamp
    FROM booking_history bh
    JOIN bookings b ON bh.booking_id = b.id
    WHERE b.user_id = p_user_id
    ORDER BY bh.timestamp DESC;
END $$

DELIMITER ;
```

```php
$userId = $_SESSION['user_id'];

try {
    $stmt = $db->prepare("CALL getBookingHistory(?)");
    $stmt->execute([$userId]);

    echo "<h3>Riwayat Booking Anda</h3>";
    echo "<table><tr><th>Kode</th><th>Aksi</th><th>Status</th><th>Bayar</th><th>Waktu</th></tr>";

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>
                <td>{$row['booking_code']}</td>
                <td>{$row['action_type']}</td>
                <td>{$row['booking_status']}</td>
                <td>{$row['payment_status']}</td>
                <td>{$row['timestamp']}</td>
              </tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "❌ Error ambil histori: " . $e->getMessage();
}
```

Dengan menyimpan proses-proses ini di sisi database, sistem menjaga integritas data di level paling dasar, terlepas dari cara aplikasi mengaksesnya.

## 🚨 Trigger
Trigger booking_history_trigger adalah bagian dari sistem pelacakan histori status pemesanan. Ia bertindak sebagai perekam otomatis yang mencatat setiap perubahan status booking (baik status pemesanan maupun pembayaran) ke dalam tabel booking_history.

Fungsinya seperti CCTV digital, yang merekam segala aktivitas penting yang terjadi pada data pemesanan — siapa yang mengubah apa dan kapan perubahan terjadi.

![Triggers](assets/Triggers.png)

Peran Trigger booking_history_trigger
Trigger ini aktif secara otomatis setiap kali ada UPDATE pada tabel bookings. Ia mencatat perubahan status, sehingga sistem dapat:

🔍 Melacak jejak perubahan status booking  
🕵️ Menyediakan histori transaksi pengguna untuk keperluan audit  
📈 Menyediakan data dasar untuk laporan perkembangan pemesanan

```sql
CREATE TRIGGER booking_history_trigger
AFTER UPDATE ON bookings
FOR EACH ROW
BEGIN
    IF OLD.booking_status != NEW.booking_status 
       OR OLD.payment_status != NEW.payment_status THEN
        INSERT INTO booking_history (
            booking_id, action_type, booking_status, payment_status
        ) VALUES (
            NEW.id, 'status update', NEW.booking_status, NEW.payment_status
        );
    END IF;
END;
```

✅ 1. Contoh Trigger INSERT → booking_create_trigger
📄 PHP Code: Menambahkan booking baru (memicu INSERT trigger)
```php
$booking_code = 'TRG-' . time(); // kode unik
$stmt = $db->prepare("
    INSERT INTO bookings (
        user_id, schedule_id, booking_code,
        passenger_name, passenger_phone,
        seat_numbers, total_seats, total_amount,
        booking_status, payment_status, booking_date
    ) VALUES (
        :user_id, :schedule_id, :booking_code,
        :name, :phone,
        :seats, :total_seats, :amount,
        'pending', 'unpaid', NOW()
    )
");

$stmt->execute([
    'user_id' => $_SESSION['user_id'],
    'schedule_id' => 1,
    'booking_code' => $booking_code,
    'name' => 'Test Trigger',
    'phone' => '081122334455',
    'seats' => 'A1',
    'total_seats' => 1,
    'amount' => 75000
]);

echo "Booking berhasil dibuat.<br>";
```

✅ 2. Contoh Trigger UPDATE → booking_history_trigger dan booking_cancel_trigger
📄 PHP Code: Update status booking menjadi cancelled
```php
$stmt = $db->prepare("
    UPDATE bookings
    SET booking_status = 'cancelled', payment_status = 'refunded'
    WHERE booking_code = :kode
");

$stmt->execute(['kode' => $booking_code]);

echo "Booking berhasil dibatalkan.<br>";
```

✅ 3. Contoh Trigger DELETE → booking_delete_trigger
📄 PHP Code: Hapus booking (memicu trigger BEFORE DELETE)
```php
$stmt = $db->prepare("DELETE FROM bookings WHERE booking_code = :kode");
$stmt->execute(['kode' => $booking_code]);

echo "Booking berhasil dihapus.<br>";
```

🔍 Cek Hasil Trigger
Setelah operasi-operasi di atas, kamu bisa mengecek hasil kerja trigger:

```php
$stmt = $db->prepare("SELECT * FROM booking_history WHERE booking_id IS NOT NULL ORDER BY timestamp DESC LIMIT 5");
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h4>Histori Booking</h4><ul>";
foreach ($history as $row) {
    echo "<li><b>Aksi:</b> {$row['action_type']} | <b>Status:</b> {$row['booking_status']} | <b>Bayar:</b> {$row['payment_status']}</li>";
}
echo "</ul>";
```

## 🧠 Stored Procedure: buatBooking
Stored Procedure buatBooking bertindak sebagai titik utama proses pemesanan dalam sistem bustix-system. Procedure ini menyederhanakan dan mengamankan proses booking langsung dari sisi database. Hal ini penting karena setiap pemesanan melibatkan banyak elemen: validasi kursi, penumpang, jadwal, dan penghasilan kode_booking unik.

Dengan menggunakan procedure ini, seluruh proses pemesanan menjadi lebih konsisten, efisien, dan lebih aman dari manipulasi langsung di sisi aplikasi.

📌 Fungsi Utama  
- Validasi jumlah kursi yang tersedia  
- Menolak booking yang melebihi kapasitas bus  
- Menyimpan informasi penumpang dan kursi  
- Menghasilkan booking_code unik  
- Mengembalikan status keberhasilan/gagal via parameter OUT

📜 Struktur Pemanggilan

```sql
CALL buatBooking(
    p_user_id,
    p_schedule_id,
    p_passenger_name,
    p_passenger_phone,
    p_seat_numbers,
    p_total_seats,
    @kode_booking,
    @hasil
);
```

💡 Contoh Implementasi di PHP

```php
// Ambil data dari form
$userId = $_SESSION['user_id'];
$scheduleId = $_POST['schedule_id'];
$passengerName = $_POST['name'];
$passengerPhone = $_POST['phone'];
$seatNumbers = implode(',', $_POST['seats']); // e.g., "A1,A2"
$totalSeats = count($_POST['seats']);

// Panggil prosedur booking
$stmt = $conn->prepare("CALL buatBooking(?, ?, ?, ?, ?, ?, @booking_code, @result)");
$stmt->execute([
    $userId,
    $scheduleId,
    $passengerName,
    $passengerPhone,
    $seatNumbers,
    $totalSeats
]);

// Ambil output
$output = $conn->query("SELECT @booking_code AS code, @result AS message")->fetch();

if ($output['code']) {
    echo "Booking berhasil. Kode Anda: " . $output['code'];
} else {
    echo "Gagal booking: " . $output['message'];
}
```

## 📺 Stored Function – Menampilkan Informasi tanpa Mengubah Data
Stored function dalam sistem BustiX digunakan untuk mengambil informasi penting tanpa mengubah isi database. Ibarat layar informasi elektronik di terminal bus — fungsinya hanya untuk menampilkan, bukan mencatat ulang.

### Fungsi get_seat_availability(p_schedule_id)

```sql
CREATE FUNCTION get_seat_availability(p_schedule_id INT)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE total_capacity INT;
    DECLARE total_booked INT;

    SELECT capacity INTO total_capacity
    FROM schedules WHERE id = p_schedule_id;

    SELECT IFNULL(SUM(total_seats), 0) INTO total_booked
    FROM bookings
    WHERE schedule_id = p_schedule_id AND booking_status = 'confirmed';

    RETURN total_capacity - total_booked;
END;
```

🔍 Tujuan  
💺 Menampilkan jumlah kursi yang masih bisa dibooking pada jadwal tertentu  
💡 Digunakan untuk pre-check sebelum booking dimulai  
🧠 Dipanggil oleh prosedur buatBooking maupun oleh frontend PHP/JS

### 💻 Penggunaan di Aplikasi

📍 Dalam Prosedur Booking

```sql
SET @available = get_seat_availability(p_schedule_id);
IF @available < p_total_seats THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Kursi tidak mencukupi';
END IF;
```

📍 Dalam Tampilan Jadwal (PHP)

```php
$stmt = $conn->prepare("SELECT get_seat_availability(?) AS sisa_kursi");
$stmt->execute([$scheduleId]);
$data = $stmt->fetch();
echo "Tersisa " . $data['sisa_kursi'] . " kursi";
```

📍 Dalam UI (Blade / HTML)

```html
<div class="text-muted">
    Tersedia: <?= $data['sisa_kursi']; ?> kursi
</div>
```

## 🔄 Backup Otomatis
Untuk menjaga ketersediaan dan keamanan data, sistem bustix-system dilengkapi fitur backup otomatis berbasis mysqldump yang dijalankan secara berkala. File hasil backup disimpan di dalam folder:

```
/backups
```

Setiap file backup dinamai dengan format timestamp, sehingga mudah ditelusuri dan dikelola.

### 🛠️ backup_bustix.cmd

```cmd
@echo off
set DATE=%DATE:~10,4%-%DATE:~4,2%-%DATE:~7,2%
set TIME=%TIME:~0,2%-%TIME:~3,2%-%TIME:~6,2%
"C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe" -u root bustix > ..\backups\bustix_backup_%DATE%_%TIME%.sql
```

### 💻 backup.php (Backup via Web Server)

```php
<?php
require_once __DIR__ . '/../config/database.php';

$date = date('Y-m-d_H-i-s');
$backupFile = __DIR__ . "/../backups/bustix_backup_$date.sql";
$command = "\"C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysqldump.exe\" -u root bustix > \"$backupFile\"";
exec($command);
echo "Backup berhasil disimpan di: $backupFile";
?>
```

### 🗂️ Lokasi Default Backup

Semua file backup disimpan dalam folder:

```
/backups/bustix_backup_YYYY-MM-DD_HH-MM-SS.sql
```

### 📅 Penjadwalan Otomatis (Task Scheduler)

Backup dapat dijalankan secara otomatis setiap hari dengan menambahkan scripts/backup_bustix.cmd ke dalam Windows Task Scheduler:

⏰ Jadwal: 1x per hari (misalnya jam 23:59)  
🧰 Aksi: Jalankan file .cmd  
✅ Hasil: Backup otomatis tanpa intervensi manual.
