<?php
session_start();
require_once 'config/db.php';

// Mengalihkan jika sudah login
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

// Proses form login
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if(empty($username) || empty($password)) {
        $error = "Username dan password tidak boleh kosong";
    } else {
        try {
            $sql = "SELECT u.*, r.nama_role FROM users u 
                    JOIN roles r ON u.id_role = r.id_role 
                    WHERE u.username = :username";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if(password_verify($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id_user'];
                    $_SESSION['nama'] = $user['nama_lengkap'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['id_role'];
                    $_SESSION['role_name'] = $user['nama_role'];
                    
                    // Redirect to dashboard
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = "Password salah";
                }
            } else {
                $error = "Username tidak ditemukan";
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
    <title>Login - Sistem Peminjaman Buku</title>
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/peminjamanbuku/css/style.css">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center justify-center">
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
                <h2 class="text-xl font-semibold mb-6 text-center text-gray-700">Login</h2>
                
                <?php if(!empty($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?= $error ?></span>
                    </div>
                <?php endif; ?>
                
                <form action="" method="post">
                    <div class="mb-4">
                        <label for="username" class="block text-gray-700 text-sm font-semibold mb-2">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input type="text" name="username" id="username" class="pl-10 block w-full mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="Masukkan username" required>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" name="password" id="password" class="pl-10 block w-full mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="Masukkan password" required>
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition">
                            <i class="fas fa-sign-in-alt mr-1"></i> Login
                        </button>
                    </div>
                </form>
                
                <div class="text-center mt-6 text-sm">
                    <p class="text-gray-600">Belum punya akun?</p>
                    <a href="register.php" class="text-indigo-600 hover:text-indigo-800 font-medium">
                        Register Sekarang
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








































