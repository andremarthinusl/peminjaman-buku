<?php
session_start();
require_once '../config/db.php';
require_once '../config/auth.php';

// Periksa apakah pengguna sudah login
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Periksa apakah pengguna memiliki akses ke halaman ini (Hanya Admin)
requireAccess([1]);

$error = '';
$success = '';

// Ambil data peran untuk dropdown
try {
    $roles_sql = "SELECT * FROM roles ORDER BY id_role";
    $roles_stmt = $conn->query($roles_sql);
    $roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// Proses pengiriman formulir
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $id_role = (int)$_POST['id_role'];
    
    // Validasi
    if(empty($nama_lengkap) || empty($username) || empty($password) || empty($confirm_password)) {
        $error = "Semua field harus diisi";
    } elseif($password !== $confirm_password) {
        $error = "Password tidak cocok";
    } elseif(strlen($password) < 6) {
        $error = "Password minimal 6 karakter";
    } else {
        try {
            // Periksa apakah nama pengguna sudah ada
            $check_sql = "SELECT id_user FROM users WHERE username = :username";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->execute();
            
            if($check_stmt->rowCount() > 0) {
                $error = "Username sudah digunakan";
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user
                $insert_sql = "INSERT INTO users (nama_lengkap, username, password, id_role) 
                               VALUES (:nama_lengkap, :username, :password, :id_role)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bindParam(':nama_lengkap', $nama_lengkap);
                $insert_stmt->bindParam(':username', $username);
                $insert_stmt->bindParam(':password', $hashed_password);
                $insert_stmt->bindParam(':id_role', $id_role);
                $insert_stmt->execute();
                
                $_SESSION['success'] = "User berhasil ditambahkan";
                header("Location: index.php");
                exit;
            }
        } catch(PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Include header
include '../templates/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-user-plus mr-2 text-indigo-600"></i> Tambah User
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
                <div class="md:col-span-2">
                    <label for="nama_lengkap" class="block text-sm font-medium text-gray-700 mb-2">
                        Nama Lengkap <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nama_lengkap" id="nama_lengkap" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                        Username <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="username" id="username" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                </div>
                
                <div>
                    <label for="id_role" class="block text-sm font-medium text-gray-700 mb-2">
                        Role <span class="text-red-500">*</span>
                    </label>
                    <select name="id_role" id="id_role" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                        <option value="">-- Pilih Role --</option>
                        <?php foreach($roles as $role): ?>
                            <option value="<?= $role['id_role'] ?>"><?= htmlspecialchars($role['nama_role']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Password <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" name="password" id="password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <button type="button" id="togglePassword" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Minimal 6 karakter</p>
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                        Konfirmasi Password <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" name="confirm_password" id="confirm_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <button type="button" id="toggleConfirmPassword" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
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

<script>
    // Toggle password visibility
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const icon = this.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
    
    // Toggle confirm password visibility
    document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('confirm_password');
        const icon = this.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
</script>

<?php include '../templates/footer.php'; ?>








































