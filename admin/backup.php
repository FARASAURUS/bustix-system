<?php
$page_title = "Backup Database";
include '../includes/header.php';
requireLogin();
requireAdmin();

// Create backups directory if it doesn't exist
$backup_dir = '../backups';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get table to backup (if specified)
$table = isset($_GET['table']) ? $_GET['table'] : null;

// Set filename for backup
$timestamp = date('Y-m-d_H-i-s');
$filename = $table ? "backup_{$table}_{$timestamp}.sql" : "backup_full_{$timestamp}.sql";
$backup_path = "{$backup_dir}/{$filename}";

// Function to get all tables
function getTables($db) {
    $tables = [];
    $result = $db->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    return $tables;
}

// Function to generate backup for a table
function backupTable($db, $table, $file) {
    // Get create table statement
    $stmt = $db->query("SHOW CREATE TABLE `$table`");
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $create_table = $row[1];
    
    fwrite($file, "-- Table structure for table `$table`\n");
    fwrite($file, "DROP TABLE IF EXISTS `$table`;\n");
    fwrite($file, "$create_table;\n\n");
    
    // Get table data
    $stmt = $db->query("SELECT * FROM `$table`");
    $column_count = $stmt->columnCount();
    
    if ($stmt->rowCount() > 0) {
        fwrite($file, "-- Dumping data for table `$table`\n");
        fwrite($file, "INSERT INTO `$table` VALUES\n");
        
        $row_count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $row_count++;
            
            fwrite($file, "(");
            for ($i = 0; $i < $column_count; $i++) {
                if ($row[$i] === null) {
                    fwrite($file, "NULL");
                } else {
                    fwrite($file, "'" . addslashes($row[$i]) . "'");
                }
                
                if ($i < ($column_count - 1)) {
                    fwrite($file, ",");
                }
            }
            
            if ($row_count < $stmt->rowCount()) {
                fwrite($file, "),\n");
            } else {
                fwrite($file, ");\n");
            }
        }
    }
    
    fwrite($file, "\n\n");
}

// Start backup process
$success = false;
$error = '';

try {
    // Open file for writing
    $file = fopen($backup_path, 'w');
    
    // Write header
    fwrite($file, "-- busTix Database Backup\n");
    fwrite($file, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($file, "-- ------------------------------------------------------\n\n");
    
    // Get tables to backup
    $tables = $table ? [$table] : getTables($db);
    
    // Backup each table
    foreach ($tables as $table_name) {
        backupTable($db, $table_name, $file);
    }
    
    // Close file
    fclose($file);
    
    // Update last backup time
    file_put_contents("{$backup_dir}/last_backup_time.txt", date('Y-m-d H:i:s'));
    
    $success = true;
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Set headers for download if successful
if ($success && !headers_sent()) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . basename($backup_path));
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($backup_path));
    ob_clean();
    flush();
    readfile($backup_path);
    exit;
}
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-database"></i> Backup Database</h2>
                <a href="reports.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali ke Laporan
                </a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Error: <?php echo $error; ?>
                </div>
            <?php elseif (!$success): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <h4>Memproses Backup...</h4>
                        <p class="text-muted">Mohon tunggu, file backup sedang dibuat.</p>
                    </div>
                </div>
                
                <script>
                    // Redirect to download after a short delay
                    setTimeout(function() {
                        window.location.href = window.location.href;
                    }, 2000);
                </script>
            <?php endif; ?>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle"></i> Informasi Backup</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Backup yang Tersedia:</h6>
                            <ul class="list-group">
                                <?php
                                $backup_files = glob("{$backup_dir}/*.sql");
                                usort($backup_files, function($a, $b) {
                                    return filemtime($b) - filemtime($a);
                                });
                                
                                $count = 0;
                                foreach ($backup_files as $file) {
                                    if ($count++ < 5) {
                                        $file_name = basename($file);
                                        $file_date = date('d/m/Y H:i:s', filemtime($file));
                                        $file_size = round(filesize($file) / 1024, 2);
                                        
                                        echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
                                        echo "<div><i class='fas fa-file-code text-primary'></i> {$file_name}<br>";
                                        echo "<small class='text-muted'>{$file_date} - {$file_size} KB</small></div>";
                                        echo "<a href='download_backup.php?file={$file_name}' class='btn btn-sm btn-outline-primary'>";
                                        echo "<i class='fas fa-download'></i></a>";
                                        echo "</li>";
                                    }
                                }
                                
                                if (count($backup_files) == 0) {
                                    echo "<li class='list-group-item'>Belum ada file backup</li>";
                                }
                                ?>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Panduan Backup:</h6>
                            <div class="alert alert-info">
                                <ul class="mb-0">
                                    <li>Backup Full Database: Membuat backup seluruh database</li>
                                    <li>Backup Tabel: Membuat backup tabel tertentu saja</li>
                                    <li>File backup akan otomatis didownload</li>
                                    <li>Backup secara berkala untuk keamanan data</li>
                                </ul>
                            </div>
                            
                            <h6 class="mt-3">Opsi Backup:</h6>
                            <a href="backup.php" class="btn btn-primary me-2 mb-2">
                                <i class="fas fa-database"></i> Backup Full Database
                            </a>
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-outline-primary dropdown-toggle mb-2" type="button" id="backupDropdown" data-bs-toggle="dropdown" aria-expanded="false">
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>