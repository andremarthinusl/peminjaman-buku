<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';

// Periksa apakah pengguna sudah login
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// All users can view book details
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$book = null;
$error = '';

if($id <= 0) {
    $error = "ID buku tidak valid";
} else {
    try {
        // Ambil detail buku
        $sql = "SELECT * FROM buku WHERE id_buku = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $book = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$book) {
            $error = "Buku tidak ditemukan";
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Include header
include '../templates/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="mb-6">
        <a href="index.php" class="inline-flex items-center text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i> Kembali ke Daftar Buku
        </a>
    </div>
    
    <?php if($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?= $error ?></p>
        </div>
    <?php elseif($book): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-indigo-600 text-white p-6">
                <h1 class="text-2xl font-bold"><?= htmlspecialchars($book['judul_buku']) ?></h1>
                <p class="text-indigo-200 mt-2">Kode: <?= htmlspecialchars($book['kode_buku']) ?></p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Detail Buku</h2>
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm text-gray-500">Judul Buku</p>
                                <p class="font-medium"><?= htmlspecialchars($book['judul_buku']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Penulis</p>
                                <p class="font-medium"><?= htmlspecialchars($book['penulis']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Penerbit</p>
                                <p class="font-medium"><?= htmlspecialchars($book['penerbit']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Tahun Terbit</p>
                                <p class="font-medium"><?= htmlspecialchars($book['tahun_terbit']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Stok</p>
                                <p class="font-medium">
                                    <?= (int)$book['stok'] ?>
                                    <?php if((int)$book['stok'] <= 0): ?>
                                        <span class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">Habis</span>
                                    <?php elseif((int)$book['stok'] < 5): ?>
                                        <span class="ml-2 px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">Stok Sedikit</span>
                                    <?php else: ?>
                                        <span class="ml-2 px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Tersedia</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>                    
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Aksi</h2>
                        <div class="space-y-3">
                            <?php if(checkAccess([1, 2])): // Admin and Pustakawan only ?>
                                <a href="edit.php?id=<?= $book['id_buku'] ?>" class="inline-block w-full bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">
                                    <i class="fas fa-edit mr-2"></i> Edit Buku
                                </a>
                                <button onclick="confirmDelete(<?= $book['id_buku'] ?>)" class="inline-block w-full bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                                    <i class="fas fa-trash mr-2"></i> Hapus Buku
                                </button>
                                <?php if((int)$book['stok'] > 0): ?>
                                <a href="../pinjam/tambah.php?id_buku=<?= $book['id_buku'] ?>" class="inline-block w-full bg-indigo-500 text-white px-4 py-2 rounded hover:bg-indigo-600">
                                    <i class="fas fa-book-reader mr-2"></i> Tambah Peminjaman
                                </a>
                                <?php endif; ?>
                            <?php elseif($_SESSION['role'] == 3): // Anggota options ?>
                                <?php if((int)$book['stok'] > 0): ?>
                                <button class="inline-block w-full bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                                    <i class="fas fa-heart mr-2"></i> Tambahkan ke Favorit
                                </button>
                                <?php endif; ?>
                                <a href="../riwayatpinjam/" class="inline-block w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                    <i class="fas fa-history mr-2"></i> Lihat Riwayat Pinjaman
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if(checkAccess([1, 2])): // Only show the delete confirmation for Admin and Pustakawan ?>
<script>
function confirmDelete(id) {
    if(confirm("Apakah Anda yakin ingin menghapus buku ini?")) {
        window.location.href = "delete.php?id=" + id;
    }
}
</script>
<?php endif; ?>

<?php include '../templates/footer.php'; ?>








































