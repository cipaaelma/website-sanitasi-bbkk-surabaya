<?php
// Jika mode normal (bukan download Excel)
header('Content-Type: application/json');

// ==============================
// 1️⃣ KONEKSI DATABASE
// ==============================
$conn = new mysqli("localhost", "root", "", "db_sanitasi");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Koneksi gagal: " . $conn->connect_error]);
    exit;
}

// ==============================
// 2️⃣ FILTER PARAMETER (opsional)
// ==============================
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';
$wilayah    = $_GET['wilayah'] ?? '';

$where = [];
if (!empty($start_date)) $where[] = "tanggal_input >= '$start_date'";
if (!empty($end_date))   $where[] = "tanggal_input <= '$end_date'";
if (!empty($wilayah))    $where[] = "wilayah_kerja = '$wilayah'";
$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// ==============================
// 🔽 FITUR DOWNLOAD EXCEL
// ==============================
if (isset($_GET['download_excel']) && isset($_GET['type'])) {
    $type = $_GET['type'];

    // Tentukan query & header kolom
    if ($type === 'air') {
        $filename = "sertifikat_air.xls";
        $query = "SELECT wilayah_kerja, tanggal_input, jenis_sarana, jumlah, keterangan 
                  FROM sertif_air $where_sql ORDER BY tanggal_input DESC";
        $headers = ['Wilayah Kerja','Tanggal Input','Jenis Sarana','Jumlah','Keterangan'];
    } elseif ($type === 'slhs') {
        $filename = "sertifikat_slhs.xls";
        $query = "SELECT wilayah_kerja, tanggal_input, nama_perusahaan, alamat_tpp, pb_umku, nama_tpp, kategori_tpp 
                  FROM sertifikat_slhs $where_sql ORDER BY tanggal_input DESC";
        $headers = ['Wilayah Kerja','Tanggal Input','Nama Perusahaan','Alamat TPP','PB-UMKU','Nama TPP','Kategori TPP'];
    } elseif ($type === 'penjamah') {
        $filename = "sertifikat_penjamah.xls";
        $query = "SELECT wilayah_kerja, tanggal_input, nama_penerima, nama_perusahaan, alamat_perusahaan, keterangan 
                  FROM sertifikat_penjamah $where_sql ORDER BY tanggal_input DESC";
        $headers = ['Wilayah Kerja','Tanggal Input','Nama Penerima','Nama Perusahaan','Alamat Perusahaan','Keterangan'];
    } else {
        exit("Jenis sertifikat tidak valid");
    }

    // Jalankan query dan hasilkan Excel
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=$filename");

    echo "<table border='1'><tr>";
    foreach ($headers as $h) echo "<th>$h</th>";
    echo "</tr>";

    $res = $conn->query($query);
    while ($row = $res->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $v) echo "<td>" . htmlspecialchars($v) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
}

// ==============================
// 3️⃣ KPI (COUNT PER TABEL)
// ==============================
$kpi = [
    "air" => 0,
    "slhs" => 0,
    "penjamah" => 0,
    "total" => 0
];

// ✅ Sertifikat Air dihitung dari kolom jumlah
$res = $conn->query("SELECT SUM(jumlah) AS c FROM sertif_air $where_sql");
$kpi["air"] = (int) ($res->fetch_assoc()["c"] ?? 0);

// SLHS dan Penjamah dihitung per baris
$res = $conn->query("SELECT COUNT(*) AS c FROM sertifikat_slhs $where_sql");
$kpi["slhs"] = (int) ($res->fetch_assoc()["c"] ?? 0);

$res = $conn->query("SELECT COUNT(*) AS c FROM sertifikat_penjamah $where_sql");
$kpi["penjamah"] = (int) ($res->fetch_assoc()["c"] ?? 0);

$kpi["total"] = $kpi["air"] + $kpi["slhs"] + $kpi["penjamah"];

// ==============================
// 4️⃣ BAR CHART PER WILAYAH
// ==============================
$wilayah_data = [];
$q = "
    SELECT wilayah_kerja, SUM(jumlah) AS count FROM (
        SELECT wilayah_kerja, SUM(jumlah) AS jumlah FROM sertif_air $where_sql GROUP BY wilayah_kerja
        UNION ALL
        SELECT wilayah_kerja, COUNT(*) AS jumlah FROM sertifikat_slhs $where_sql GROUP BY wilayah_kerja
        UNION ALL
        SELECT wilayah_kerja, COUNT(*) AS jumlah FROM sertifikat_penjamah $where_sql GROUP BY wilayah_kerja
    ) AS all_wilayah
    GROUP BY wilayah_kerja
";
$res = $conn->query($q);
while ($r = $res->fetch_assoc()) $wilayah_data[] = $r;

// ==============================
// 5️⃣ LINE CHART — TREN BULANAN PER JENIS
// ==============================
$trend_data = [];
$q = "
    SELECT label,
           COALESCE(SUM(CASE WHEN jenis='air' THEN jumlah END),0) AS air,
           COALESCE(SUM(CASE WHEN jenis='slhs' THEN jumlah END),0) AS slhs,
           COALESCE(SUM(CASE WHEN jenis='penjamah' THEN jumlah END),0) AS penjamah
    FROM (
        -- ✅ sertif_air gunakan SUM(jumlah)
        SELECT DATE_FORMAT(tanggal_input, '%Y-%m') AS label, SUM(jumlah) AS jumlah, 'air' AS jenis
        FROM sertif_air $where_sql GROUP BY label

        UNION ALL

        -- SLHS dan Penjamah tetap COUNT(*)
        SELECT DATE_FORMAT(tanggal_input, '%Y-%m') AS label, COUNT(*) AS jumlah, 'slhs' AS jenis
        FROM sertifikat_slhs $where_sql GROUP BY label

        UNION ALL

        SELECT DATE_FORMAT(tanggal_input, '%Y-%m') AS label, COUNT(*) AS jumlah, 'penjamah' AS jenis
        FROM sertifikat_penjamah $where_sql GROUP BY label
    ) AS combined
    GROUP BY label
    ORDER BY label ASC
";
$res = $conn->query($q);
while ($r = $res->fetch_assoc()) $trend_data[] = $r;

// ==============================
// 6️⃣ 5 DATA TERBARU PER TABEL
// ==============================
$recent = [];

// Air
$q = "SELECT wilayah_kerja, tanggal_input, jenis_sarana, jumlah, keterangan 
      FROM sertif_air $where_sql ORDER BY tanggal_input DESC LIMIT 5";
$res = $conn->query($q);
$recent["air"] = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// SLHS
$q = "SELECT wilayah_kerja, tanggal_input, nama_perusahaan, alamat_tpp, pb_umku, nama_tpp, kategori_tpp 
      FROM sertifikat_slhs $where_sql ORDER BY tanggal_input DESC LIMIT 5";
$res = $conn->query($q);
$recent["slhs"] = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// Penjamah
$q = "SELECT wilayah_kerja, tanggal_input, nama_penerima, nama_perusahaan, alamat_perusahaan, keterangan 
      FROM sertifikat_penjamah $where_sql ORDER BY tanggal_input DESC LIMIT 5";
$res = $conn->query($q);
$recent["penjamah"] = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// ==============================
// 7️⃣ RETURN JSON KE DASHBOARD
// ==============================
echo json_encode([
    "kpi" => $kpi,
    "wilayah" => $wilayah_data,
    "trend" => $trend_data,
    "recent" => $recent
], JSON_PRETTY_PRINT);

$conn->close();
?>
