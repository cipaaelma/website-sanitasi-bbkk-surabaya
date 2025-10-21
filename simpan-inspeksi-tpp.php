<?php
// Koneksi ke database
$conn = new mysqli("localhost", "root", "", "db_sanitasi");

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Ambil data dari form
$wilayah_kerja = $_POST['wilayah_kerja'];
$tanggal_input = $_POST['tanggal_input'];
$nama_tpp = $_POST['nama_tpp'];
$lokasi = $_POST['lokasi'];

// Ubah array menjadi string, kalau tidak ada isinya biar NULL
$temuan = isset($_POST['temuan']) ? implode("; ", $_POST['temuan']) : NULL;
$rekomendasi = isset($_POST['rekomendasi']) ? implode("; ", $_POST['rekomendasi']) : NULL;
$keterangan = isset($_POST['keterangan']) ? implode("; ", $_POST['keterangan']) : NULL;

// Data hasil pemeriksaan
$hasil_inspeksi_status = $_POST['hasil_inspeksi_status'];
$hasil_inspeksi_jumlah = $_POST['hasil_inspeksi_jumlah'];
$hasil_orgonoleptik_status = $_POST['hasil_orgonoleptik_status'];
$hasil_orgonoleptik_jumlah = $_POST['hasil_orgonoleptik_jumlah'];
$hasil_mikrobiologi_status = $_POST['hasil_mikrobiologi_status'];
$hasil_mikrobiologi_jumlah = $_POST['hasil_mikrobiologi_jumlah'];
$hasil_kimiawi_status = $_POST['hasil_kimiawi_status'];
$hasil_kimiawi_jumlah = $_POST['hasil_kimiawi_jumlah'];
$hasil_usap_alat_status = $_POST['hasil_usap_alat_status'];
$hasil_usap_alat_jumlah = $_POST['hasil_usap_alat_jumlah'];

// Query simpan data
$sql = "INSERT INTO inspeksi_tpp (
    wilayah_kerja, tanggal_input, nama_tpp, lokasi, temuan, rekomendasi,
    hasil_inspeksi_status, hasil_inspeksi_jumlah,
    hasil_orgonoleptik_status, hasil_orgonoleptik_jumlah,
    hasil_mikrobiologi_status, hasil_mikrobiologi_jumlah,
    hasil_kimiawi_status, hasil_kimiawi_jumlah,
    hasil_usap_alat_status, hasil_usap_alat_jumlah,
    keterangan
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "sssssssssssssssss",
    $wilayah_kerja, $tanggal_input, $nama_tpp, $lokasi,
    $temuan, $rekomendasi,
    $hasil_inspeksi_status, $hasil_inspeksi_jumlah,
    $hasil_orgonoleptik_status, $hasil_orgonoleptik_jumlah,
    $hasil_mikrobiologi_status, $hasil_mikrobiologi_jumlah,
    $hasil_kimiawi_status, $hasil_kimiawi_jumlah,
    $hasil_usap_alat_status, $hasil_usap_alat_jumlah,
    $keterangan
);

if ($stmt->execute()) {
    echo "<!DOCTYPE html><html><head><title>Sukses</title><style>body{font-family:'Segoe UI', sans-serif; text-align:center; padding-top: 50px;}</style></head><body>";
    echo "<h1>Data Inspeksi Tempat Pengolahan Pangan</h1>";
    echo "<p>Data inspeksi untuk <strong>" . htmlspecialchars($nama_tpp) . "</strong> telah berhasil dimasukkan.</p>";
    echo "<a href='landing-tpp.html' style='display:inline-block; padding:10px 20px; background-color:#312E5C; color:white; text-decoration:none; border-radius:5px;'>Kembali</a>";
    echo "</body></html>";
} else {
    echo "<!DOCTYPE html><html><head><title>Gagal</title><style>body{font-family:'Segoe UI', sans-serif; text-align:center; padding-top: 50px;}</style></head><body>";
    echo "<h1>Terjadi Kesalahan</h1>";
    echo "<p>Gagal menyimpan data: " . htmlspecialchars($stmt->error) . "</p>";
    echo "<a href='javascript:history.back()' style='display:inline-block; padding:10px 20px; background-color:#312E5C; color:white; text-decoration:none; border-radius:5px;'>Kembali dan Coba Lagi</a>";
    echo "</body></html>";
}

$stmt->close();
$conn->close();
?>
