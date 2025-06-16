<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';

// Periksa apakah pengguna sudah login
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Periksa apakah pengguna memiliki akses ke halaman ini (Hanya Admin dan Pustakawan)
requireAccess([1, 2]);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

if($id <= 0) {
    $error = "ID buku tidak valid";
} else {
    try {
        // Periksa apakah buku ada dan dapat dihapus
        $check_sql = "SELECT id_buku FROM buku WHERE id_buku = :id";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if($check_stmt->rowCount() == 0) {
            $error = "Buku tidak ditemukan";
        } else {
            // Periksa apakah buku sedang dipinjam
            $loan_check = "SELECT id_pinjam FROM meminjam WHERE id_buku = :id AND status != 'kembali'";
            $loan_stmt = $conn->prepare($loan_check);
            $loan_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $loan_stmt->execute();
            
            if($loan_stmt->rowCount() > 0) {
                $error = "Buku sedang dipinjam, tidak dapat dihapus";
            } else {
                // Delete the book
                $delete_sql = "DELETE FROM buku WHERE id_buku = :id";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bindParam(':id', $id, PDO::PARAM_INT);
                
                if($delete_stmt->execute()) {
                    $success = "Buku berhasil dihapus";
                } else {
                    $error = "Gagal menghapus buku";
                }
            }
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Set session messages and redirect
if($error) {
    $_SESSION['error'] = $error;
} else {
    $_SESSION['success'] = $success;
}

header("Location: index.php");
exit;








































