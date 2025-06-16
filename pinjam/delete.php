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

// Periksa apakah ID sudah disediakan
if(!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID Peminjaman tidak ditemukan";
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

try {
    // Periksa apakah peminjaman ada
    $check_sql = "SELECT m.*, b.judul_buku, a.nama as nama_anggota 
                 FROM meminjam m 
                 JOIN buku b ON m.id_buku = b.id_buku
                 JOIN anggota a ON m.id_anggota = a.id_anggota
                 WHERE m.id_pinjam = :id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if($check_stmt->rowCount() == 0) {
        $_SESSION['error'] = "Data peminjaman tidak ditemukan";
        header("Location: index.php");
        exit;
    }
    
    $peminjaman = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Delete the record
    $delete_sql = "DELETE FROM meminjam WHERE id_pinjam = :id";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $delete_stmt->execute();
    
    $_SESSION['success'] = "Data peminjaman berhasil dihapus";
    header("Location: index.php");
    exit;
    
} catch(PDOException $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header("Location: index.php");
    exit;
}








































