<?php
header('Content-Type: application/json');

$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_sanitasi";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

// --- PARAMETER FILTER ---
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';
$wilayah    = $_GET['wilayah_kerja'] ?? '';

$where_sql = " WHERE 1=1 ";
$where_sql_no_wilayah = " WHERE 1=1 ";

if ($start_date && $end_date) {
    $where_sql .= " AND tanggal_input BETWEEN '" . $conn->real_escape_string($start_date) . "' 
                   AND '" . $conn->real_escape_string($end_date) . "'";
    $where_sql_no_wilayah .= " AND tanggal_input BETWEEN '" . $conn->real_escape_string($start_date) . "' 
                              AND '" . $conn->real_escape_string($end_date) . "'";
}

if (!empty($wilayah)) {
    $where_sql .= " AND wilayah_kerja = '" . $conn->real_escape_string($wilayah) . "'";
}

// Helper function
function runQuery($conn, $sql){
    $res = $conn->query($sql);
    if(!$res){
        echo json_encode(["error"=>$conn->error,"query"=>$sql]);
        exit;
    }
    return $res;
}

// ================= KPI =================
$kpi = [
    "total_surveys" => 0,
    "total_tpp" => 0,
    "total_air" => 0,
    "total_ttu" => 0,
    "total_sertifikat" => 0
];

// Hitung total TPP, Air Bersih, TTU
$q = runQuery($conn,"SELECT COUNT(*) as c FROM inspeksi_tpp $where_sql");
$kpi["total_tpp"] = (int)$q->fetch_assoc()["c"];

$q = runQuery($conn,"SELECT COUNT(*) as c FROM air_bersih $where_sql");
$kpi["total_air"] = (int)$q->fetch_assoc()["c"];

$q = runQuery($conn,"SELECT COUNT(*) as c FROM tempat_umum $where_sql");
$kpi["total_ttu"] = (int)$q->fetch_assoc()["c"];

// ================== TOTAL SERTIFIKAT (PAKAI 1 QUERY SAJA) ==================
// ✅ Perubahan utama: sertif_air pakai SUM(jumlah)
$q = "
    SELECT 
        SUM(sertif_air) AS sertif_air,
        SUM(slhs) AS slhs,
        SUM(penjamah) AS penjamah,
        SUM(sertif_air + slhs + penjamah) AS total_sertifikat
    FROM (
        SELECT SUM(jumlah) AS sertif_air, 0 AS slhs, 0 AS penjamah FROM sertif_air $where_sql
        UNION ALL
        SELECT 0 AS sertif_air, COUNT(*) AS slhs, 0 AS penjamah FROM sertifikat_slhs $where_sql_no_wilayah
        UNION ALL
        SELECT 0 AS sertif_air, 0 AS slhs, COUNT(*) AS penjamah FROM sertifikat_penjamah $where_sql_no_wilayah
    ) AS combined
";
$res = runQuery($conn, $q);
$row = $res->fetch_assoc();

$kpi["sertif_air"]       = (int)($row["sertif_air"] ?? 0);
$kpi["slhs"]             = (int)($row["slhs"] ?? 0);
$kpi["penjamah"]         = (int)($row["penjamah"] ?? 0);
$kpi["total_sertifikat"] = (int)($row["total_sertifikat"] ?? 0);

// ================== TOTAL SURVEY (GABUNG SEMUA INSPEKSI) ==================
$kpi["total_surveys"] = $kpi["total_tpp"] + $kpi["total_air"] + $kpi["total_ttu"];

