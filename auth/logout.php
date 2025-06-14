<?php
require_once '../config/database.php';
startSession();

// Destroy session
session_destroy();

// Redirect to home page
header("Location: ../index.php");
exit();
?>
