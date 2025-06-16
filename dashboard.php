<?php
session_start();
require_once 'config/db.php';
require_once 'config/auth.php';

// Periksa apakah pengguna sudah login
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Ambil data jumlah untuk dashboard
try {    // Hitung jumlah buku
    $book_query = "SELECT COUNT(*) as total_books FROM buku";
    $book_stmt = $conn->query($book_query);
    $total_books = $book_stmt->fetch(PDO::FETCH_ASSOC)['total_books'];
    
    // Hanya untuk Admin dan Pustakawan
    if(checkAccess([1, 2])) {        // Hitung jumlah anggota
        $member_query = "SELECT COUNT(*) as total_members FROM anggota";
        $member_stmt = $conn->query($member_query);
        $total_members = $member_stmt->fetch(PDO::FETCH_ASSOC)['total_members'];
        
        // Hitung peminjaman aktif
        $active_loan_query = "SELECT COUNT(*) as total_active FROM meminjam WHERE status = 'dipinjam'";
        $active_loan_stmt = $conn->query($active_loan_query);
        $total_active = $active_loan_stmt->fetch(PDO::FETCH_ASSOC)['total_active'];
          // Hitung buku yang sudah dikembalikan
        $returned_query = "SELECT COUNT(*) as total_returned FROM meminjam WHERE status = 'kembali'";
        $returned_stmt = $conn->query($returned_query);
        $total_returned = $returned_stmt->fetch(PDO::FETCH_ASSOC)['total_returned'];
        
        // Hitung keterlambatan pengembalian
        $late_query = "SELECT COUNT(*) as total_late FROM meminjam WHERE status = 'terlambat'";
        $late_stmt = $conn->query($late_query);
        $total_late = $late_stmt->fetch(PDO::FETCH_ASSOC)['total_late'];
    }      // Untuk anggota biasa, hanya tampilkan statistik mereka sendiri
    if($_SESSION['role'] == 3) { // Anggota
        $user_id = $_SESSION['user_id'];
          // Untuk Anggota, gunakan nama untuk pencocokan daripada user_id karena mungkin tidak ada link langsung
        $username = $_SESSION['nama'];
        
        // Dapatkan ID anggota berdasarkan nama
        $anggota_query = "SELECT id_anggota FROM anggota WHERE nama = :nama";
        $anggota_stmt = $conn->prepare($anggota_query);
        $anggota_stmt->bindParam(':nama', $username);
        $anggota_stmt->execute();
        $anggota_result = $anggota_stmt->fetch(PDO::FETCH_ASSOC);
        
        if($anggota_result) {
            $id_anggota = $anggota_result['id_anggota'];
              // Hitung peminjaman aktif anggota
            $active_loan_query = "SELECT COUNT(*) as total_active FROM meminjam WHERE id_anggota = :id_anggota AND status = 'dipinjam'";
            $active_loan_stmt = $conn->prepare($active_loan_query);
            $active_loan_stmt->bindParam(':id_anggota', $id_anggota, PDO::PARAM_INT);
            $active_loan_stmt->execute();
            $total_active = $active_loan_stmt->fetch(PDO::FETCH_ASSOC)['total_active'];
            
            // Hitung buku yang sudah dikembalikan oleh anggota
            $returned_query = "SELECT COUNT(*) as total_returned FROM meminjam WHERE id_anggota = :id_anggota AND status = 'kembali'";
            $returned_stmt = $conn->prepare($returned_query);
            $returned_stmt->bindParam(':id_anggota', $id_anggota, PDO::PARAM_INT);
            $returned_stmt->execute();
            $total_returned = $returned_stmt->fetch(PDO::FETCH_ASSOC)['total_returned'];
              // Hitung keterlambatan pengembalian oleh anggota
            $late_query = "SELECT COUNT(*) as total_late FROM meminjam WHERE id_anggota = :id_anggota AND status = 'terlambat'";
            $late_stmt = $conn->prepare($late_query);
            $late_stmt->bindParam(':id_anggota', $id_anggota, PDO::PARAM_INT);
            $late_stmt->execute();
            $total_late = $late_stmt->fetch(PDO::FETCH_ASSOC)['total_late'];
        }
    }
      // Peminjaman terbaru (5 terakhir)
    // Ubah query berdasarkan peran pengguna
    if(checkAccess([1, 2])) { // Admin dan Pustakawan melihat semua peminjaman
        $recent_query = "SELECT m.id_pinjam, m.tgl_pinjam, m.tgl_kembali, m.status, 
                      a.nama as nama_anggota, b.judul_buku 
                      FROM meminjam m
                      JOIN anggota a ON m.id_anggota = a.id_anggota
                      JOIN buku b ON m.id_buku = b.id_buku
                      ORDER BY m.tgl_pinjam DESC
                      LIMIT 5";
        $recent_stmt = $conn->query($recent_query);    } else { // Anggota hanya melihat peminjamannya sendiri
        $username = $_SESSION['nama'];
        
        $recent_query = "SELECT m.id_pinjam, m.tgl_pinjam, m.tgl_kembali, m.status, 
                      a.nama as nama_anggota, b.judul_buku 
                      FROM meminjam m
                      JOIN anggota a ON m.id_anggota = a.id_anggota
                      JOIN buku b ON m.id_buku = b.id_buku
                      WHERE a.nama = :nama
                      ORDER BY m.tgl_pinjam DESC
                      LIMIT 5";
        $recent_stmt = $conn->prepare($recent_query);
        $recent_stmt->bindParam(':nama', $username);
        $recent_stmt->execute();
    }
    
    $recent_loans = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// Show error or success messages if they exist
if(isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if(isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Include header
include 'templates/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-tachometer-alt mr-2 text-indigo-600"></i> Dashboard
        </h1>
        <div class="mt-4 md:mt-0">
            <p class="text-gray-600">Selamat datang, <span class="font-medium"><?= $_SESSION['nama'] ?></span> (<?= $_SESSION['role_name'] ?>)</p>
        </div>
    </div>
    
    <?php if(isset($error)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p><?= $error ?></p>
    </div>
    <?php endif; ?>
    
    <?php if(isset($success)): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
        <p><?= $success ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-<?= checkAccess([1, 2]) ? '5' : '3' ?> gap-4 mb-6">
        <!-- Books - Visible to all roles -->
        <div class="bg-white rounded-lg shadow-md p-5 border-t-4 border-blue-500 hover:shadow-lg transition duration-300">
            <div class="flex justify-between">
                <div>
                    <h3 class="text-gray-500 text-sm">Total Buku</h3>
                    <p class="text-2xl font-bold text-gray-800"><?= $total_books ?></p>
                </div>
                <div class="rounded-full bg-blue-100 p-3">
                    <i class="fas fa-book text-blue-500 text-xl"></i>
                </div>
            </div>
            <a href="buku/" class="text-blue-500 hover:text-blue-600 text-sm flex items-center mt-4">
                <span>Lihat Detail</span>
                <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <?php if(checkAccess([1, 2])): // Only for Admin and Pustakawan ?>
        <!-- Members -->
        <div class="bg-white rounded-lg shadow-md p-5 border-t-4 border-green-500 hover:shadow-lg transition duration-300">
            <div class="flex justify-between">
                <div>
                    <h3 class="text-gray-500 text-sm">Total Anggota</h3>
                    <p class="text-2xl font-bold text-gray-800"><?= $total_members ?></p>
                </div>
                <div class="rounded-full bg-green-100 p-3">
                    <i class="fas fa-users text-green-500 text-xl"></i>
                </div>
            </div>
            <a href="anggota/" class="text-green-500 hover:text-green-600 text-sm flex items-center mt-4">
                <span>Lihat Detail</span>
                <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Active Loans -->
        <div class="bg-white rounded-lg shadow-md p-5 border-t-4 border-yellow-500 hover:shadow-lg transition duration-300">
            <div class="flex justify-between">
                <div>
                    <h3 class="text-gray-500 text-sm"><?= $_SESSION['role'] == 3 ? 'Anda Sedang Meminjam' : 'Sedang Dipinjam' ?></h3>
                    <p class="text-2xl font-bold text-gray-800"><?= isset($total_active) ? $total_active : '0' ?></p>
                </div>
                <div class="rounded-full bg-yellow-100 p-3">
                    <i class="fas fa-book-reader text-yellow-500 text-xl"></i>
                </div>
            </div>
            <a href="<?= $_SESSION['role'] == 3 ? 'riwayatpinjam/?filter=dipinjam' : 'pinjam/' ?>" class="text-yellow-500 hover:text-yellow-600 text-sm flex items-center mt-4">
                <span>Lihat Detail</span>
                <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <!-- Returned Books -->
        <div class="bg-white rounded-lg shadow-md p-5 border-t-4 border-indigo-500 hover:shadow-lg transition duration-300">
            <div class="flex justify-between">
                <div>
                    <h3 class="text-gray-500 text-sm"><?= $_SESSION['role'] == 3 ? 'Buku Yang Sudah Anda Kembalikan' : 'Sudah Dikembalikan' ?></h3>
                    <p class="text-2xl font-bold text-gray-800"><?= isset($total_returned) ? $total_returned : '0' ?></p>
                </div>
                <div class="rounded-full bg-indigo-100 p-3">
                    <i class="fas fa-check-circle text-indigo-500 text-xl"></i>
                </div>
            </div>
            <a href="riwayatpinjam/" class="text-indigo-500 hover:text-indigo-600 text-sm flex items-center mt-4">
                <span>Lihat Detail</span>
                <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <!-- Late Returns -->
        <div class="bg-white rounded-lg shadow-md p-5 border-t-4 border-red-500 hover:shadow-lg transition duration-300">
            <div class="flex justify-between">
                <div>
                    <h3 class="text-gray-500 text-sm"><?= $_SESSION['role'] == 3 ? 'Peminjaman Terlambat Anda' : 'Terlambat' ?></h3>
                    <p class="text-2xl font-bold text-gray-800"><?= isset($total_late) ? $total_late : '0' ?></p>
                </div>
                <div class="rounded-full bg-red-100 p-3">
                    <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                </div>
            </div>
            <a href="<?= $_SESSION['role'] == 3 ? 'riwayatpinjam/?filter=terlambat' : 'pinjam/?filter=terlambat' ?>" class="text-red-500 hover:text-red-600 text-sm flex items-center mt-4">
                <span>Lihat Detail</span>
                <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
    
    <!-- Recent Loans -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">
            <i class="fas fa-clock mr-2 text-indigo-600"></i> 
            <?= $_SESSION['role'] == 3 ? 'Riwayat Peminjaman Anda' : 'Peminjaman Terbaru' ?>
        </h2>
        
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead>
                    <tr>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tanggal Pinjam</th>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tanggal Kembali</th>
                        <?php if(checkAccess([1, 2])): // Only for Admin and Pustakawan ?>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Peminjam</th>
                        <?php endif; ?>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Buku</th>
                        <th class="py-3 px-4 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if(count($recent_loans) > 0): ?>
                        <?php foreach($recent_loans as $loan): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-4 text-sm"><?= $loan['id_pinjam'] ?></td>
                                <td class="py-3 px-4 text-sm"><?= date('d/m/Y', strtotime($loan['tgl_pinjam'])) ?></td>
                                <td class="py-3 px-4 text-sm">
                                    <?= $loan['tgl_kembali'] ? date('d/m/Y', strtotime($loan['tgl_kembali'])) : '-' ?>
                                </td>
                                <?php if(checkAccess([1, 2])): // Only for Admin and Pustakawan ?>
                                <td class="py-3 px-4 text-sm"><?= $loan['nama_anggota'] ?></td>
                                <?php endif; ?>
                                <td class="py-3 px-4 text-sm"><?= $loan['judul_buku'] ?></td>
                                <td class="py-3 px-4 text-sm">
                                    <?php if($loan['status'] == 'dipinjam'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Dipinjam
                                        </span>
                                    <?php elseif($loan['status'] == 'kembali'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Dikembalikan
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Terlambat
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= checkAccess([1, 2]) ? '6' : '5' ?>" class="py-3 px-4 text-center text-sm text-gray-500">Tidak ada data peminjaman terbaru</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-4 text-center">
            <a href="<?= $_SESSION['role'] == 3 ? 'riwayatpinjam/' : 'pinjam/' ?>" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                <i class="fas fa-list-ul mr-2"></i>
                <?= $_SESSION['role'] == 3 ? 'Lihat Semua Riwayat Peminjaman' : 'Lihat Semua Peminjaman' ?>
            </a>
        </div>
    </div>
      <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-<?= checkAccess([1, 2]) ? '4' : '2' ?> gap-4">
        <?php if(checkAccess([1, 2])): // Only for Admin and Pustakawan ?>
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300 card-hover">
            <div class="text-center">
                <div class="rounded-full bg-blue-100 p-4 inline-block">
                    <i class="fas fa-book-medical text-blue-500 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold mt-3">Tambah Buku</h3>
                <p class="text-gray-600 text-sm mt-2">Tambahkan data buku baru ke sistem</p>
                <a href="buku/tambah.php" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-500 hover:bg-blue-600">
                    <i class="fas fa-plus mr-2"></i> Tambah Buku
                </a>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300 card-hover">
            <div class="text-center">
                <div class="rounded-full bg-green-100 p-4 inline-block">
                    <i class="fas fa-user-plus text-green-500 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold mt-3">Tambah Anggota</h3>
                <p class="text-gray-600 text-sm mt-2">Tambahkan data anggota perpustakaan</p>
                <a href="anggota/tambah.php" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-500 hover:bg-green-600">
                    <i class="fas fa-plus mr-2"></i> Tambah Anggota
                </a>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300 card-hover">
            <div class="text-center">
                <div class="rounded-full bg-yellow-100 p-4 inline-block">
                    <i class="fas fa-hand-holding-book text-yellow-500 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold mt-3">Peminjaman Baru</h3>
                <p class="text-gray-600 text-sm mt-2">Catat peminjaman buku baru</p>
                <a href="pinjam/tambah.php" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-yellow-500 hover:bg-yellow-600">
                    <i class="fas fa-plus mr-2"></i> Tambah Peminjaman
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- For all roles -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300 card-hover">
            <div class="text-center">
                <div class="rounded-full bg-indigo-100 p-4 inline-block">
                    <i class="fas fa-book text-indigo-500 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold mt-3">Katalog Buku</h3>
                <p class="text-gray-600 text-sm mt-2">Lihat daftar buku yang tersedia</p>
                <a href="buku/" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-500 hover:bg-indigo-600">
                    <i class="fas fa-list mr-2"></i> Lihat Katalog
                </a>
            </div>
        </div>
          <!-- For regular members -->
        <?php if($_SESSION['role'] == 3): ?>
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300 card-hover">
            <div class="text-center">
                <div class="rounded-full bg-purple-100 p-4 inline-block">
                    <i class="fas fa-history text-purple-500 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold mt-3">Riwayat Peminjaman</h3>
                <p class="text-gray-600 text-sm mt-2">Lihat semua peminjaman Anda</p>
                <a href="riwayatpinjam/" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-purple-500 hover:bg-purple-600">
                    <i class="fas fa-history mr-2"></i> Lihat Riwayat
                </a>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300 card-hover">
            <div class="text-center">
                <div class="rounded-full bg-teal-100 p-4 inline-block">
                    <i class="fas fa-user text-teal-500 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold mt-3">Profil Saya</h3>
                <p class="text-gray-600 text-sm mt-2">Edit informasi profil dan akun Anda</p>
                <a href="profile/" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-teal-500 hover:bg-teal-600">
                    <i class="fas fa-user-edit mr-2"></i> Edit Profil
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'templates/footer.php'; ?>








