// ================= STATUS DISTRIBUSI MS/TMS =================
$q = runQuery($conn,"
    SELECT hasil_final, COUNT(*) as c FROM (
      -- TPP
      SELECT COALESCE(NULLIF(TRIM(hasil_inspeksi_status),''),'Tidak Ada Catatan') AS hasil_final, tanggal_input, wilayah_kerja FROM inspeksi_tpp
      UNION ALL
      SELECT COALESCE(NULLIF(TRIM(hasil_orgonoleptik_status),''),'Tidak Ada Catatan'), tanggal_input, wilayah_kerja FROM inspeksi_tpp
      UNION ALL
      SELECT COALESCE(NULLIF(TRIM(hasil_mikrobiologi_status),''),'Tidak Ada Catatan'), tanggal_input, wilayah_kerja FROM inspeksi_tpp
      UNION ALL
      SELECT COALESCE(NULLIF(TRIM(hasil_kimiawi_status),''),'Tidak Ada Catatan'), tanggal_input, wilayah_kerja FROM inspeksi_tpp
      UNION ALL
      SELECT COALESCE(NULLIF(TRIM(hasil_usap_alat_status),''),'Tidak Ada Catatan'), tanggal_input, wilayah_kerja FROM inspeksi_tpp
      -- Air Bersih
      UNION ALL
      SELECT COALESCE(NULLIF(TRIM(hasil_is_sarana),''),'Tidak Ada Catatan'), tanggal_input, wilayah_kerja FROM air_bersih
      UNION ALL
      SELECT COALESCE(NULLIF(TRIM(hasil_lab_bakteriologis),''),'Tidak Ada Catatan'), tanggal_input, wilayah_kerja FROM air_bersih
      UNION ALL
      SELECT COALESCE(NULLIF(TRIM(hasil_lab_kimia_lengkap),''),'Tidak Ada Catatan'), tanggal_input, wilayah_kerja FROM air_bersih
      -- TTU
      UNION ALL
      SELECT COALESCE(NULLIF(TRIM(hasil_is_sarana),''),'Tidak Ada Catatan'), tanggal_input, wilayah_kerja FROM tempat_umum
      UNION ALL
      SELECT COALESCE(NULLIF(TRIM(hasil_laboratorium),''),'Tidak Ada Catatan'), tanggal_input, wilayah_kerja FROM tempat_umum
    ) as all_data
    $where_sql
    GROUP BY hasil_final
");

$status_dist = ["ms_count"=>0,"tms_count"=>0];
while($row = $q->fetch_assoc()){
    if($row["hasil_final"] === "MS"){
        $status_dist["ms_count"] = (int)$row["c"];
    } elseif($row["hasil_final"] === "TMS"){
        $status_dist["tms_count"] = (int)$row["c"];
    }
}

// ================= DISTRIBUSI WILAYAH =================
$q = runQuery($conn,"
    SELECT wilayah_kerja, COUNT(*) as c FROM (
      SELECT wilayah_kerja, tanggal_input FROM inspeksi_tpp
      UNION ALL
      SELECT wilayah_kerja, tanggal_input FROM air_bersih
      UNION ALL
      SELECT wilayah_kerja, tanggal_input FROM tempat_umum
    ) as all_data
    $where_sql
    GROUP BY wilayah_kerja
");
$wilayah_dist = [];
while($row = $q->fetch_assoc()){
    $wilayah_dist[] = [
        "wilayah_kerja" => $row["wilayah_kerja"],
        "count" => (int)$row["c"]
    ];
}

// ================= TREN BULANAN =================
$q = runQuery($conn,"
    SELECT YEAR(tanggal_input) as y, MONTH(tanggal_input) as m, 'tpp' as category, COUNT(*) as c
    FROM inspeksi_tpp $where_sql GROUP BY y,m
    UNION ALL
    SELECT YEAR(tanggal_input), MONTH(tanggal_input), 'air_bersih' as category, COUNT(*)
    FROM air_bersih $where_sql GROUP BY YEAR(tanggal_input), MONTH(tanggal_input)
    UNION ALL
    SELECT YEAR(tanggal_input), MONTH(tanggal_input), 'ttu' as category, COUNT(*)
    FROM tempat_umum $where_sql GROUP BY YEAR(tanggal_input), MONTH(tanggal_input)
    ORDER BY y,m
");
$monthly_trend = [];
while($row = $q->fetch_assoc()){
    $monthly_trend[] = [
        "year" => (int)$row["y"],
        "month"=> (int)$row["m"],
        "category" => $row["category"],
        "count" => (int)$row["c"]
    ];
}

// ================= DATA TERBARU =================
$q = runQuery($conn,"
    SELECT 'TPP' as kategori, nama_tpp as nama, wilayah_kerja, tanggal_input, temuan, rekomendasi
    FROM inspeksi_tpp
    UNION ALL
    SELECT 'Air Bersih', lokasi, wilayah_kerja, tanggal_input, temuan, rekomendasi
    FROM air_bersih
    UNION ALL
    SELECT 'TTU', lokasi, wilayah_kerja, tanggal_input, temuan, rekomendasi
    FROM tempat_umum
    ORDER BY tanggal_input DESC LIMIT 10
");
$recent_entries = [];
while($row = $q->fetch_assoc()){
    $recent_entries[] = [
        "category"=>$row["kategori"],
        "name"=>$row["nama"],
        "wilayah"=>$row["wilayah_kerja"],
        "date"=>$row["tanggal_input"],
        "temuan"=>$row["temuan"],
        "recommendation"=>$row["rekomendasi"]
    ];
}

// ================= RETURN JSON =================
echo json_encode([
    "kpi"=>$kpi,
    "status_dist"=>$status_dist,
    "wilayah_dist"=>$wilayah_dist,
    "monthly_trend"=>$monthly_trend,
    "recent_entries"=>$recent_entries
], JSON_PRETTY_PRINT);

$conn->close();
?>
