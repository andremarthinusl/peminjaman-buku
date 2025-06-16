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
    // Ambil data peminjaman with related information
    $sql = "SELECT m.*, a.nama as nama_anggota, a.kode_anggota, a.alamat, a.no_telepon,
            b.judul_buku, b.kode_buku, b.penulis, b.penerbit, b.tahun_terbit
            FROM meminjam m
            JOIN anggota a ON m.id_anggota = a.id_anggota
            JOIN buku b ON m.id_buku = b.id_buku
            WHERE m.id_pinjam = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if($stmt->rowCount() == 0) {
        $_SESSION['error'] = "Data peminjaman tidak ditemukan";
        header("Location: index.php");
        exit;
    }
    
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate late days if status is terlambat
    $late_days = 0;
    if($loan['status'] == 'terlambat') {
        $today = new DateTime();
        $due_date = new DateTime($loan['tgl_jatuh_tempo']);
        $diff = $today->diff($due_date);
        $late_days = $diff->days;
    }
    
    // Calculate remaining days if status is dipinjam
    $remaining_days = 0;
    if($loan['status'] == 'dipinjam') {
        $today = new DateTime();
        $due_date = new DateTime($loan['tgl_jatuh_tempo']);
        if($today <= $due_date) {
            $diff = $today->diff($due_date);
            $remaining_days = $diff->days;
        } else {
            // Should be marked as terlambat
            $loan['status'] = 'terlambat';
            
            // Update status in database
            $update_sql = "UPDATE meminjam SET status = 'terlambat' WHERE id_pinjam = :id";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $update_stmt->execute();
            
            // Calculate late days
            $diff = $today->diff($due_date);
            $late_days = $diff->days;
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
        <a href="index.php" class="inline-flex items-center text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i> Kembali ke Daftar Peminjaman
        </a>
    </div>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="bg-indigo-600 text-white p-6">
            <h1 class="text-2xl font-bold flex items-center">
                <i class="fas fa-info-circle mr-3"></i>
                Detail Peminjaman
            </h1>
            <p class="text-indigo-200 mt-2">ID: <?= $loan['id_pinjam'] ?></p>
        </div>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
                <p><?= $_SESSION['success'] ?></p>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                <p><?= $_SESSION['error'] ?></p>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Left Column: Loan Information -->
                <div class="space-y-6">
                    <div class="border rounded-lg overflow-hidden">
                        <div class="bg-gray-50 px-4 py-2 border-b">
                            <h2 class="font-medium text-gray-700">Informasi Peminjaman</h2>
                        </div>
                        <div class="p-4">
                            <table class="w-full">
                                <tr>
                                    <td class="py-2 text-gray-600">Status</td>
                                    <td class="py-2">
                                        <?php if($loan['status'] == 'dipinjam'): ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <i class="fas fa-book-reader mr-1"></i> Dipinjam
                                            </span>
                                            <?php if($remaining_days > 0): ?>
                                                <span class="text-xs text-gray-500 ml-1">(<?= $remaining_days ?> hari lagi)</span>
                                            <?php endif; ?>
                                        <?php elseif($loan['status'] == 'kembali'): ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i> Dikembalikan
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <i class="fas fa-exclamation-circle mr-1"></i> Terlambat
                                            </span>
                                            <?php if($late_days > 0): ?>
                                                <span class="text-xs text-gray-500 ml-1">(<?= $late_days ?> hari)</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-600">Tanggal Pinjam</td>
                                    <td class="py-2"><?= date('d/m/Y', strtotime($loan['tgl_pinjam'])) ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-600">Tanggal Jatuh Tempo</td>
                                    <td class="py-2"><?= date('d/m/Y', strtotime($loan['tgl_jatuh_tempo'])) ?></td>
                                </tr>
                                <?php if($loan['status'] == 'kembali'): ?>
                                <tr>
                                    <td class="py-2 text-gray-600">Tanggal Kembali</td>
                                    <td class="py-2"><?= date('d/m/Y', strtotime($loan['tgl_kembali'])) ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="py-2 text-gray-600">Keterangan</td>
                                    <td class="py-2"><?= htmlspecialchars($loan['keterangan'] ?: '-') ?></td>
                                </tr>
                            </table>
                            
                            <!-- Action buttons -->
                            <div class="mt-6 flex flex-wrap gap-2">
                                <?php if($loan['status'] == 'dipinjam'): ?>
                                    <a href="kembalikan.php?id=<?= $loan['id_pinjam'] ?>" class="bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded">
                                        <i class="fas fa-check-circle mr-1"></i> Kembalikan
                                    </a>
                                <?php endif; ?>
                                
                                <?php if($loan['status'] != 'kembali'): ?>
                                    <a href="edit.php?id=<?= $loan['id_pinjam'] ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white py-2 px-4 rounded">
                                        <i class="fas fa-edit mr-1"></i> Edit
                                    </a>
                                <?php endif; ?>
                                
                                <button onclick="confirmDelete(<?= $loan['id_pinjam'] ?>)" class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded">
                                    <i class="fas fa-trash mr-1"></i> Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Anggota and Book Information -->
                <div class="space-y-6">
                    <!-- Anggota Information -->
                    <div class="border rounded-lg overflow-hidden">
                        <div class="bg-gray-50 px-4 py-2 border-b">
                            <h2 class="font-medium text-gray-700">Informasi Anggota</h2>
                        </div>
                        <div class="p-4">
                            <table class="w-full">
                                <tr>
                                    <td class="py-2 text-gray-600">Kode Anggota</td>
                                    <td class="py-2"><?= htmlspecialchars($loan['kode_anggota']) ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-600">Nama</td>
                                    <td class="py-2"><?= htmlspecialchars($loan['nama_anggota']) ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-600">Alamat</td>
                                    <td class="py-2"><?= htmlspecialchars($loan['alamat'] ?: '-') ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-600">No. Telepon</td>
                                    <td class="py-2"><?= htmlspecialchars($loan['no_telepon'] ?: '-') ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Book Information -->
                    <div class="border rounded-lg overflow-hidden">
                        <div class="bg-gray-50 px-4 py-2 border-b">
                            <h2 class="font-medium text-gray-700">Informasi Buku</h2>
                        </div>
                        <div class="p-4">
                            <table class="w-full">
                                <tr>
                                    <td class="py-2 text-gray-600">Kode Buku</td>
                                    <td class="py-2"><?= htmlspecialchars($loan['kode_buku']) ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-600">Judul</td>
                                    <td class="py-2"><?= htmlspecialchars($loan['judul_buku']) ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-600">Penulis</td>
                                    <td class="py-2"><?= htmlspecialchars($loan['penulis'] ?: '-') ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-600">Penerbit</td>
                                    <td class="py-2"><?= htmlspecialchars($loan['penerbit'] ?: '-') ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-600">Tahun Terbit</td>
                                    <td class="py-2"><?= htmlspecialchars($loan['tahun_terbit'] ?: '-') ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed z-10 inset-0 overflow-y-auto hidden">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Hapus Data Peminjaman</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">Apakah Anda yakin ingin menghapus data peminjaman ini? Tindakan ini tidak dapat dibatalkan.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <a id="confirmDeleteBtn" href="#" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                    Hapus
                </a>
                <button type="button" onclick="closeDeleteModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Batal
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(id) {
        document.getElementById('confirmDeleteBtn').href = 'delete.php?id=' + id;
        document.getElementById('deleteModal').classList.remove('hidden');
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }
</script>

<?php include '../templates/footer.php'; ?>








































