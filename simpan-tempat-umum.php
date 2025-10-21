<?php
include 'koneksi.php';

// Menampilkan error untuk debugging (opsional)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ambil semua data dari formulir
// Perhatikan: 'wilayah' dari <select> sekarang menjadi 'wilayah_kerja'
$tanggal_input = $_POST['tanggal_input'];
$wilayah_kerja = $_POST['wilayah_kerja'];
$lokasi = $_POST['lokasi'];
$temuan = $_POST['temuan'];
$rekomendasi = $_POST['rekomendasi'];
$keterangan = $_POST['keterangan'];

// Ambil data radio button (MS/TMS)
$hasil_is_sarana = $_POST['hasil_is_sarana'] ?? null;
$hasil_laboratorium = $_POST['hasil_laboratorium'] ?? null;

// Siapkan query SQL untuk menyimpan data
$sql = "INSERT INTO tempat_umum (
            tanggal_input, wilayah_kerja, lokasi, temuan, rekomendasi,
            hasil_is_sarana, hasil_laboratorium, keterangan
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $koneksi->prepare($sql);

// Bind parameter ke query SQL
// Tipe data: s = string
$stmt->bind_param("ssssssss",
    $tanggal_input, $wilayah_kerja, $lokasi, $temuan, $rekomendasi,
    $hasil_is_sarana, $hasil_laboratorium, $keterangan
);

// Eksekusi query dan berikan respons
if ($stmt->execute()) {
    echo "<!DOCTYPE html><html><head><title>Sukses</title><style>body{font-family:'Segoe UI', sans-serif; text-align:center; padding-top: 50px;}</style></head><body>";
    echo "<h1>Data Berhasil Disimpan!</h1>";
    echo "<p>Data untuk lokasi <strong>" . htmlspecialchars($lokasi) . "</strong> telah berhasil dimasukkan ke database.</p>";
    echo "<a href='landing-ttu.html' style='display:inline-block; padding:10px 20px; background-color:#312E5C; color:white; text-decoration:none; border-radius:5px;'>Input Data Lagi</a>";
    echo "</body></html>";
} else {
    echo "<!DOCTYPE html><html><head><title>Gagal</title><style>body{font-family:'Segoe UI', sans-serif; text-align:center; padding-top: 50px;}</style></head><body>";
    echo "<h1>Terjadi Kesalahan</h1>";
    echo "<p>Gagal menyimpan data: " . $stmt->error . "</p>";
    echo "<a href='javascript:history.back()' style='display:inline-block; padding:10px 20px; background-color:#312E5C; color:white; text-decoration:none; border-radius:5px;'>Kembali dan Coba Lagi</a>";
    echo "</body></html>";
}

// Tutup koneksi
$stmt->close();
$koneksi->close();
?>