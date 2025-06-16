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
    // Periksa apakah peminjaman ada dan belum dikembalikan
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
    
    if($peminjaman['status'] == 'kembali') {
        $_SESSION['error'] = "Buku sudah dikembalikan sebelumnya";
        header("Location: detail.php?id=$id");
        exit;
    }
    
    // Proses pengiriman formulir
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : '';
        $kondisi = isset($_POST['kondisi']) ? trim($_POST['kondisi']) : 'baik';
          // Begin transaction
        $conn->beginTransaction();
        
        // Set current date as return date
        $tgl_kembali = date('Y-m-d');
        
        // Update peminjaman status
        $update_sql = "UPDATE meminjam 
                      SET status = 'kembali', 
                          tgl_kembali = :tgl_kembali, 
                          keterangan = CONCAT(IFNULL(keterangan, ''), IF(LENGTH(keterangan) > 0, '\n', ''), :keterangan)
                      WHERE id_pinjam = :id";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bindParam(':tgl_kembali', $tgl_kembali);
        $update_stmt->bindParam(':keterangan', $keterangan);
        $update_stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $update_stmt->execute();
        
        // Update book stock - increment by 1 when returned
        $update_stock_sql = "UPDATE buku SET stok = stok + 1 WHERE id_buku = :id_buku";
        $update_stock_stmt = $conn->prepare($update_stock_sql);
        $update_stock_stmt->bindParam(':id_buku', $peminjaman['id_buku'], PDO::PARAM_INT);
        $update_stock_stmt->execute();
        
        // Update book status if needed based on condition
        if($kondisi == 'rusak') {
            // Add to keterangan that book was returned damaged
            $book_note = "Buku dikembalikan dalam kondisi rusak pada " . date('d/m/Y');
            
            $update_book_sql = "UPDATE buku 
                              SET keterangan = CONCAT(IFNULL(keterangan, ''), IF(LENGTH(keterangan) > 0, '\n', ''), :book_note)
                              WHERE id_buku = :id_buku";
            $update_book_stmt = $conn->prepare($update_book_sql);
            $update_book_stmt->bindParam(':book_note', $book_note);
            $update_book_stmt->bindParam(':id_buku', $peminjaman['id_buku'], PDO::PARAM_INT);
            $update_book_stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Buku berhasil dikembalikan";
        header("Location: detail.php?id=$id");
        exit;
    }
} catch(PDOException $e) {
    // Rollback on error
    if($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header("Location: detail.php?id=$id");
    exit;
}

// Include header
include '../templates/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="mb-6">
        <a href="detail.php?id=<?= $id ?>" class="inline-flex items-center text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i> Kembali ke Detail Peminjaman
        </a>
    </div>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="bg-green-600 text-white p-6">
            <h1 class="text-2xl font-bold flex items-center">
                <i class="fas fa-check-circle mr-3"></i>
                Proses Pengembalian Buku
            </h1>
            <p class="text-green-200 mt-2">ID Peminjaman: <?= $id ?></p>
        </div>
        
        <div class="p-6">
            <div class="mb-6">
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700">
                                Anda akan mengembalikan buku <strong><?= htmlspecialchars($peminjaman['judul_buku']) ?></strong> yang dipinjam oleh <strong><?= htmlspecialchars($peminjaman['nama_anggota']) ?></strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <form method="post" action="">
                <div class="mb-6">
                    <label for="kondisi" class="block text-sm font-medium text-gray-700 mb-1">Kondisi Buku</label>
                    <div class="mt-2">
                        <div class="flex items-center">
                            <input id="kondisi_baik" name="kondisi" type="radio" value="baik" checked class="h-4 w-4 text-indigo-600 border-gray-300">
                            <label for="kondisi_baik" class="ml-3 block text-sm font-medium text-gray-700">
                                Baik
                            </label>
                        </div>
                        <div class="flex items-center mt-2">
                            <input id="kondisi_rusak" name="kondisi" type="radio" value="rusak" class="h-4 w-4 text-indigo-600 border-gray-300">
                            <label for="kondisi_rusak" class="ml-3 block text-sm font-medium text-gray-700">
                                Rusak / Bermasalah
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label for="keterangan" class="block text-sm font-medium text-gray-700 mb-1">Keterangan (opsional)</label>
                    <textarea id="keterangan" name="keterangan" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="Tambahkan keterangan jika ada (misalnya kondisi buku atau keterlambatan)"></textarea>
                </div>
                
                <div class="flex justify-end">
                    <a href="detail.php?id=<?= $id ?>" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded mr-2">
                        Batal
                    </a>
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded">
                        <i class="fas fa-check-circle mr-1"></i> Konfirmasi Pengembalian
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>








































