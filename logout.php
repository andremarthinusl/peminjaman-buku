<?php
session_start();

// Mengosongkan semua variabel session
$_SESSION = array();

// Menghapus session
session_destroy();

// Dialihkan ke halaman login
header("Location: login");
exit;
?>