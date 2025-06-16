<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Peminjaman Buku</title>
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <!-- Navbar -->
    <nav class="bg-indigo-600 text-white shadow-md">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <a href="/dashboard" class="font-bold text-xl">
                        <i class="fas fa-book-reader mr-2"></i>
                        Perpustakaan Digital
                    </a>
                </div>                
                <div class="hidden md:flex items-center space-x-4">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="/dashboard" class="px-3 py-2 rounded hover:bg-indigo-500 transition">Dashboard</a>
                        
                        <?php if($_SESSION['role'] == 1): // Admin ?>
                            <a href="../buku/" class="px-3 py-2 rounded hover:bg-indigo-500 transition">Buku</a>
                            <a href="../anggota/" class="px-3 py-2 rounded hover:bg-indigo-500 transition">Anggota</a>
                            <a href="../pinjam/" class="px-3 py-2 rounded hover:bg-indigo-500 transition">Peminjaman</a>
                            <a href="../riwayatpinjam/" class="px-3 py-2 rounded hover:bg-indigo-500 transition">Riwayat</a>
                            <a href="../users/" class="px-3 py-2 rounded hover:bg-indigo-500 transition">Users</a>
                        <?php elseif($_SESSION['role'] == 2): // Pustakawan ?>
                            <a href="../buku/" class="px-3 py-2 rounded hover:bg-indigo-500 transition">Buku</a>
                            <a href="../anggota/" class="px-3 py-2 rounded hover:bg-indigo-500 transition">Anggota</a>
                            <a href="../pinjam/" class="px-3 py-2 rounded hover:bg-indigo-500 transition">Peminjaman</a>
                            <a href="../riwayatpinjam/" class="px-3 py-2 rounded hover:bg-indigo-500 transition">Riwayat</a>                        <?php elseif($_SESSION['role'] == 3): // Anggota ?>
                            <a href="../buku/" class="px-3 py-2 rounded hover:bg-indigo-500 transition">Buku</a>
                            <a href="../riwayatpinjam/" class="px-3 py-2 rounded hover:bg-indigo-500 transition">Riwayat Pinjam</a>
                            <a href="../profile/" class="px-3 py-2 rounded hover:bg-indigo-500 transition">Profil Saya</a>
                        <?php endif; ?>
                        <div class="border-l border-indigo-500 pl-4">
                            <span class="mr-2"><?= $_SESSION['nama'] ?></span>
                            <a href="/logout" class="bg-red-500 hover:bg-red-600 px-3 py-2 rounded transition">
                                <i class="fas fa-sign-out-alt mr-1"></i> Logout
                            </a>
                        </div>
                    <?php else: ?>
                        <a href="/login" class="px-3 py-2 rounded hover:bg-indigo-500 transition">Login</a>
                        <a href="/register" class="bg-white text-indigo-600 hover:bg-gray-100 px-3 py-2 rounded transition">Register</a>
                    <?php endif; ?>
                </div>
                <!-- Mobile menu button -->
                <div class="md:hidden flex items-center">
                    <button class="mobile-menu-button">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>        <!-- Mobile menu -->
        <div class="mobile-menu hidden md:hidden">
            <div class="container mx-auto px-4 py-2 space-y-1">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="/dashboard" class="block px-3 py-2 rounded hover:bg-indigo-500 transition">Dashboard</a>
                    
                    <?php if($_SESSION['role'] == 1): // Admin ?>
                        <a href="../buku/" class="block px-3 py-2 rounded hover:bg-indigo-500 transition">Buku</a>
                        <a href="../anggota/" class="block px-3 py-2 rounded hover:bg-indigo-500 transition">Anggota</a>
                        <a href="../pinjam/" class="block px-3 py-2 rounded hover:bg-indigo-500 transition">Peminjaman</a>
                        <a href="../riwayatpinjam/" class="block px-3 py-2 rounded hover:bg-indigo-500 transition">Riwayat</a>
                        <a href="../users/" class="block px-3 py-2 rounded hover:bg-indigo-500 transition">Users</a>
                        <a href="../pengguna/" class="block px-3 py-2 rounded hover:bg-indigo-500 transition">Pengguna</a>
                    <?php elseif($_SESSION['role'] == 2): // Pustakawan ?>
                        <a href="../buku/" class="block px-3 py-2 rounded hover:bg-indigo-500 transition">Buku</a>
                        <a href="../anggota/" class="block px-3 py-2 rounded hover:bg-indigo-500 transition">Anggota</a>
                        <a href="../pinjam/" class="block px-3 py-2 rounded hover:bg-indigo-500 transition">Peminjaman</a>
                        <a href="../riwayatpinjam/" class="block px-3 py-2 rounded hover:bg-indigo-500 transition">Riwayat</a>                    <?php elseif($_SESSION['role'] == 3): // Anggota ?>
                        <a href="../buku/" class="block px-3 py-2 rounded hover:bg-indigo-500 transition">Buku</a>
                        <a href="../riwayatpinjam/" class="block px-3 py-2 rounded hover:bg-indigo-500 transition">Riwayat Pinjam</a>
                        <a href="../profile/" class="block px-3 py-2 rounded hover:bg-indigo-500 transition">Profil Saya</a>
                    <?php endif; ?>
                    <div class="border-t border-indigo-500 pt-2 mt-2">
                        <span class="block px-3 py-2"><?= $_SESSION['nama'] ?></span>
                        <a href="/logout" class="block bg-red-500 hover:bg-red-600 px-3 py-2 rounded transition">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </a>
                    </div>
                <?php else: ?>
                    <a href="/login" class="block px-3 py-2 rounded hover:bg-indigo-500 transition">Login</a>
                    <a href="/register" class="block bg-white text-indigo-600 hover:bg-gray-100 px-3 py-2 rounded transition">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Main content -->
    <main class="flex-grow container mx-auto px-4 py-6">








































