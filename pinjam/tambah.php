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

$error = '';
$success = '';

// Ambil data anggota dan buku untuk dropdown
try {
    // Ambil data anggota
    $anggota_sql = "SELECT id_anggota, kode_anggota, nama FROM anggota ORDER BY nama";
    $anggota_stmt = $conn->query($anggota_sql);
    $anggota = $anggota_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ambil data buku dengan stok > 0
    $buku_sql = "SELECT id_buku, kode_buku, judul_buku, stok FROM buku WHERE stok > 0 ORDER BY judul_buku";
    $buku_stmt = $conn->query($buku_sql);
    $buku = $buku_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// Proses pengiriman formulir
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_anggota = (int)$_POST['id_anggota'];
    $id_buku = (int)$_POST['id_buku'];
    $tgl_pinjam = $_POST['tgl_pinjam'];
    
    // Validasi
    if(empty($id_anggota) || empty($id_buku) || empty($tgl_pinjam)) {
        $error = "Semua field harus diisi";
    } else {
        try {
            // Periksa apakah buku tersedia
            $check_buku_sql = "SELECT stok FROM buku WHERE id_buku = :id_buku";
            $check_buku_stmt = $conn->prepare($check_buku_sql);
            $check_buku_stmt->bindParam(':id_buku', $id_buku);
            $check_buku_stmt->execute();
            $book_stok = $check_buku_stmt->fetch(PDO::FETCH_ASSOC)['stok'];
            
            if($book_stok <= 0) {
                $error = "Buku tidak tersedia untuk dipinjam";
            } else {
                // Begin transaction
                $conn->beginTransaction();
                
                // Insert new peminjaman
                $insert_sql = "INSERT INTO meminjam (tgl_pinjam, id_anggota, id_buku, status) 
                               VALUES (:tgl_pinjam, :id_anggota, :id_buku, 'dipinjam')";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bindParam(':tgl_pinjam', $tgl_pinjam);
                $insert_stmt->bindParam(':id_anggota', $id_anggota);
                $insert_stmt->bindParam(':id_buku', $id_buku);
                $insert_stmt->execute();
                
                // Update book stok
                $update_sql = "UPDATE buku SET stok = stok - 1 WHERE id_buku = :id_buku";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bindParam(':id_buku', $id_buku);
                $update_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['success'] = "Data peminjaman berhasil ditambahkan";
                header("Location: index.php");
                exit;
            }
        } catch(PDOException $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Include header
include '../templates/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-book-reader mr-2 text-indigo-600"></i> Tambah Peminjaman
        </h1>
        <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded inline-flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Kembali
        </a>
    </div>
    
    <?php if(!empty($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
            <p><?= $error ?></p>
        </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <form action="" method="post" class="p-6">
            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label for="id_anggota" class="block text-sm font-medium text-gray-700 mb-2">
                        Anggota <span class="text-red-500">*</span>
                    </label>
                    <select name="id_anggota" id="id_anggota" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                        <option value="">-- Pilih Anggota --</option>
                        <?php foreach($anggota as $member): ?>
                            <option value="<?= $member['id_anggota'] ?>"><?= htmlspecialchars($member['nama'] . ' (' . $member['kode_anggota'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="id_buku" class="block text-sm font-medium text-gray-700 mb-2">
                        Buku <span class="text-red-500">*</span>
                    </label>
                    <select name="id_buku" id="id_buku" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                        <option value="">-- Pilih Buku --</option>
                        <?php foreach($buku as $book): ?>
                            <option value="<?= $book['id_buku'] ?>"><?= htmlspecialchars($book['judul_buku'] . ' (' . $book['kode_buku'] . ') - Stok: ' . $book['stok']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="tgl_pinjam" class="block text-sm font-medium text-gray-700 mb-2">
                        Tanggal Peminjaman <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="tgl_pinjam" id="tgl_pinjam" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
            </div>
            
            <div class="mt-6 flex items-center justify-end">
                <button type="reset" class="bg-white hover:bg-gray-100 text-gray-700 font-bold py-2 px-4 border border-gray-300 rounded mr-2">
                    Reset
                </button>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-save mr-2"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../templates/footer.php'; ?>








































