<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';

// Periksa apakah pengguna sudah login
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Periksa apakah pengguna memiliki akses ke halaman ini (Hanya Admin)
requireAccess([1]);

// Process delete request
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['id'] ?? null;

    if($user_id) {
        try {
            // First check if user exists
            $checkStmt = $conn->prepare("SELECT * FROM users WHERE id_user = :id_user");
            $checkStmt->bindParam(':id_user', $user_id, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if($checkStmt->rowCount() > 0) {
                $stmt = $conn->prepare("DELETE FROM users WHERE id_user = :id_user");
                $stmt->bindParam(':id_user', $user_id, PDO::PARAM_INT);
                $stmt->execute();

                $_SESSION['success'] = "User deleted successfully.";
            } else {
                $_SESSION['error'] = "User ID not found in database.";
            }
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error deleting user: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Invalid user ID.";
    }

    header("Location: index.php");
    exit;
}







































