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
// FITUR DOWNLOAD EXCEL (langsung di sini)
// ===================================================================================
if (isset($_GET['download']) && $_GET['download'] == '1') {
    $sql = "
      SELECT lokasi, wilayah_kerja, hasil_is_sarana, hasil_lab_bakteriologis,
             hasil_lab_kimia_lengkap, hasil_kimia_ph, hasil_kimia_sisa_chlor,
             tanggal_input, rekomendasi
      FROM air_bersih
      $where_sql
      ORDER BY tanggal_input DESC
    ";
    $res = $conn->query($sql);
    if (!$res) {
        die("Query error: " . $conn->error);
    }

    // Header agar browser unduh file Excel (format tab-separated)
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"data_air_bersih.xls\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Header kolom
    echo "Lokasi\tWilayah Kerja\tHasil Sarana\tHasil Bakteriologis\tHasil Kimia Lengkap\tpH\tSisa Chlor\tTanggal Input\tRekomendasi\n";

    // Isi data
    while ($r = $res->fetch_assoc()) {
        echo $r["lokasi"] . "\t" .
             $r["wilayah_kerja"] . "\t" .
             $r["hasil_is_sarana"] . "\t" .
             $r["hasil_lab_bakteriologis"] . "\t" .
             $r["hasil_lab_kimia_lengkap"] . "\t" .
             $r["hasil_kimia_ph"] . "\t" .
             $r["hasil_kimia_sisa_chlor"] . "\t" .
             $r["tanggal_input"] . "\t" .
             $r["rekomendasi"] . "\n";
    }
    exit;
}

// ===================================================================================
// 1. KPI
// ===================================================================================
$kpi = [
    "total" => 0,
    "sarana_ms" => 0, "sarana_tms" => 0,
    "bakteri_ms" => 0, "bakteri_tms" => 0,
    "kimia_ms" => 0, "kimia_tms" => 0,
    "ms" => 0, "tms" => 0,
    "nodata" => 0
];

// Total pemeriksaan
$res = $conn->query("SELECT COUNT(*) as c FROM air_bersih $where_sql");
$kpi["total"] = (int)($res->fetch_assoc()["c"] ?? 0);

// Hitung per kategori
function hitung($conn, $where_sql, $col, $val){
    if ($where_sql && trim($where_sql) !== "") {
        $sql = "SELECT COUNT(*) as c FROM air_bersih $where_sql AND $col='" . $conn->real_escape_string($val) . "'";
    } else {
        $sql = "SELECT COUNT(*) as c FROM air_bersih WHERE $col='" . $conn->real_escape_string($val) . "'";
    }
    $res = $conn->query($sql);
    return (int)($res->fetch_assoc()["c"] ?? 0);
}

// Sarana
$kpi["sarana_ms"] = hitung($conn,$where_sql,"hasil_is_sarana","MS");
$kpi["sarana_tms"] = hitung($conn,$where_sql,"hasil_is_sarana","TMS");

// Bakteriologis
$kpi["bakteri_ms"] = hitung($conn,$where_sql,"hasil_lab_bakteriologis","MS");
$kpi["bakteri_tms"] = hitung($conn,$where_sql,"hasil_lab_bakteriologis","TMS");

// Kimia Lengkap
$kpi["kimia_ms"] = hitung($conn,$where_sql,"hasil_lab_kimia_lengkap","MS");
$kpi["kimia_tms"] = hitung($conn,$where_sql,"hasil_lab_kimia_lengkap","TMS");

// Gabungan MS & TMS
$kpi["ms"]  = $kpi["sarana_ms"] + $kpi["bakteri_ms"] + $kpi["kimia_ms"];
$kpi["tms"] = $kpi["sarana_tms"] + $kpi["bakteri_tms"] + $kpi["kimia_tms"];

