<?php
// ==============================
// FILE: simpan-sertifikat-slhs.php
// Tujuan: Menerima data dari form-sertifikat-slhs.html
// dan menyimpan ke tabel 'sertifikat_slhs' di database 'db_sanitasi'
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
$wilayah_kerja   = $_POST['wilayah_kerja'] ?? null;
$tanggal_input   = $_POST['tanggal_input'] ?? null;
$nama_perusahaan = $_POST['nama_perusahaan'] ?? null;
$alamat_tpp      = $_POST['alamat_tpp'] ?? null;
$pb_umku         = $_POST['pb_umku'] ?? null;
$nama_tpp        = $_POST['nama_tpp'] ?? null;
$kategori_tpp    = $_POST['kategori_tpp'] ?? null;

// 3️⃣ SIMPAN KE DATABASE
$sql = "INSERT INTO sertifikat_slhs (wilayah_kerja, tanggal_input, nama_perusahaan, alamat_tpp, pb_umku, nama_tpp, kategori_tpp)
        VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssss", $wilayah_kerja, $tanggal_input, $nama_perusahaan, $alamat_tpp, $pb_umku, $nama_tpp, $kategori_tpp);

// 4️⃣ EKSEKUSI QUERY
if ($stmt->execute()) {
    echo "<!DOCTYPE html><html><head><title>Sukses</title>
    <style>
        body {font-family:'Segoe UI',sans-serif; text-align:center; padding-top:50px;}
        a {display:inline-block; margin-top:20px; padding:10px 20px; background-color:#312E5C; color:white; text-decoration:none; border-radius:5px;}
    </style></head><body>";
    echo "<h1>Data Berhasil Disimpan!</h1>";
    echo "<p>Data Sertifikat SLHS untuk wilayah <strong>" . htmlspecialchars($wilayah_kerja ?: '-') . "</strong> dan perusahaan <strong>" . htmlspecialchars($nama_perusahaan ?: '-') . "</strong> telah berhasil dimasukkan.</p>";
    echo "<a href='landing-sertif.html'>Input Data Lagi</a>";
    echo "</body></html>";
} else {
    echo "<!DOCTYPE html><html><head><title>Gagal</title>
    <style>
        body {font-family:'Segoe UI',sans-serif; text-align:center; padding-top:50px;}
        a {display:inline-block; margin-top:20px; padding:10px 20px; background-color:#312E5C; color:white; text-decoration:none; border-radius:5px;}
    </style></head><body>";
    echo "<h1>Terjadi Kesalahan!</h1>";
    echo "<p>Gagal menyimpan data: " . htmlspecialchars($stmt->error) . "</p>";
    echo "<a href='javascript:history.back()'>Kembali dan Coba Lagi</a>";
    echo "</body></html>";
}

// 5️⃣ TUTUP KONEKSI
$stmt->close();
$conn->close();
?>
