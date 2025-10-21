<?php
// ==============================
// FILE: simpan-sertif-air.php
// Tujuan: Menerima data dari form-sertifikat-air.html
// dan menyimpan ke tabel 'sertif_air' di database 'db_sanitasi'
// ==============================

// 1️⃣ KONEKSI DATABASE
$host       = "localhost";
$user       = "root";
$password   = "";
$database   = "db_sanitasi";

$conn = new mysqli($host, $user, $password, $database);

// Cek koneksi
if ($conn->connect_error) {
    die("<script>alert('Koneksi ke database gagal: " . addslashes($conn->connect_error) . "'); window.history.back();</script>");
}

// 2️⃣ AMBIL DATA DARI FORM (boleh kosong)
$wilayah_kerja = $_POST['wilayah_kerja'] ?? null;
$tanggal_input = $_POST['tanggal_input'] ?? null;
$jenis_sarana  = $_POST['jenis_sarana'] ?? null;
$jumlah        = $_POST['jumlah'] ?? null;
$keterangan    = $_POST['keterangan'] ?? null;

// 3️⃣ SIMPAN KE DATABASE
$sql = "INSERT INTO sertif_air (wilayah_kerja, tanggal_input, jenis_sarana, jumlah, keterangan)
        VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssis", $wilayah_kerja, $tanggal_input, $jenis_sarana, $jumlah, $keterangan);

// 4️⃣ EKSEKUSI QUERY
if ($stmt->execute()) {
    echo "<!DOCTYPE html><html><head><title>Sukses</title><style>body{font-family:'Segoe UI', sans-serif; text-align:center; padding-top: 50px;}</style></head><body>";
    echo "<h1>Data Berhasil Disimpan!</h1>";
    echo "<p>Data Sertifikat Air untuk wilayah kerja <strong>" . htmlspecialchars($wilayah_kerja) . "</strong> telah berhasil dimasukkan.</p>";
    echo "<a href='landing-sertif.html' style='display:inline-block; padding:10px 20px; background-color:#312E5C; color:white; text-decoration:none; border-radius:5px;'>Input Data Lagi</a>";
    echo "</body></html>";
} else {
    echo "<!DOCTYPE html><html><head><title>Gagal</title><style>body{font-family:'Segoe UI', sans-serif; text-align:center; padding-top: 50px;}</style></head><body>";
    echo "<h1>Terjadi Kesalahan</h1>";
    echo "<p>Gagal menyimpan data: " . htmlspecialchars($stmt->error) . "</p>";
    echo "<a href='javascript:history.back()' style='display:inline-block; padding:10px 20px; background-color:#312E5C; color:white; text-decoration:none; border-radius:5px;'>Kembali dan Coba Lagi</a>";
    echo "</body></html>";
}
// 5️⃣ TUTUP KONEKSI
$stmt->close();
$conn->close();
?>
