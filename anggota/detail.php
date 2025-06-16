<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';

// Periksa apakah pengguna sudah login
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// All roles can view anggota details

// Ambil ID anggota dari parameter URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id <= 0) {
    $_SESSION['error'] = "ID Anggota tidak valid";
    header("Location: index.php");
    exit;
}

try {
    // Ambil data anggota
    $sql = "SELECT * FROM anggota WHERE id_anggota = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if($stmt->rowCount() == 0) {
        $_SESSION['error'] = "Anggota tidak ditemukan";
        header("Location: index.php");
        exit;
    }
    
    $anggota = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Periksa apakah anggota terhubung dengan akun pengguna
    $check_user_sql = "SELECT u.* FROM users u 
                      WHERE u.nama_lengkap = :nama";
    $check_user_stmt = $conn->prepare($check_user_sql);
    $check_user_stmt->bindParam(':nama', $anggota['nama']);
    $check_user_stmt->execute();
    $linked_user = $check_user_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get peminjaman history for this anggota
    $pinjam_sql = "SELECT m.*, b.judul_buku, b.kode_buku 
                  FROM meminjam m 
                  JOIN buku b ON m.id_buku = b.id_buku
                  WHERE m.id_anggota = :id_anggota
                  ORDER BY m.tgl_pinjam DESC
                  LIMIT 5";
    $pinjam_stmt = $conn->prepare($pinjam_sql);
    $pinjam_stmt->bindParam(':id_anggota', $id, PDO::PARAM_INT);
    $pinjam_stmt->execute();
    $peminjaman = $pinjam_stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
        <a href="index.php" class="inline-flex items-center text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i> Kembali ke Daftar Anggota
        </a>
    </div>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="bg-indigo-600 text-white p-6">
            <h1 class="text-2xl font-bold flex items-center">
                <i class="fas fa-user mr-3"></i>
                Detail Anggota
            </h1>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Anggota Info -->
                <div class="lg:col-span-2">
                    <div class="border rounded-lg overflow-hidden">
                        <div class="bg-gray-50 px-4 py-2 border-b">
                            <h2 class="text-lg font-medium text-gray-700">Informasi Anggota</h2>
                        </div>
                        <div class="p-4">
                            <table class="w-full">
                                <tr>
                                    <td class="py-2 text-gray-600 w-1/3">Kode Anggota</td>
                                    <td class="py-2 font-medium"><?= htmlspecialchars($anggota['kode_anggota']) ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-600">Nama</td>
                                    <td class="py-2 font-medium"><?= htmlspecialchars($anggota['nama']) ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-600">Alamat</td>
                                    <td class="py-2"><?= htmlspecialchars($anggota['alamat'] ?: '-') ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-600">No. Telepon</td>
                                    <td class="py-2"><?= htmlspecialchars($anggota['no_telepon'] ?: '-') ?></td>
                                </tr>
                                <?php if($linked_user): ?>
                                <tr>
                                    <td class="py-2 text-gray-600">Status Akun</td>
                                    <td class="py-2">
                                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                            <i class="fas fa-link mr-1"></i> Terhubung dengan Akun User
                                        </span>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>
                            
                            <div class="mt-6 flex space-x-2">
                                <?php if($_SESSION['role'] != 3): // Not for Anggota ?>
                                <a href="edit.php?id=<?= $anggota['id_anggota'] ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white py-2 px-4 rounded">
                                    <i class="fas fa-edit mr-1"></i> Edit
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activities -->
                <div>
                    <div class="border rounded-lg overflow-hidden">
                        <div class="bg-gray-50 px-4 py-2 border-b">
                            <h2 class="text-lg font-medium text-gray-700">Peminjaman Terakhir</h2>
                        </div>
                        <div class="p-4">
                            <?php if(count($peminjaman) > 0): ?>
                                <div class="space-y-3">
                                    <?php foreach($peminjaman as $pinjam): ?>
                                        <div class="border rounded p-3">
                                            <div class="font-medium"><?= htmlspecialchars($pinjam['judul_buku']) ?></div>
                                            <div class="text-sm text-gray-500">Kode: <?= htmlspecialchars($pinjam['kode_buku']) ?></div>
                                            <div class="flex justify-between mt-2 text-xs">
                                                <span>Dipinjam: <?= date('d/m/Y', strtotime($pinjam['tgl_pinjam'])) ?></span>
                                                <?php if($pinjam['status'] == 'kembali'): ?>
                                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded">Dikembalikan</span>
                                                <?php elseif($pinjam['status'] == 'terlambat'): ?>
                                                    <span class="px-2 py-1 bg-red-100 text-red-800 rounded">Terlambat</span>
                                                <?php else: ?>
                                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded">Dipinjam</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-4 text-center">
                                    <a href="../riwayatpinjam/index.php?search=<?= urlencode($anggota['nama']) ?>" class="text-indigo-600 hover:text-indigo-800 text-sm">
                                        Lihat Semua Riwayat <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4 text-gray-500">
                                    <i class="fas fa-book-open text-4xl mb-3"></i>
                                    <p>Belum ada riwayat peminjaman</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>








































