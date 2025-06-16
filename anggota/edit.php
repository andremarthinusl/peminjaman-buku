<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';

// Periksa apakah pengguna sudah login
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Only Admin and Pustakawan can access this page
requireAccess([1, 2]);

// Ambil ID anggota dari parameter URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id <= 0) {
    $_SESSION['error'] = "ID Anggota tidak valid";
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

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
    
    // Proses pengiriman formulir
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        $kode_anggota = trim($_POST['kode_anggota']);
        $nama = trim($_POST['nama']);
        $alamat = trim($_POST['alamat']);
        $no_telepon = trim($_POST['no_telepon']);
        
        // Validasi
        if(empty($kode_anggota) || empty($nama)) {
            $error = "Kode anggota dan nama harus diisi";
        } else {
            // Periksa apakah kode sudah digunakan oleh anggota lain
            if($kode_anggota != $anggota['kode_anggota']) {
                $check_code = "SELECT id_anggota FROM anggota WHERE kode_anggota = :kode AND id_anggota != :id";
                $check_stmt = $conn->prepare($check_code);
                $check_stmt->bindParam(':kode', $kode_anggota);
                $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $check_stmt->execute();
                
                if($check_stmt->rowCount() > 0) {
                    $error = "Kode anggota sudah digunakan";
                }
            }
            
            if(empty($error)) {
                // Begin transaction
                $conn->beginTransaction();
                
                // Update anggota data
                $update_sql = "UPDATE anggota 
                               SET kode_anggota = :kode_anggota, 
                                   nama = :nama, 
                                   alamat = :alamat, 
                                   no_telepon = :no_telepon 
                               WHERE id_anggota = :id";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bindParam(':kode_anggota', $kode_anggota);
                $update_stmt->bindParam(':nama', $nama);
                $update_stmt->bindParam(':alamat', $alamat);
                $update_stmt->bindParam(':no_telepon', $no_telepon);
                $update_stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $update_stmt->execute();
                
                // If linked to user account, update user name as well
                if($linked_user) {
                    $update_user_sql = "UPDATE users SET nama_lengkap = :nama WHERE id_user = :user_id";
                    $update_user_stmt = $conn->prepare($update_user_sql);
                    $update_user_stmt->bindParam(':nama', $nama);
                    $update_user_stmt->bindParam(':user_id', $linked_user['id_user'], PDO::PARAM_INT);
                    $update_user_stmt->execute();
                }
                
                // Commit transaction
                $conn->commit();
                
                $success = "Data anggota berhasil diperbarui";
                
                // Refresh anggota data
                $stmt->execute();
                $anggota = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Refresh linked user data if exists
                if($linked_user) {
                    $check_user_stmt->bindParam(':nama', $anggota['nama']);
                    $check_user_stmt->execute();
                    $linked_user = $check_user_stmt->fetch(PDO::FETCH_ASSOC);
                }
            }
        }
    }
} catch(PDOException $e) {
    // Rollback transaction on error
    if($conn->inTransaction()) {
        $conn->rollback();
    }
    $error = "Error: " . $e->getMessage();
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
                <i class="fas fa-user-edit mr-3"></i>
                Edit Anggota
            </h1>
            <?php if($linked_user): ?>
            <p class="text-sm text-indigo-200 mt-2">
                <i class="fas fa-info-circle mr-1"></i> Anggota ini terhubung dengan akun user. Perubahan nama akan mempengaruhi akun tersebut.
            </p>
            <?php endif; ?>
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
        
        <form method="post" action="" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <div class="mb-4">
                        <label for="kode_anggota" class="block text-sm font-medium text-gray-700 mb-1">Kode Anggota</label>
                        <input type="text" id="kode_anggota" name="kode_anggota" value="<?= htmlspecialchars($anggota['kode_anggota']) ?>" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    
                    <div class="mb-4">
                        <label for="nama" class="block text-sm font-medium text-gray-700 mb-1">Nama</label>
                        <input type="text" id="nama" name="nama" value="<?= htmlspecialchars($anggota['nama']) ?>" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
                
                <div>
                    <div class="mb-4">
                        <label for="alamat" class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                        <textarea id="alamat" name="alamat" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"><?= htmlspecialchars($anggota['alamat']) ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label for="no_telepon" class="block text-sm font-medium text-gray-700 mb-1">No. Telepon</label>
                        <input type="text" id="no_telepon" name="no_telepon" value="<?= htmlspecialchars($anggota['no_telepon']) ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end">
                <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded mr-2">
                    Batal
                </a>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded">
                    <i class="fas fa-save mr-1"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../templates/footer.php'; ?>








































