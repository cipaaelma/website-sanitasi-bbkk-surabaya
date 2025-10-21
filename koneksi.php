<?php
// Ganti detail ini sesuai dengan pengaturan database Anda
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "db_sanitasi";

// Membuat koneksi
$koneksi = new mysqli($servername, $username, $password, $dbname);

// Memeriksa koneksi
if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}
$koneksi->set_charset("utf8mb4");
?>