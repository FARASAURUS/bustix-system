<?php
// Script khusus untuk backup otomatis via Task Scheduler
// Tidak memerlukan header/footer dan tidak menampilkan output visual

// Muat konfigurasi database
require_once '../config/database.php';

// Fungsi untuk mencatat log
function writeLog($message) {
    $log_file = '../backups/backup_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// Mulai proses backup
writeLog("Memulai proses backup otomatis");

// Buat direktori backup jika belum ada
$backup_dir = '../backups';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
    writeLog("Direktori backup dibuat: $backup_dir");
}

// Koneksi database
try {
    $database = new Database();
    $db = $database->getConnection();
    writeLog("Koneksi database berhasil");
} catch (Exception $e) {
    writeLog("ERROR: Koneksi database gagal: " . $e->getMessage());
    exit(1);
}

// Set nama file backup dengan timestamp
$timestamp = date('Y-m-d_H-i-s');
$filename = "auto_backup_{$timestamp}.sql";
$backup_path = "{$backup_dir}/{$filename}";

// Fungsi untuk mendapatkan semua tabel
function getTables($db) {
    $tables = [];
    $result = $db->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    return $tables;
}

// Fungsi untuk backup tabel
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

// Mulai proses backup
try {
    // Buka file untuk penulisan
    $file = fopen($backup_path, 'w');
    
    // Tulis header
    fwrite($file, "-- busTix Database Backup (OTOMATIS)\n");
    fwrite($file, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($file, "-- ------------------------------------------------------\n\n");
    
    // Dapatkan semua tabel
    $tables = getTables($db);
    writeLog("Ditemukan " . count($tables) . " tabel untuk dibackup");
    
    // Backup setiap tabel
    foreach ($tables as $table_name) {
        writeLog("Membackup tabel: $table_name");
        backupTable($db, $table_name, $file);
    }
    
    // Tutup file
    fclose($file);
    
    // Catat waktu backup terakhir
    file_put_contents("{$backup_dir}/last_backup_time.txt", date('Y-m-d H:i:s'));
    
    // Hapus backup lama (opsional - simpan hanya 10 backup terakhir)
    $backup_files = glob("{$backup_dir}/auto_backup_*.sql");
    usort($backup_files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    if (count($backup_files) > 10) {
        for ($i = 10; $i < count($backup_files); $i++) {
            unlink($backup_files[$i]);
            writeLog("Menghapus backup lama: " . basename($backup_files[$i]));
        }
    }
    
    writeLog("Backup berhasil: $filename");
    echo "Backup berhasil: $filename\n";
    exit(0);
    
} catch (Exception $e) {
    writeLog("ERROR: Backup gagal: " . $e->getMessage());
    echo "ERROR: Backup gagal: " . $e->getMessage() . "\n";
    exit(1);
}
?>