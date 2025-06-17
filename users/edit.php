<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';

// Memeriksa apakah pengguna sudah login
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Memeriksa apakah pengguna memiliki akses ke halaman ini (hanya Admin)
requireAccess([1]);

// Memeriksa apakah ID disediakan
if(!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID pengguna tidak ditemukan";
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];
$error = '';
$success = '';

try {
    // Mendapatkan data peran untuk dropdown
    $roles_sql = "SELECT * FROM roles ORDER BY id_role";
    $roles_stmt = $conn->query($roles_sql);
    $roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mendapatkan data pengguna
    $user_sql = "SELECT * FROM users WHERE id_user = :id";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $user_stmt->execute();
    
    if($user_stmt->rowCount() == 0) {
        $_SESSION['error'] = "Pengguna tidak ditemukan";
        header("Location: index.php");
        exit;
    }
    
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Memproses pengiriman formulir
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $username = trim($_POST['username']);
        $password = $_POST['password']; // Opsional, hanya jika mengubah password
        $confirm_password = $_POST['confirm_password'];
        $id_role = (int)$_POST['id_role'];
        
        // Validasi
        if(empty($nama_lengkap) || empty($username)) {
            $error = "Nama lengkap dan username harus diisi";
        } elseif(!empty($password) && $password !== $confirm_password) {
            $error = "Password tidak cocok";
        } elseif(!empty($password) && strlen($password) < 6) {
            $error = "Password minimal 6 karakter";
        } else {
            // Memeriksa apakah username sudah digunakan oleh pengguna lain
            if($username != $user['username']) {
                $check_sql = "SELECT id_user FROM users WHERE username = :username AND id_user != :id_user";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bindParam(':username', $username);
                $check_stmt->bindParam(':id_user', $id, PDO::PARAM_INT);
                $check_stmt->execute();
                
                if($check_stmt->rowCount() > 0) {
                    $error = "Username sudah digunakan";
                }
            }
            
            if(empty($error)) {
                // Memulai transaksi
                $conn->beginTransaction();
                
                // Menyusun SQL update berdasarkan apakah password disediakan
                if(!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_sql = "UPDATE users SET 
                                  nama_lengkap = :nama_lengkap, 
                                  username = :username, 
                                  password = :password, 
                                  id_role = :id_role 
                                  WHERE id_user = :id_user";
                } else {
                    $update_sql = "UPDATE users SET 
                                  nama_lengkap = :nama_lengkap, 
                                  username = :username, 
                                  id_role = :id_role 
                                  WHERE id_user = :id_user";
                }
                
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bindParam(':nama_lengkap', $nama_lengkap);
                $update_stmt->bindParam(':username', $username);
                $update_stmt->bindParam(':id_role', $id_role, PDO::PARAM_INT);                $update_stmt->bindParam(':id_user', $id, PDO::PARAM_INT);
                
                if(!empty($password)) {
                    $update_stmt->bindParam(':password', $hashed_password);
                }
                
                $update_stmt->execute();
                
                // Jika pengguna diubah ke atau dari peran 3 (Anggota), tangani catatan anggota
                if($id_role == 3 || $user['id_role'] == 3) {
                    // Memeriksa apakah sudah ada catatan anggota dengan nama ini
                    $check_anggota_sql = "SELECT id_anggota FROM anggota WHERE nama = :nama";
                    $check_anggota_stmt = $conn->prepare($check_anggota_sql);
                    $check_anggota_stmt->bindParam(':nama', $nama_lengkap);
                    $check_anggota_stmt->execute();
                    
                    // Jika mengubah ke peran 3 (Anggota) dan tidak ada catatan anggota
                    if($id_role == 3 && $check_anggota_stmt->rowCount() == 0) {
                        // Menghasilkan kode anggota
                        $kode_anggota = 'A' . str_pad($id, 5, '0', STR_PAD_LEFT);
                        
                        // Membuat catatan anggota
                        $insert_anggota_sql = "INSERT INTO anggota (kode_anggota, nama, alamat, no_telepon) 
                                          VALUES (:kode_anggota, :nama, '', '')";
                        $insert_anggota_stmt = $conn->prepare($insert_anggota_sql);
                        $insert_anggota_stmt->bindParam(':kode_anggota', $kode_anggota);
                        $insert_anggota_stmt->bindParam(':nama', $nama_lengkap);
                        $insert_anggota_stmt->execute();
                    } 
                    // Jika catatan anggota sudah ada dan nama berubah
                    elseif($check_anggota_stmt->rowCount() > 0 && $nama_lengkap != $user['nama_lengkap']) {
                        // Memperbarui nama anggota agar sesuai
                        $update_anggota_sql = "UPDATE anggota SET nama = :new_nama WHERE nama = :old_nama";
                        $update_anggota_stmt = $conn->prepare($update_anggota_sql);
                        $update_anggota_stmt->bindParam(':new_nama', $nama_lengkap);
                        $update_anggota_stmt->bindParam(':old_nama', $user['nama_lengkap']);
                        $update_anggota_stmt->execute();
                    }
                }
                
                // Commit transaksi
                $conn->commit();
                
                // Perbarui sesi jika mengedit pengguna saat ini
                if($id == $_SESSION['user_id']) {
                    $_SESSION['nama'] = $nama_lengkap;
                    $_SESSION['role'] = $id_role;
                    
                    // Mendapatkan nama peran
                    $role_name_sql = "SELECT nama_role FROM roles WHERE id_role = :id_role";
                    $role_name_stmt = $conn->prepare($role_name_sql);
                    $role_name_stmt->bindParam(':id_role', $id_role, PDO::PARAM_INT);
                    $role_name_stmt->execute();
                    $role_name = $role_name_stmt->fetch(PDO::FETCH_ASSOC)['nama_role'];
                    $_SESSION['role_name'] = $role_name;
                }
                
                $success = "Data pengguna berhasil diperbarui";
                
                // Menyegarkan data pengguna
                $user_stmt->execute();
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
} catch(PDOException $e) {
    // Rollback transaksi jika terjadi kesalahan
    if($conn->inTransaction()) {
        $conn->rollBack();
    }
    $error = "Error: " . $e->getMessage();
}

// Include header
include '../templates/header.php';
?>

<div class="container mx-auto px-4 py-6">    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-user-edit mr-2 text-indigo-600"></i> Edit Pengguna
        </h1>
        <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded inline-flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Kembali
        </a>
    </div>
    
    <?php if($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
            <p><?= $error ?></p>
        </div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded" role="alert">
            <p><?= $success ?></p>
        </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <form action="" method="post" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <div class="mb-4">
                        <label for="nama_lengkap" class="block text-sm font-medium text-gray-700 mb-2">
                            Nama Lengkap <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nama_lengkap" id="nama_lengkap" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                            Username <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="username" id="username" value="<?= htmlspecialchars($user['username']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                    </div>
                </div>
                
                <div>
                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Password (Kosongkan jika tidak ingin mengubah)
                        </label>
                        <input type="password" name="password" id="password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="mt-1 text-sm text-gray-500">Minimal 6 karakter</p>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Konfirmasi Password
                        </label>
                        <input type="password" name="confirm_password" id="confirm_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    
                    <div class="mb-4">
                        <label for="id_role" class="block text-sm font-medium text-gray-700 mb-2">
                            Role <span class="text-red-500">*</span>
                        </label>
                        <select name="id_role" id="id_role" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                            <?php foreach($roles as $role): ?>
                                <option value="<?= $role['id_role'] ?>" <?= ($user['id_role'] == $role['id_role']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($role['nama_role']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if($id == $_SESSION['user_id']): ?>
                            <p class="mt-1 text-sm text-red-500">Peringatan: Mengubah role Anda sendiri dapat memengaruhi akses Anda ke sistem!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end mt-6">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <i class="fas fa-save mr-2"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../templates/footer.php'; ?>
