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
$book = null;
$error = '';
$success = '';

if($id <= 0) {
    $error = "ID buku tidak valid";
} else {
    try {
        // If form submitted
        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $kode_buku = trim($_POST['kode_buku']);
            $judul_buku = trim($_POST['judul_buku']);
            $penulis = trim($_POST['penulis']);
            $penerbit = trim($_POST['penerbit']);
            $tahun_terbit = trim($_POST['tahun_terbit']);
            $stok = (int)$_POST['stok'];
            
            // Validasi
            if(empty($kode_buku) || empty($judul_buku)) {
                $error = "Kode buku dan judul buku tidak boleh kosong";
            } elseif($stok < 0) {
                $error = "Stok tidak boleh negatif";
            } else {
                // Periksa apakah kode_buku sudah ada for another book
                $check_sql = "SELECT id_buku FROM buku WHERE kode_buku = :kode_buku AND id_buku != :id";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bindParam(':kode_buku', $kode_buku);
                $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $check_stmt->execute();
                
                if($check_stmt->rowCount() > 0) {
                    $error = "Kode buku sudah digunakan oleh buku lain";
                } else {
                    // Update book data
                    $update_sql = "UPDATE buku SET 
                                kode_buku = :kode_buku,
                                judul_buku = :judul_buku,
                                penulis = :penulis,
                                penerbit = :penerbit,
                                tahun_terbit = :tahun_terbit,
                                stok = :stok
                                WHERE id_buku = :id";
                                
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bindParam(':kode_buku', $kode_buku);
                    $update_stmt->bindParam(':judul_buku', $judul_buku);
                    $update_stmt->bindParam(':penulis', $penulis);
                    $update_stmt->bindParam(':penerbit', $penerbit);
                    $update_stmt->bindParam(':tahun_terbit', $tahun_terbit);
                    $update_stmt->bindParam(':stok', $stok, PDO::PARAM_INT);
                    $update_stmt->bindParam(':id', $id, PDO::PARAM_INT);
                    
                    if($update_stmt->execute()) {
                        $success = "Buku berhasil diperbarui";
                    } else {
                        $error = "Gagal memperbarui buku";
                    }
                }
            }
        }
        
        // Ambil data buku
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
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="bg-indigo-600 text-white p-6">
            <h1 class="text-2xl font-bold">Edit Buku</h1>
            <p class="text-indigo-200 mt-2">Perbarui informasi buku</p>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                <p><?= $error ?></p>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
                <p><?= $success ?></p>
            </div>
        <?php endif; ?>
        
        <?php if($book): ?>
            <div class="p-6">
                <form method="post" action="">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="mb-4">
                            <label for="kode_buku" class="block text-sm font-medium text-gray-700 mb-1">Kode Buku</label>
                            <input type="text" name="kode_buku" id="kode_buku" value="<?= htmlspecialchars($book['kode_buku']) ?>" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="judul_buku" class="block text-sm font-medium text-gray-700 mb-1">Judul Buku</label>
                            <input type="text" name="judul_buku" id="judul_buku" value="<?= htmlspecialchars($book['judul_buku']) ?>" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="penulis" class="block text-sm font-medium text-gray-700 mb-1">Penulis</label>
                            <input type="text" name="penulis" id="penulis" value="<?= htmlspecialchars($book['penulis']) ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="penerbit" class="block text-sm font-medium text-gray-700 mb-1">Penerbit</label>
                            <input type="text" name="penerbit" id="penerbit" value="<?= htmlspecialchars($book['penerbit']) ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="tahun_terbit" class="block text-sm font-medium text-gray-700 mb-1">Tahun Terbit</label>
                            <input type="number" name="tahun_terbit" id="tahun_terbit" value="<?= htmlspecialchars($book['tahun_terbit']) ?>" min="1900" max="<?= date('Y') ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="stok" class="block text-sm font-medium text-gray-700 mb-1">Stok</label>
                            <input type="number" name="stok" id="stok" value="<?= (int)$book['stok'] ?>" min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div class="mt-6 flex items-center justify-end">
                        <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded-md mr-2">
                            Batal
                        </a>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-md">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../templates/footer.php'; ?>








































