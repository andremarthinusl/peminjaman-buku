<?php
session_start();
require_once 'config/db.php';

// Redirect if already logged in
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

// Process registration form
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
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
            } else {                // Default role (3 = anggota, 2 = pustakawan, 1 = admin)
                $default_role = 3;
                
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Start transaction
                $conn->beginTransaction();
                
                try {
                    // Insert new user
                    $insert_sql = "INSERT INTO users (nama_lengkap, username, password, id_role) 
                                VALUES (:nama_lengkap, :username, :password, :id_role)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bindParam(':nama_lengkap', $nama_lengkap);
                    $insert_stmt->bindParam(':username', $username);
                    $insert_stmt->bindParam(':password', $hashed_password);
                    $insert_stmt->bindParam(':id_role', $default_role);
                    $insert_stmt->execute();
                    
                    $user_id = $conn->lastInsertId();
                    
                    // Generate anggota code - A followed by user_id padded with zeros
                    $kode_anggota = 'A' . str_pad($user_id, 5, '0', STR_PAD_LEFT);
                    
                    // Create anggota record for the user
                    $insert_anggota_sql = "INSERT INTO anggota (kode_anggota, nama, alamat, no_telepon) 
                                        VALUES (:kode_anggota, :nama, :alamat, :no_telepon)";
                    $insert_anggota_stmt = $conn->prepare($insert_anggota_sql);
                    $insert_anggota_stmt->bindParam(':kode_anggota', $kode_anggota);
                    $insert_anggota_stmt->bindParam(':nama', $nama_lengkap);
                    $insert_anggota_stmt->bindValue(':alamat', '');  // Default empty value
                    $insert_anggota_stmt->bindValue(':no_telepon', '');  // Default empty value
                    $insert_anggota_stmt->execute();
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $success = "Registrasi berhasil. Silakan login";
                    
                    // Redirect to login after 2 seconds
                    header("refresh:2; url=login.php");
                } catch(PDOException $e) {
                    // Rollback on error
                    $conn->rollBack();
                    $error = "Error: " . $e->getMessage();
                }
            }
        } catch(PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Sistem Peminjaman Buku</title>
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/peminjamanbuku/css/style.css">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center justify-center py-8">
    <div class="w-full max-w-md">
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="bg-indigo-600 text-white p-6 text-center">
                <h1 class="text-2xl font-bold flex items-center justify-center">
                    <i class="fas fa-book-reader mr-2 text-3xl"></i>
                    <span>Perpustakaan Digital</span>
                </h1>
                <p class="mt-1">Sistem Peminjaman Buku</p>
            </div>
            
            <div class="p-6">
                <h2 class="text-xl font-semibold mb-6 text-center text-gray-700">Register</h2>
                
                <?php if(!empty($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?= $error ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($success)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?= $success ?></span>
                    </div>
                <?php endif; ?>
                
                <form action="" method="post">
                    <div class="mb-4">
                        <label for="nama_lengkap" class="block text-gray-700 text-sm font-semibold mb-2">Nama Lengkap</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input type="text" name="nama_lengkap" id="nama_lengkap" class="pl-10 block w-full mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="Masukkan nama lengkap" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="username" class="block text-gray-700 text-sm font-semibold mb-2">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-at text-gray-400"></i>
                            </div>
                            <input type="text" name="username" id="username" class="pl-10 block w-full mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="Masukkan username" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" name="password" id="password" class="pl-10 block w-full mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="Minimal 6 karakter" required>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label for="confirm_password" class="block text-gray-700 text-sm font-semibold mb-2">Konfirmasi Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-check-double text-gray-400"></i>
                            </div>
                            <input type="password" name="confirm_password" id="confirm_password" class="pl-10 block w-full mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="Konfirmasi password" required>
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition">
                            <i class="fas fa-user-plus mr-1"></i> Register
                        </button>
                    </div>
                </form>
                
                <div class="text-center mt-6 text-sm">
                    <p class="text-gray-600">Sudah punya akun?</p>
                    <a href="login.php" class="text-indigo-600 hover:text-indigo-800 font-medium">
                        Login Sekarang
                    </a>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4 text-gray-500 text-xs">
            &copy; <?= date('Y') ?> Perpustakaan Digital. Hak cipta dilindungi.
        </div>
    </div>
</body>
</html>








