// ===================================================================================
// 2. Bar Chart per Wilayah
// ===================================================================================
$wilayah = [];
$q = $conn->query("SELECT wilayah_kerja, COUNT(*) as count 
                   FROM air_bersih $where_sql 
                   GROUP BY wilayah_kerja");
while($r = $q->fetch_assoc()){
    $wilayah[] = ["wilayah_kerja"=>$r["wilayah_kerja"], "count"=>(int)$r["count"]];
}

// ===================================================================================
// 3. Line Chart Trend Bulanan
// ===================================================================================
$trend = [];
$q = $conn->query("SELECT DATE_FORMAT(tanggal_input, '%Y-%m') as bulan, COUNT(*) as count
                   FROM air_bersih $where_sql
                   GROUP BY bulan ORDER BY bulan");
while($r = $q->fetch_assoc()){
    $trend[] = ["label"=>$r["bulan"], "count"=>(int)$r["count"]];
}

// ===================================================================================
// 4. Top 5 Lokasi pH & Chlor
// ===================================================================================
$ph_cond = ($where_sql != "" ? "$where_sql AND" : "WHERE");
$top_ph_good = [];
$q = $conn->query("SELECT lokasi, MAX(CAST(hasil_kimia_ph AS DECIMAL(10,2))) as ph 
                   FROM air_bersih 
                   $ph_cond hasil_kimia_ph IS NOT NULL AND hasil_kimia_ph <> ''
                   GROUP BY lokasi 
                   ORDER BY ph DESC LIMIT 5");
while($r = $q->fetch_assoc()){
    $top_ph_good[] = ["lokasi"=>$r["lokasi"], "ph"=>(float)$r["ph"]];
}

$top_ph_bad = [];
$q = $conn->query("SELECT lokasi, MIN(CAST(hasil_kimia_ph AS DECIMAL(10,2))) as ph 
                   FROM air_bersih 
                   $ph_cond hasil_kimia_ph IS NOT NULL AND hasil_kimia_ph <> ''
                   GROUP BY lokasi 
                   ORDER BY ph ASC LIMIT 5");
while($r = $q->fetch_assoc()){
    $top_ph_bad[] = ["lokasi"=>$r["lokasi"], "ph"=>(float)$r["ph"]];
}

$chlor_cond = ($where_sql != "" ? "$where_sql AND" : "WHERE");
$top_chlor_good = [];
$q = $conn->query("SELECT lokasi, MAX(CAST(hasil_kimia_sisa_chlor AS DECIMAL(10,2))) as chlor 
                   FROM air_bersih 
                   $chlor_cond hasil_kimia_sisa_chlor IS NOT NULL AND hasil_kimia_sisa_chlor <> ''
                   GROUP BY lokasi 
                   ORDER BY chlor DESC LIMIT 5");
while($r = $q->fetch_assoc()){
    $top_chlor_good[] = ["lokasi"=>$r["lokasi"], "chlor"=>(float)$r["chlor"]];
}

$top_chlor_bad = [];
$q = $conn->query("SELECT lokasi, MIN(CAST(hasil_kimia_sisa_chlor AS DECIMAL(10,2))) as chlor 
                   FROM air_bersih 
                   $chlor_cond hasil_kimia_sisa_chlor IS NOT NULL AND hasil_kimia_sisa_chlor <> ''
                   GROUP BY lokasi 
                   ORDER BY chlor ASC LIMIT 5");
while($r = $q->fetch_assoc()){
    $top_chlor_bad[] = ["lokasi"=>$r["lokasi"], "chlor"=>(float)$r["chlor"]];
}

// ===================================================================================
// 5. Tabel Data Terbaru
// ===================================================================================
$recent = [];
$q = $conn->query("
  SELECT lokasi, wilayah_kerja, hasil_is_sarana, hasil_lab_bakteriologis, 
         hasil_lab_kimia_lengkap, hasil_kimia_ph, hasil_kimia_sisa_chlor, 
         tanggal_input, rekomendasi
  FROM air_bersih $where_sql
  ORDER BY tanggal_input DESC LIMIT 20
");
while($r = $q->fetch_assoc()){
    $recent[] = [
        "nama" => $r["lokasi"],
        "wilayah" => $r["wilayah_kerja"],
        "hasil_sarana" => $r["hasil_is_sarana"],
        "hasil_bakteri" => $r["hasil_lab_bakteriologis"],
        "hasil_kimia" => $r["hasil_lab_kimia_lengkap"],
        "ph" => $r["hasil_kimia_ph"],
        "chlor" => $r["hasil_kimia_sisa_chlor"],
        "tanggal" => $r["tanggal_input"],
        "rekomendasi" => $r["rekomendasi"]
    ];
}

// ===================================================================================
// OUTPUT JSON (default)
// ===================================================================================
header('Content-Type: application/json');
echo json_encode([
    "kpi" => $kpi,
    "wilayah" => $wilayah,
    "trend" => $trend,
    "top_ph_good" => $top_ph_good,
    "top_ph_bad" => $top_ph_bad,
    "top_chlor_good" => $top_chlor_good,
    "top_chlor_bad" => $top_chlor_bad,
    "recent" => $recent
], JSON_UNESCAPED_UNICODE);
?>
