<?php
$page_title = "Restore Database";
include '../includes/header.php';
requireLogin();
requireAdmin();

$backup_dir = '../backups';
$success = false;
$error = '';
$message = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
    $file = $_FILES['backup_file'];
    
    // Check for errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Error uploading file. Code: ' . $file['error'];
    } 
    // Check file type
    elseif (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'sql') {
        $error = 'File harus berupa SQL backup file';
    }
    // Process the file
    else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Read SQL file
            $sql = file_get_contents($file['tmp_name']);
            
            // Split SQL file into individual queries
            $queries = explode(';', $sql);
            
            // Begin transaction
            $db->beginTransaction();
            
            // Execute each query
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    $db->exec($query);
                }
            }
            
            // Commit transaction
            $db->commit();
            
            $success = true;
            $message = 'Database berhasil di-restore!';
            
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get available backup files
$backup_files = glob("{$backup_dir}/*.sql");
usort($backup_files, function($a, $b) {
    return filemtime($b) - filemtime($a);
});
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
                <h2><i class="fas fa-database"></i> Restore Database</h2>
                <a href="reports.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali ke Laporan
                </a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-upload"></i> Upload Backup File</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="backup_file" class="form-label">Pilih File SQL Backup</label>
                                    <input type="file" class="form-control" id="backup_file" name="backup_file" accept=".sql" required>
                                    <div class="form-text">Hanya file SQL yang diizinkan</div>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> <strong>Perhatian!</strong>
                                    <p>Restore database akan menimpa data yang ada saat ini. Pastikan Anda telah membuat backup terlebih dahulu.</p>
                                </div>
                                
                                <button type="submit" class="btn btn-primary" onclick="return confirm('Apakah Anda yakin ingin me-restore database? Data yang ada saat ini akan ditimpa.')">
                                    <i class="fas fa-upload"></i> Restore Database
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-history"></i> Backup Tersedia</h5>
                        </div>
                        <div class="card-body">
                            <p>Pilih file backup yang tersedia untuk di-restore:</p>
                            
                            <ul class="list-group">
                                <?php
                                if (count($backup_files) > 0) {
                                    foreach ($backup_files as $file) {
                                        $file_name = basename($file);
                                        $file_date = date('d/m/Y H:i:s', filemtime($file));
                                        $file_size = round(filesize($file) / 1024, 2);
                                        
                                        echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
                                        echo "<div><i class='fas fa-file-code text-primary'></i> {$file_name}<br>";
                                        echo "<small class='text-muted'>{$file_date} - {$file_size} KB</small></div>";
                                        echo "<div>";
                                        echo "<a href='download_backup.php?file={$file_name}' class='btn btn-sm btn-outline-primary me-2'>";
                                        echo "<i class='fas fa-download'></i></a>";
                                        echo "<a href='restore.php?file={$file_name}' class='btn btn-sm btn-outline-warning' ";
                                        echo "onclick=\"return confirm('Apakah Anda yakin ingin me-restore database dari file ini?')\">";
                                        echo "<i class='fas fa-undo'></i></a>";
                                        echo "</div>";
                                        echo "</li>";
                                    }
                                } else {
                                    echo "<li class='list-group-item'>Belum ada file backup</li>";
                                }
                                ?>
                            </ul>
                            
                            <div class="mt-3">
                                <a href="backup.php" class="btn btn-success">
                                    <i class="fas fa-plus"></i> Buat Backup Baru
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>