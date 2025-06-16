<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';

// Periksa apakah pengguna sudah login
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Only Admin and Pustakawan can access this page
requireAccess([1, 2]);

// Periksa apakah ID sudah disediakan
if(!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID Anggota tidak ditemukan";
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Periksa apakah anggota ada
    $check_sql = "SELECT a.*, COUNT(m.id_pinjam) as total_pinjam 
                 FROM anggota a
                 LEFT JOIN meminjam m ON a.id_anggota = m.id_anggota
                 WHERE a.id_anggota = :id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if($check_stmt->rowCount() == 0) {
        $_SESSION['error'] = "Anggota tidak ditemukan";
        header("Location: index.php");
        exit;
    }
    
    $anggota = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if this anggota has active loans
    if($anggota['total_pinjam'] > 0) {
        $_SESSION['error'] = "Tidak dapat menghapus anggota yang memiliki riwayat peminjaman";
        header("Location: index.php");
        exit;
    }
    
    // Check if anggota is linked to user account
    $check_user_sql = "SELECT id_user FROM users WHERE nama_lengkap = :nama";
    $check_user_stmt = $conn->prepare($check_user_sql);
    $check_user_stmt->bindParam(':nama', $anggota['nama']);
    $check_user_stmt->execute();
    
    if($check_user_stmt->rowCount() > 0) {
        $_SESSION['error'] = "Tidak dapat menghapus anggota yang terhubung dengan akun user";
        header("Location: index.php");
        exit;
    }
    
    // Delete anggota
    $delete_sql = "DELETE FROM anggota WHERE id_anggota = :id";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $delete_stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success'] = "Anggota berhasil dihapus";
} catch(PDOException $e) {
    // Rollback on error
    $conn->rollBack();
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

// Redirect back to index
header("Location: index.php");
exit;








































