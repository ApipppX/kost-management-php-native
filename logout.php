<?php
session_start();

// Menghapus semua variabel session yang ada
session_unset();

// Menghancurkan session sepenuhnya dari server
session_destroy();

// Mengarahkan pengguna kembali ke halaman login
header("Location: login.php");
exit;
?>