<?php
// ==================== get_tpp_data.php (updated) ====================
// Supports: normal JSON output (for dashboard) AND download mode when ?download=1
// ===================================================================

$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_sanitasi";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    // early exit with JSON error
    header('Content-Type: application/json');
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
// === ADDED: DOWNLOAD MODE (when ?download=1) =======================================
// If ?download=1 is present, output an Excel-compatible .xls (HTML table) and exit.
// ===================================================================================
if (isset($_GET['download']) && $_GET['download'] == '1') {
    $sql = "SELECT nama_tpp, lokasi, wilayah_kerja, 
                   hasil_inspeksi_status, hasil_orgonoleptik_status, hasil_mikrobiologi_status, 
                   hasil_kimiawi_status, hasil_usap_alat_status,
                   tanggal_input, temuan, rekomendasi
            FROM inspeksi_tpp
            $where_sql
            ORDER BY tanggal_input DESC";

    $res = $conn->query($sql);
    if (!$res) {
        // send a simple error (not JSON) because this is download path
        header('HTTP/1.1 500 Internal Server Error');
        echo "Query error: " . $conn->error;
        exit;
    }

    // Excel-friendly headers (HTML table inside .xls)
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"Data_TPP.xls\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Start table
    echo "<table border='1'>";
    echo "<tr>
            <th>No</th>
            <th>Nama TPP</th>
            <th>Lokasi</th>
            <th>Wilayah Kerja</th>
            <th>Hasil Inspeksi</th>
            <th>Orgonoleptik</th>
            <th>Mikrobiologi</th>
            <th>Kimiawi</th>
            <th>Usap Alat</th>
            <th>Tanggal Input</th>
            <th>Temuan</th>
            <th>Rekomendasi</th>
          </tr>";

    $no = 1;
    while ($r = $res->fetch_assoc()) {
        // sanitize output for HTML
        $nama = htmlspecialchars($r['nama_tpp']);
        $lokasi = htmlspecialchars($r['lokasi']);
        $wilayah = htmlspecialchars($r['wilayah_kerja']);
        $inspeksi = htmlspecialchars($r['hasil_inspeksi_status']);
        $org = htmlspecialchars($r['hasil_orgonoleptik_status']);
        $mikro = htmlspecialchars($r['hasil_mikrobiologi_status']);
        $kimia = htmlspecialchars($r['hasil_kimiawi_status']);
        $usap = htmlspecialchars($r['hasil_usap_alat_status']);
        $tanggal = htmlspecialchars($r['tanggal_input']);
        $temuan = htmlspecialchars($r['temuan']);
        $rekom = htmlspecialchars($r['rekomendasi']);

        echo "<tr>
                <td>{$no}</td>
                <td>{$nama}</td>
                <td>{$lokasi}</td>
                <td>{$wilayah}</td>
                <td>{$inspeksi}</td>
                <td>{$org}</td>
                <td>{$mikro}</td>
                <td>{$kimia}</td>
                <td>{$usap}</td>
                <td>{$tanggal}</td>
                <td>{$temuan}</td>
                <td>{$rekom}</td>
              </tr>";
        $no++;
    }
    echo "</table>";
    $conn->close();
    exit; // important: stop further JSON-generation
}
// ===================================================================================
// End download block
// ===================================================================================

// ===================================================================================
// 1. KPI Per Kolom + Summary Gabungan
// ===================================================================================
$kpi = [
    "total" => 0,
    "inspeksi"     => ["ms"=>0, "tms"=>0],
    "orgonoleptik" => ["ms"=>0, "tms"=>0],
    "mikrobiologi" => ["ms"=>0, "tms"=>0],
    "kimiawi"      => ["ms"=>0, "tms"=>0],
    "usap_alat"    => ["ms"=>0, "tms"=>0],
    "summary"      => ["ms"=>0, "tms"=>0]
];

$q = $conn->query("SELECT hasil_inspeksi_status, hasil_orgonoleptik_status, 
                          hasil_mikrobiologi_status, hasil_kimiawi_status, hasil_usap_alat_status
                   FROM inspeksi_tpp $where_sql");

while($r = $q->fetch_assoc()){
    // Array kolom
    $map = [
        "inspeksi"     => $r["hasil_inspeksi_status"],
        "orgonoleptik" => $r["hasil_orgonoleptik_status"],
        "mikrobiologi" => $r["hasil_mikrobiologi_status"],
        "kimiawi"      => $r["hasil_kimiawi_status"],
        "usap_alat"    => $r["hasil_usap_alat_status"]
    ];

    foreach($map as $kategori => $val){
        if ($val === "MS") {
            $kpi[$kategori]["ms"]++;
            $kpi["summary"]["ms"]++;
        } elseif ($val === "TMS") {
            $kpi[$kategori]["tms"]++;
            $kpi["summary"]["tms"]++;
        }
    }
}

// Total baris
$res = $conn->query("SELECT COUNT(*) as c FROM inspeksi_tpp $where_sql");
$kpi["total"] = (int)($res->fetch_assoc()["c"] ?? 0);

// ===================================================================================
// 2. Bar Chart per Wilayah
// ===================================================================================
$wilayah = [];
$q = $conn->query("SELECT wilayah_kerja, COUNT(*) as count 
                   FROM inspeksi_tpp $where_sql 
                   GROUP BY wilayah_kerja");
while($r = $q->fetch_assoc()){
    $wilayah[] = ["wilayah_kerja"=>$r["wilayah_kerja"], "count"=>(int)$r["count"]];
}

// ===================================================================================
// 3. Line Chart Trend Bulanan
// ===================================================================================
$trend = [];
$q = $conn->query("SELECT DATE_FORMAT(tanggal_input, '%Y-%m') as bulan, COUNT(*) as count
                   FROM inspeksi_tpp $where_sql
                   GROUP BY bulan ORDER BY bulan");
while($r = $q->fetch_assoc()){
    $trend[] = ["label"=>$r["bulan"], "count"=>(int)$r["count"]];
}

// ===================================================================================
// 4. Tabel Data Terbaru
// ===================================================================================
$recent = [];
$q = $conn->query("SELECT nama_tpp, lokasi, wilayah_kerja, 
                          hasil_inspeksi_status, hasil_orgonoleptik_status, hasil_mikrobiologi_status, 
                          hasil_kimiawi_status, hasil_usap_alat_status,
                          tanggal_input, temuan, rekomendasi
                   FROM inspeksi_tpp $where_sql
                   ORDER BY tanggal_input DESC LIMIT 20");
while($r = $q->fetch_assoc()){
    $recent[] = [
        "nama" => $r["nama_tpp"],
        "lokasi" => $r["lokasi"],
        "wilayah" => $r["wilayah_kerja"],
        "inspeksi" => $r["hasil_inspeksi_status"],
        "orgonoleptik" => $r["hasil_orgonoleptik_status"],
        "mikrobiologi" => $r["hasil_mikrobiologi_status"],
        "kimiawi" => $r["hasil_kimiawi_status"],
        "usap_alat" => $r["hasil_usap_alat_status"],
        "tanggal" => $r["tanggal_input"],
        "temuan" => $r["temuan"],
        "rekomendasi" => $r["rekomendasi"]
    ];
}

// ===================================================================================
// OUTPUT JSON (default dashboard behavior)
// ===================================================================================
header('Content-Type: application/json');
echo json_encode([
    "kpi" => $kpi,
    "wilayah" => $wilayah,
    "trend" => $trend,
    "recent" => $recent
], JSON_UNESCAPED_UNICODE);

$conn->close();
