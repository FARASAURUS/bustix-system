<?php
require_once '../config/database.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$user_id = $_SESSION['user_id'];

if ($booking_id <= 0 || $user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking or user ID']);
    exit();
}

try {
    // CALL batalkanBooking(booking_id, user_id, @result)
    $stmt = $db->prepare("CALL batalkanBooking(:booking_id, :user_id, @result)");
    $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    // Ambil output @result
    $result_stmt = $db->query("SELECT @result AS result");
    $result = $result_stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && strpos($result['result'], 'SUCCESS') !== false) {
        echo json_encode(['success' => true, 'message' => $result['result']]);
    } else {
        echo json_encode(['success' => false, 'message' => $result['result'] ?? 'Pembatalan gagal']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
