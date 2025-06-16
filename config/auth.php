<?php
/**
 * File pembantu otentikasi untuk kontrol akses berbasis peran
 */

/**
 * Memeriksa apakah pengguna saat ini memiliki izin untuk mengakses modul tertentu
 * @param array $allowed_roles Array ID peran yang diizinkan untuk mengakses
 * @return bool True jika pengguna memiliki akses, false jika sebaliknya
 */
function checkAccess($allowed_roles = []) {    // Periksa apakah pengguna sudah login
    if(!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Periksa apakah peran pengguna ada dalam daftar peran yang diizinkan
    return in_array($_SESSION['role'], $allowed_roles);
}

/**
 * Mengalihkan pengguna jika mereka tidak memiliki izin untuk mengakses halaman saat ini
 * @param array $allowed_roles Array ID peran yang diizinkan untuk mengakses
 */
function requireAccess($allowed_roles = []) {
    if(!checkAccess($allowed_roles)) {
        // Pengguna tidak memiliki izin, alihkan ke dashboard dengan pesan error
        $_SESSION['error'] = "Anda tidak memiliki akses ke halaman tersebut.";
        header("Location: " . getBaseUrl() . "dashboard.php");
        exit;
    }
}

/**
 * Mendapatkan URL dasar aplikasi
 * @return string URL dasar yang diakhiri dengan garis miring
 */
function getBaseUrl() {
    $base_url = "/peminjamanbuku/";
      // Periksa apakah kita berada di subdirektori
    $current_path = $_SERVER['PHP_SELF'];
    $depth = substr_count($current_path, '/', 1);
    
    if($depth > 1) {
        // Kita berada di subdirektori, tambahkan ../ untuk setiap level
        for($i = 1; $i < $depth; $i++) {
            $base_url = "../" . $base_url;
        }
    }
    
    return $base_url;
}








































