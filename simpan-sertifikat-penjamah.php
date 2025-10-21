<?php 
// ==============================
// FILE: simpan-sertifikat-penjamah.php
// Tujuan: Menerima data dari form-sertifikat-penjamah.html
// dan menyimpan ke tabel 'sertifikat_penjamah' di database 'db_sanitasi'
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
$wilayah_kerja     = $_POST['wilayah_kerja'] ?? null;
$tanggal_input     = $_POST['tanggal_input'] ?? null;
$nama_penerima     = $_POST['nama_penerima'] ?? null;
$nama_perusahaan   = $_POST['nama_perusahaan'] ?? null;
$alamat_perusahaan = $_POST['alamat_perusahaan'] ?? null;
$keterangan        = $_POST['keterangan'] ?? null;

// 3️⃣ SIMPAN KE DATABASE
$sql = "INSERT INTO sertifikat_penjamah 
        (wilayah_kerja, tanggal_input, nama_penerima, nama_perusahaan, alamat_perusahaan, keterangan)
        VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssss", $wilayah_kerja, $tanggal_input, $nama_penerima, $nama_perusahaan, $alamat_perusahaan, $keterangan);

// 4️⃣ EKSEKUSI QUERY
if ($stmt->execute()) {
    echo "<!DOCTYPE html><html><head><title>Sukses</title><style>
            body{font-family:'Segoe UI', sans-serif;text-align:center;padding-top:50px}
            a{display:inline-block;padding:10px 20px;background-color:#312E5C;color:white;text-decoration:none;border-radius:5px;margin-top:20px}
          </style></head><body>";
    echo "<h1>Data Berhasil Disimpan!</h1>";
    echo "<p>Sertifikat Penjamah untuk wilayah kerja <strong>" . htmlspecialchars($wilayah_kerja) . "</strong> telah tersimpan.</p>";
    echo "<a href='landing-sertif.html'>Input Data Lagi</a>";
    echo "</body></html>";
} else {
    echo "<!DOCTYPE html><html><head><title>Gagal</title><style>
            body{font-family:'Segoe UI', sans-serif;text-align:center;padding-top:50px}
            a{display:inline-block;padding:10px 20px;background-color:#312E5C;color:white;text-decoration:none;border-radius:5px;margin-top:20px}
          </style></head><body>";
    echo "<h1>Terjadi Kesalahan</h1>";
    echo "<p>Gagal menyimpan data: " . htmlspecialchars($stmt->error) . "</p>";
    echo "<a href='javascript:history.back()'>Kembali dan Coba Lagi</a>";
    echo "</body></html>";
}

// 5️⃣ TUTUP KONEKSI
$stmt->close();
$conn->close();
?>
