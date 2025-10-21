<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_sanitasi";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

// --- FILTER ---
$where = [];
if (!empty($_GET['start_date'])) {
    $where[] = "tanggal_input >= '" . $conn->real_escape_string($_GET['start_date']) . "'";
}
if (!empty($_GET['end_date'])) {
    $where[] = "tanggal_input <= '" . $conn->real_escape_string($_GET['end_date']) . "'";
}
if (!empty($_GET['wilayah_kerja'])) {
    $where[] = "wilayah_kerja = '" . $conn->real_escape_string($_GET['wilayah_kerja']) . "'";
}
$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// ===================================================================================
// 1. KPI (disederhanakan)
// ===================================================================================
$kpi = [
    "total" => 0,
    "sarana_ms" => 0,
    "sarana_tms" => 0
];

// Total pemeriksaan
$res = $conn->query("SELECT COUNT(*) as c FROM tempat_umum $where_sql");
$kpi["total"] = (int)($res->fetch_assoc()["c"] ?? 0);

// Hasil Sarana MS dan TMS
$sql = "SELECT 
            SUM(CASE WHEN hasil_is_sarana='MS' THEN 1 ELSE 0 END) AS ms,
            SUM(CASE WHEN hasil_is_sarana='TMS' THEN 1 ELSE 0 END) AS tms
        FROM tempat_umum $where_sql";
$r = $conn->query($sql)->fetch_assoc();
$kpi["sarana_ms"] = (int)$r["ms"];
$kpi["sarana_tms"] = (int)$r["tms"];

// ===================================================================================
// 2. Bar Chart per Wilayah
// ===================================================================================
$wilayah = [];
$q = $conn->query("SELECT wilayah_kerja, COUNT(*) as count 
                   FROM tempat_umum $where_sql 
                   GROUP BY wilayah_kerja");
while($r = $q->fetch_assoc()){
    $wilayah[] = [
        "wilayah_kerja" => $r["wilayah_kerja"],
        "count" => (int)$r["count"]
    ];
}

// ===================================================================================
// 3. Line Chart Trend Bulanan
// ===================================================================================
$trend = [];
$q = $conn->query("SELECT DATE_FORMAT(tanggal_input, '%Y-%m') as bulan, COUNT(*) as count
                   FROM tempat_umum $where_sql
                   GROUP BY bulan ORDER BY bulan");
while($r = $q->fetch_assoc()){
    $trend[] = [
        "label" => $r["bulan"],
        "count" => (int)$r["count"]
    ];
}

// ===================================================================================
// 4. Tabel Data Terbaru (untuk JSON & Excel)
// ===================================================================================
$recent = [];
$q = $conn->query("SELECT lokasi, wilayah_kerja, hasil_is_sarana, hasil_laboratorium, 
                          tanggal_input, temuan, rekomendasi
                   FROM tempat_umum $where_sql
                   ORDER BY tanggal_input DESC");
while($r = $q->fetch_assoc()){
    $recent[] = [
        "lokasi" => $r["lokasi"],
        "wilayah" => $r["wilayah_kerja"],
        "hasil_sarana" => $r["hasil_is_sarana"],
        "hasil_lab" => $r["hasil_laboratorium"],
        "tanggal" => $r["tanggal_input"],
        "temuan" => $r["temuan"],
        "rekomendasi" => $r["rekomendasi"]
    ];
}

// ===================================================================================
// MODE DOWNLOAD EXCEL
// ===================================================================================
if (isset($_GET['download_excel']) && $_GET['download_excel'] == 1) {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"data_tempat_umum.xls\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<table border='1'>";
    echo "<thead>
    <tr style='background-color:#c6e0b4;'>
        <th>No</th>
        <th>Lokasi</th>
        <th>Wilayah Kerja</th>
        <th>Hasil Sarana</th>
        <th>Hasil Laboratorium</th>
        <th>Tanggal Input</th>
        <th>Temuan</th>
        <th>Rekomendasi</th>
    </tr>
    </thead><tbody>";

    if (count($recent) > 0) {
        $no = 1;
        foreach ($recent as $row) {
            echo "<tr>";
            echo "<td>" . $no++ . "</td>";
            echo "<td>" . htmlspecialchars($row['lokasi']) . "</td>";
            echo "<td>" . htmlspecialchars($row['wilayah']) . "</td>";
            echo "<td>" . htmlspecialchars($row['hasil_sarana']) . "</td>";
            echo "<td>" . htmlspecialchars($row['hasil_lab']) . "</td>";
            echo "<td>" . htmlspecialchars($row['tanggal']) . "</td>";
            echo "<td>" . htmlspecialchars($row['temuan']) . "</td>";
            echo "<td>" . htmlspecialchars($row['rekomendasi']) . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='8' style='text-align:center;'>Tidak ada data</td></tr>";
    }

    echo "</tbody></table>";
    exit; // jangan lanjut kirim JSON
}

// ===================================================================================
// OUTPUT JSON (default untuk dashboard)
// ===================================================================================
header('Content-Type: application/json');
echo json_encode([
    "kpi" => $kpi,
    "wilayah" => $wilayah,
    "trend" => $trend,
    "recent" => array_slice($recent, 0, 20) // untuk tabel di dashboard
]);
?>
