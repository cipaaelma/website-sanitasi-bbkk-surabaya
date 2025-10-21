<?php
// 1. Sertakan file koneksi.php untuk menghubungkan ke database
include 'koneksi.php';

// Menampilkan error untuk debugging (opsional, bisa dihapus jika sudah production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. Ambil semua data yang dikirim dari formulir
$tanggal_input = $_POST['tanggal_input'];
$wilayah_kerja = $_POST['wilayah_kerja'];
$lokasi = $_POST['lokasi'];
$temuan = $_POST['temuan'];
$rekomendasi = $_POST['rekomendasi'];
$keterangan = $_POST['keterangan'];

// Ambil data spesifik untuk Air Bersih
$hasil_is_sarana = $_POST['hasil_is_sarana'] ?? null; // Gunakan null coalescing operator untuk radio button
$hasil_kimia_ph = $_POST['hasil_kimia_ph'];
$hasil_kimia_sisa_chlor = $_POST['hasil_kimia_sisa_chlor'];
$hasil_lab_bakteriologis = $_POST['hasil_lab_bakteriologis'] ?? null;
$hasil_lab_kimia_lengkap = $_POST['hasil_lab_kimia_lengkap'] ?? null;

// 3. Siapkan query SQL untuk memasukkan data ke tabel 'air_bersih'
// Menggunakan prepared statements untuk keamanan
$sql = "INSERT INTO air_bersih (
            tanggal_input, wilayah_kerja, lokasi, temuan, rekomendasi,
            hasil_is_sarana, hasil_kimia_ph, hasil_kimia_sisa_chlor,
            hasil_lab_bakteriologis, hasil_lab_kimia_lengkap, keterangan
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $koneksi->prepare($sql);

// 4. Bind parameter ke query SQL
// Tipe data: 's' untuk string. Semua data kita kirim sebagai string.
$stmt->bind_param("sssssssssss",
    $tanggal_input,
    $wilayah_kerja,
    $lokasi,
    $temuan,
    $rekomendasi,
    $hasil_is_sarana,
    $hasil_kimia_ph,
    $hasil_kimia_sisa_chlor,
    $hasil_lab_bakteriologis,
    $hasil_lab_kimia_lengkap,
    $keterangan
);

// 5. Eksekusi query dan berikan pesan feedback kepada pengguna
if ($stmt->execute()) {
    echo "<!DOCTYPE html><html><head><title>Sukses</title><style>body{font-family:'Segoe UI', sans-serif; text-align:center; padding-top: 50px;}</style></head><body>";
    echo "<h1>Data Berhasil Disimpan!</h1>";
    echo "<p>Data Kualitas Air Bersih untuk lokasi <strong>" . htmlspecialchars($lokasi) . "</strong> telah berhasil dimasukkan.</p>";
    echo "<a href='landing-air.html' style='display:inline-block; padding:10px 20px; background-color:#312E5C; color:white; text-decoration:none; border-radius:5px;'>Input Data Lagi</a>";
    echo "</body></html>";
} else {
    echo "<!DOCTYPE html><html><head><title>Gagal</title><style>body{font-family:'Segoe UI', sans-serif; text-align:center; padding-top: 50px;}</style></head><body>";
    echo "<h1>Terjadi Kesalahan</h1>";
    echo "<p>Gagal menyimpan data: " . htmlspecialchars($stmt->error) . "</p>";
    echo "<a href='javascript:history.back()' style='display:inline-block; padding:10px 20px; background-color:#312E5C; color:white; text-decoration:none; border-radius:5px;'>Kembali dan Coba Lagi</a>";
    echo "</body></html>";
}

// 6. Tutup statement dan koneksi
$stmt->close();
$koneksi->close();
?>