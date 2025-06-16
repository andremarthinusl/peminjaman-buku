<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';

// Periksa apakah pengguna sudah login
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$error = '';
$success = '';
$user_id = $_SESSION['user_id'];
$nama = $_SESSION['nama'];

try {
    // Ambil data pengguna
    $user_query = "SELECT * FROM users WHERE id_user = :user_id";
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $user_stmt->execute();
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Try to find matching anggota record
    $anggota_query = "SELECT * FROM anggota WHERE nama = :nama";
    $anggota_stmt = $conn->prepare($anggota_query);
    $anggota_stmt->bindParam(':nama', $nama);
    $anggota_stmt->execute();
    $anggota = $anggota_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Proses pengiriman formulir for updating profile
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Update user data
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $alamat = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
        $no_telepon = isset($_POST['no_telepon']) ? trim($_POST['no_telepon']) : '';
        
        // Basic validation
        if(empty($nama_lengkap) || empty($username)) {
            $error = "Nama lengkap dan username tidak boleh kosong";
        } else {
            // Periksa apakah nama pengguna sudah digunakan oleh pengguna lain
            if($username != $user['username']) {
                $check_username = "SELECT id_user FROM users WHERE username = :username AND id_user != :user_id";
                $check_stmt = $conn->prepare($check_username);
                $check_stmt->bindParam(':username', $username);
                $check_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $check_stmt->execute();
                
                if($check_stmt->rowCount() > 0) {
                    $error = "Username sudah digunakan";
                }
            }
            
            if(empty($error)) {
                // Begin transaction
                $conn->beginTransaction();
                
                // Update users table
                $update_sql = "UPDATE users SET nama_lengkap = :nama_lengkap, username = :username";
                
                // Only update password if a new one is provided
                if(!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_sql .= ", password = :password";
                }
                
                $update_sql .= " WHERE id_user = :user_id";
                
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bindParam(':nama_lengkap', $nama_lengkap);
                $update_stmt->bindParam(':username', $username);
                
                if(!empty($password)) {
                    $update_stmt->bindParam(':password', $hashed_password);
                }
                
                $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $update_stmt->execute();
                
                // If anggota record exists, update it too
                if($anggota) {
                    $update_anggota_sql = "UPDATE anggota SET nama = :nama, alamat = :alamat, no_telepon = :no_telepon 
                                         WHERE id_anggota = :id_anggota";
                    $update_anggota_stmt = $conn->prepare($update_anggota_sql);
                    $update_anggota_stmt->bindParam(':nama', $nama_lengkap);
                    $update_anggota_stmt->bindParam(':alamat', $alamat);
                    $update_anggota_stmt->bindParam(':no_telepon', $no_telepon);
                    $update_anggota_stmt->bindParam(':id_anggota', $anggota['id_anggota'], PDO::PARAM_INT);
                    $update_anggota_stmt->execute();
                }
                
                // Commit transaction
                $conn->commit();
                
                // Update session data
                $_SESSION['nama'] = $nama_lengkap;
                
                $success = "Profil berhasil diperbarui";
                
                // Refresh user and anggota data after update
                $user_stmt->execute();
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                
                if($anggota) {
                    $anggota_stmt->execute();
                    $anggota = $anggota_stmt->fetch(PDO::FETCH_ASSOC);
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
        <a href="../dashboard.php" class="inline-flex items-center text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i> Kembali ke Dashboard
        </a>
    </div>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="bg-indigo-600 text-white p-6">
            <h1 class="text-2xl font-bold">Profil Saya</h1>
            <p class="text-indigo-200 mt-2">Perbarui informasi profil Anda</p>
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
        
        <div class="p-6">
            <form method="post" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- User Information -->
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Informasi Akun</h2>
                        
                        <div class="mb-4">
                            <label for="nama_lengkap" class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" id="nama_lengkap" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input type="text" name="username" id="username" value="<?= htmlspecialchars($user['username']) ?>" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password Baru (kosongkan jika tidak ingin mengubah)</label>
                            <input type="password" name="password" id="password"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <input type="text" id="role" value="<?= htmlspecialchars($_SESSION['role_name']) ?>" readonly
                                class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md">
                        </div>
                    </div>
                    
                    <!-- Anggota Information (if available) -->
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Detail Anggota</h2>
                        
                        <?php if($anggota): ?>
                            <div class="mb-4">
                                <label for="kode_anggota" class="block text-sm font-medium text-gray-700 mb-1">Kode Anggota</label>
                                <input type="text" id="kode_anggota" value="<?= htmlspecialchars($anggota['kode_anggota']) ?>" readonly
                                    class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md">
                            </div>
                            
                            <div class="mb-4">
                                <label for="alamat" class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                                <textarea name="alamat" id="alamat" rows="3"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"><?= htmlspecialchars($anggota['alamat']) ?></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label for="no_telepon" class="block text-sm font-medium text-gray-700 mb-1">No. Telepon</label>
                                <input type="text" name="no_telepon" id="no_telepon" value="<?= htmlspecialchars($anggota['no_telepon']) ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        <?php else: ?>
                            <div class="p-4 bg-yellow-50 text-yellow-700 rounded-md">
                                <p><i class="fas fa-info-circle mr-2"></i> Data anggota tidak ditemukan.</p>
                                <p class="text-sm mt-2">Hubungi pustakawan jika Anda membutuhkan kartu anggota perpustakaan.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-md">
                        <i class="fas fa-save mr-2"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>








































