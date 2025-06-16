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
    // Ambil data peminjaman
    $sql = "SELECT * FROM meminjam WHERE id_pinjam = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if($stmt->rowCount() == 0) {
        $_SESSION['error'] = "Data peminjaman tidak ditemukan";
        header("Location: index.php");
        exit;
    }
    
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Periksa apakah status peminjaman sudah "kembali" - tidak dapat mengedit peminjaman yang sudah dikembalikan
    if($loan['status'] == 'kembali') {
        $_SESSION['error'] = "Tidak dapat mengedit peminjaman yang sudah dikembalikan";
        header("Location: detail.php?id=$id");
        exit;
    }
    
    // Ambil daftar anggota untuk dropdown
    $anggota_sql = "SELECT id_anggota, kode_anggota, nama FROM anggota ORDER BY nama";
    $anggota_stmt = $conn->query($anggota_sql);
    $anggota_list = $anggota_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ambil daftar buku untuk dropdown
    $buku_sql = "SELECT id_buku, kode_buku, judul_buku FROM buku ORDER BY judul_buku";
    $buku_stmt = $conn->query($buku_sql);
    $buku_list = $buku_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Proses pengiriman formulir
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        $id_anggota = (int)$_POST['id_anggota'];
        $id_buku = (int)$_POST['id_buku'];
        $tgl_pinjam = $_POST['tgl_pinjam'];
        $tgl_jatuh_tempo = $_POST['tgl_jatuh_tempo'];
        $status = $_POST['status'];
        $keterangan = trim($_POST['keterangan']);
        
        // Validasi
        $errors = [];
        
        if($id_anggota <= 0) {
            $errors[] = "Anggota harus dipilih";
        }
        
        if($id_buku <= 0) {
            $errors[] = "Buku harus dipilih";
        }
        
        if(empty($tgl_pinjam)) {
            $errors[] = "Tanggal pinjam harus diisi";
        }
        
        if(empty($tgl_jatuh_tempo)) {
            $errors[] = "Tanggal jatuh tempo harus diisi";
        }
        
        if(!in_array($status, ['dipinjam', 'terlambat'])) {
            $errors[] = "Status tidak valid";
        }
        
        // Periksa apakah tgl_jatuh_tempo setelah tgl_pinjam
        if(!empty($tgl_pinjam) && !empty($tgl_jatuh_tempo)) {
            $pinjam_date = new DateTime($tgl_pinjam);
            $tempo_date = new DateTime($tgl_jatuh_tempo);
            
            if($tempo_date <= $pinjam_date) {
                $errors[] = "Tanggal jatuh tempo harus setelah tanggal pinjam";
            }
        }
        
        // Save if no errors
        if(empty($errors)) {
            // Begin transaction
            $conn->beginTransaction();
            
            try {
                // Update loan data
                $update_sql = "UPDATE meminjam SET 
                              id_anggota = :id_anggota,
                              id_buku = :id_buku,
                              tgl_pinjam = :tgl_pinjam,
                              tgl_jatuh_tempo = :tgl_jatuh_tempo,
                              status = :status,
                              keterangan = :keterangan
                              WHERE id_pinjam = :id_pinjam";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bindParam(':id_anggota', $id_anggota, PDO::PARAM_INT);
                $update_stmt->bindParam(':id_buku', $id_buku, PDO::PARAM_INT);
                $update_stmt->bindParam(':tgl_pinjam', $tgl_pinjam);
                $update_stmt->bindParam(':tgl_jatuh_tempo', $tgl_jatuh_tempo);
                $update_stmt->bindParam(':status', $status);
                $update_stmt->bindParam(':keterangan', $keterangan);
                $update_stmt->bindParam(':id_pinjam', $id, PDO::PARAM_INT);
                $update_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['success'] = "Data peminjaman berhasil diperbarui";
                header("Location: detail.php?id=$id");
                exit;
            } catch(PDOException $e) {
                $conn->rollBack();
                $error = "Error: " . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
} catch(PDOException $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header("Location: index.php");
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
        <div class="bg-yellow-600 text-white p-6">
            <h1 class="text-2xl font-bold flex items-center">
                <i class="fas fa-edit mr-3"></i>
                Edit Peminjaman
            </h1>
            <p class="text-yellow-200 mt-2">ID: <?= $id ?></p>
        </div>
        
        <?php if(isset($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                <p><?= $error ?></p>
            </div>
        <?php endif; ?>
        
        <div class="p-6">
            <form method="post" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <div class="mb-4">
                            <label for="id_anggota" class="block text-sm font-medium text-gray-700 mb-1">Anggota</label>
                            <select id="id_anggota" name="id_anggota" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">-- Pilih Anggota --</option>
                                <?php foreach($anggota_list as $anggota): ?>
                                    <option value="<?= $anggota['id_anggota'] ?>" <?= ($loan['id_anggota'] == $anggota['id_anggota']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($anggota['kode_anggota'] . ' - ' . $anggota['nama']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="id_buku" class="block text-sm font-medium text-gray-700 mb-1">Buku</label>
                            <select id="id_buku" name="id_buku" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">-- Pilih Buku --</option>
                                <?php foreach($buku_list as $buku): ?>
                                    <option value="<?= $buku['id_buku'] ?>" <?= ($loan['id_buku'] == $buku['id_buku']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($buku['kode_buku'] . ' - ' . $buku['judul_buku']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <div class="mb-4">
                            <label for="tgl_pinjam" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Pinjam</label>
                            <input type="date" id="tgl_pinjam" name="tgl_pinjam" value="<?= htmlspecialchars($loan['tgl_pinjam']) ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="tgl_jatuh_tempo" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Jatuh Tempo</label>
                            <input type="date" id="tgl_jatuh_tempo" name="tgl_jatuh_tempo" value="<?= htmlspecialchars($loan['tgl_jatuh_tempo']) ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <div class="flex space-x-4">
                        <div class="flex items-center">
                            <input type="radio" id="status_dipinjam" name="status" value="dipinjam" <?= $loan['status'] === 'dipinjam' ? 'checked' : '' ?> class="h-4 w-4 text-indigo-600 border-gray-300">
                            <label for="status_dipinjam" class="ml-2 block text-sm text-gray-700">Dipinjam</label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio" id="status_terlambat" name="status" value="terlambat" <?= $loan['status'] === 'terlambat' ? 'checked' : '' ?> class="h-4 w-4 text-indigo-600 border-gray-300">
                            <label for="status_terlambat" class="ml-2 block text-sm text-gray-700">Terlambat</label>
                        </div>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label for="keterangan" class="block text-sm font-medium text-gray-700 mb-1">Keterangan (opsional)</label>
                    <textarea id="keterangan" name="keterangan" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"><?= htmlspecialchars($loan['keterangan']) ?></textarea>
                </div>
                
                <div class="flex justify-end">
                    <a href="detail.php?id=<?= $id ?>" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded mr-2">
                        Batal
                    </a>
                    <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded">
                        <i class="fas fa-save mr-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>








































