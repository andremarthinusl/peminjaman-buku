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

// Proses pengiriman formulir
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
        try {
            // Periksa apakah kode_buku sudah ada
            $check_sql = "SELECT id_buku FROM buku WHERE kode_buku = :kode_buku";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(':kode_buku', $kode_buku);
            $check_stmt->execute();
            
            if($check_stmt->rowCount() > 0) {
                $error = "Kode buku sudah digunakan";
            } else {
                // Insert new buku
                $insert_sql = "INSERT INTO buku (kode_buku, judul_buku, penulis, penerbit, tahun_terbit, stok) 
                               VALUES (:kode_buku, :judul_buku, :penulis, :penerbit, :tahun_terbit, :stok)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bindParam(':kode_buku', $kode_buku);
                $insert_stmt->bindParam(':judul_buku', $judul_buku);
                $insert_stmt->bindParam(':penulis', $penulis);
                $insert_stmt->bindParam(':penerbit', $penerbit);
                $insert_stmt->bindParam(':tahun_terbit', $tahun_terbit);
                $insert_stmt->bindParam(':stok', $stok);
                $insert_stmt->execute();
                
                $_SESSION['success'] = "Data buku berhasil ditambahkan";
                header("Location: index.php");
                exit;
            }
        } catch(PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Generate next kode_buku
try {
    $last_code_sql = "SELECT MAX(CAST(SUBSTRING(kode_buku, 2) AS UNSIGNED)) as last_num FROM buku WHERE kode_buku LIKE 'B%'";
    $last_code_stmt = $conn->query($last_code_sql);
    $last_num = $last_code_stmt->fetch(PDO::FETCH_ASSOC)['last_num'];
    
    $next_num = ($last_num) ? $last_num + 1 : 1;
    $suggested_code = 'B' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
    $suggested_code = 'B001';
}

// Include header
include '../templates/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-book-medical mr-2 text-indigo-600"></i> Tambah Buku
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
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="kode_buku" class="block text-sm font-medium text-gray-700 mb-2">
                        Kode Buku <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="kode_buku" id="kode_buku" value="<?= htmlspecialchars($suggested_code ?? '') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                    <p class="text-xs text-gray-500 mt-1">Format: B001, B002, dst.</p>
                </div>
                
                <div>
                    <label for="judul_buku" class="block text-sm font-medium text-gray-700 mb-2">
                        Judul Buku <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="judul_buku" id="judul_buku" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                
                <div>
                    <label for="penulis" class="block text-sm font-medium text-gray-700 mb-2">
                        Penulis
                    </label>
                    <input type="text" name="penulis" id="penulis" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                
                <div>
                    <label for="penerbit" class="block text-sm font-medium text-gray-700 mb-2">
                        Penerbit
                    </label>
                    <input type="text" name="penerbit" id="penerbit" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                
                <div>
                    <label for="tahun_terbit" class="block text-sm font-medium text-gray-700 mb-2">
                        Tahun Terbit
                    </label>
                    <input type="number" name="tahun_terbit" id="tahun_terbit" min="1900" max="<?= date('Y') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                
                <div>
                    <label for="stok" class="block text-sm font-medium text-gray-700 mb-2">
                        Stok <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="stok" id="stok" min="0" value="1" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
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








































