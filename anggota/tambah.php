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
    $kode_anggota = trim($_POST['kode_anggota']);
    $nama = trim($_POST['nama']);
    $alamat = trim($_POST['alamat']);
    $no_telepon = trim($_POST['no_telepon']);
    
    // Validasi
    if(empty($kode_anggota) || empty($nama)) {
        $error = "Kode anggota dan nama tidak boleh kosong";
    } else {
        try {
            // Periksa apakah kode_anggota sudah ada
            $check_sql = "SELECT id_anggota FROM anggota WHERE kode_anggota = :kode_anggota";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(':kode_anggota', $kode_anggota);
            $check_stmt->execute();
            
            if($check_stmt->rowCount() > 0) {
                $error = "Kode anggota sudah digunakan";
            } else {
                // Insert new anggota
                $insert_sql = "INSERT INTO anggota (kode_anggota, nama, alamat, no_telepon) 
                               VALUES (:kode_anggota, :nama, :alamat, :no_telepon)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bindParam(':kode_anggota', $kode_anggota);
                $insert_stmt->bindParam(':nama', $nama);
                $insert_stmt->bindParam(':alamat', $alamat);
                $insert_stmt->bindParam(':no_telepon', $no_telepon);
                $insert_stmt->execute();
                
                $_SESSION['success'] = "Data anggota berhasil ditambahkan";
                header("Location: index.php");
                exit;
            }
        } catch(PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Generate next kode_anggota
try {
    $last_code_sql = "SELECT MAX(CAST(SUBSTRING(kode_anggota, 2) AS UNSIGNED)) as last_num FROM anggota WHERE kode_anggota LIKE 'A%'";
    $last_code_stmt = $conn->query($last_code_sql);
    $last_num = $last_code_stmt->fetch(PDO::FETCH_ASSOC)['last_num'];
    
    $next_num = ($last_num) ? $last_num + 1 : 1;
    $suggested_code = 'A' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
    $suggested_code = 'A001';
}

// Include header
include '../templates/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-user-plus mr-2 text-indigo-600"></i> Tambah Anggota
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
                    <label for="kode_anggota" class="block text-sm font-medium text-gray-700 mb-2">
                        Kode Anggota <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="kode_anggota" id="kode_anggota" value="<?= htmlspecialchars($suggested_code ?? '') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                    <p class="text-xs text-gray-500 mt-1">Format: A001, A002, dst.</p>
                </div>
                
                <div>
                    <label for="nama" class="block text-sm font-medium text-gray-700 mb-2">
                        Nama Lengkap <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nama" id="nama" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                
                <div class="md:col-span-2">
                    <label for="alamat" class="block text-sm font-medium text-gray-700 mb-2">
                        Alamat
                    </label>
                    <textarea name="alamat" id="alamat" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>
                
                <div>
                    <label for="no_telepon" class="block text-sm font-medium text-gray-700 mb-2">
                        Nomor Telepon
                    </label>
                    <input type="text" name="no_telepon" id="no_telepon" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
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








































