<?php
header('Content-Type: application/json');

// Koneksi ke database
$conn = new mysqli("localhost", "root", "", "db_sanitasi");

// Cek koneksi
if ($conn->connect_error) {
    echo json_encode(['error' => "Koneksi database gagal: " . $conn->connect_error]);
    exit();
}

// ===========================================
// FUNGSI UNTUK MENGAMBIL OPSI WILAYAH
// ===========================================
if (isset($_GET['get_wilayah']) && $_GET['get_wilayah'] == 'true') {
    $sql = "SELECT DISTINCT wilayah_kerja FROM inspeksi_tpp ORDER BY wilayah_kerja ASC";
    $result = $conn->query($sql);

    $wilayah = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $wilayah[] = $row['wilayah_kerja'];
        }
    }
    echo json_encode(['wilayah' => $wilayah]);
    $conn->close();
    exit();
}

// ===========================================
// AMBIL PARAMETER FILTER
// ===========================================
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
$wilayah_filter = isset($_GET['wilayah']) ? $_GET['wilayah'] : null;

// Validasi tanggal
if (!$start_date || !$end_date || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $end_date)) {
    echo json_encode(['error' => "Format tanggal tidak valid."]);
    exit();
}

$response_data = [
    'kpi' => [
        'totalInspeksi' => 0,
        'totalNamaTPP' => 0,
        'persentaseMS' => 0,
        'totalTemuan' => 0,
    ],
    'charts' => [
        'statusKepatuhanPerLokasi' => ['labels' => [], 'ms' => [], 'tms' => []],
        'distribusiStatus' => ['ms' => 0, 'tms' => 0],
        'topTemuan' => ['labels' => [], 'counts' => []],
        'trenInspeksi' => ['labels' => [], 'ms' => [], 'tms' => [], 'total' => []],
    ]
];

// Base WHERE clause for date and wilayah
$where_clauses = ["tanggal_input BETWEEN ? AND ?"];
$param_types = "ss";
$params = [$start_date, $end_date];

if (!empty($wilayah_filter)) {
    $where_clauses[] = "wilayah_kerja = ?";
    $param_types .= "s";
    $params[] = $wilayah_filter;
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// ===========================================
// 1. KPI - Key Performance Indicators
// ===========================================

// Total Kegiatan Inspeksi TPP
$stmt = $conn->prepare("SELECT COUNT(id) AS total_inspeksi FROM inspeksi_tpp {$where_sql}");
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$response_data['kpi']['totalInspeksi'] = $row['total_inspeksi'];
$stmt->close();

// Total nama TPP yang diawasi (unique)
$stmt = $conn->prepare("SELECT COUNT(DISTINCT nama_tpp) AS total_unique_tpp FROM inspeksi_tpp {$where_sql}");
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$response_data['kpi']['totalNamaTPP'] = $row['total_unique_tpp'];
$stmt->close();

// Persentase TPP yang memenuhi syarat (MS) dari total inspeksi
// Menggunakan hasil_inspeksi_status sebagai metrik utama MS/TMS
$stmt = $conn->prepare("SELECT 
    COUNT(CASE WHEN hasil_inspeksi_status = 'MS' THEN 1 ELSE NULL END) AS total_ms,
    COUNT(id) AS total_inspeksi_status
    FROM inspeksi_tpp {$where_sql}");
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_ms = $row['total_ms'];
$total_inspeksi_status = $row['total_inspeksi_status'];
if ($total_inspeksi_status > 0) {
    $response_data['kpi']['persentaseMS'] = round(($total_ms / $total_inspeksi_status) * 100, 1);
} else {
    $response_data['kpi']['persentaseMS'] = 0;
}
$stmt->close();

// Total Temuan Dicatat
// Kita asumsikan kolom 'temuan' berisi string yang dipisahkan oleh '; '
$stmt = $conn->prepare("SELECT temuan FROM inspeksi_tpp {$where_sql} AND temuan IS NOT NULL AND temuan != ''");
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$total_temuan_count = 0;
while ($row = $result->fetch_assoc()) {
    $individual_temuan = explode('; ', $row['temuan']);
    $total_temuan_count += count($individual_temuan);
}
$response_data['kpi']['totalTemuan'] = $total_temuan_count;
$stmt->close();

// ===========================================
// 2. Status Kepatuhan per Lokasi (Grafik Batang Horizontal)
// ===========================================
$stmt = $conn->prepare("SELECT
    lokasi,
    COUNT(CASE WHEN hasil_inspeksi_status = 'MS' THEN 1 ELSE NULL END) AS ms_count,
    COUNT(CASE WHEN hasil_inspeksi_status = 'TMS' THEN 1 ELSE NULL END) AS tms_count
    FROM inspeksi_tpp
    {$where_sql}
    GROUP BY lokasi
    ORDER BY (ms_count + tms_count) DESC
    LIMIT 10"); // Ambil 10 lokasi teratas
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $response_data['charts']['statusKepatuhanPerLokasi']['labels'][] = $row['lokasi'];
    $response_data['charts']['statusKepatuhanPerLokasi']['ms'][] = $row['ms_count'];
    $response_data['charts']['statusKepatuhanPerLokasi']['tms'][] = $row['tms_count'];
}
$stmt->close();

// ===========================================
// 3. Distribusi Status Kepatuhan (Grafik Donat)
// ===========================================
$stmt = $conn->prepare("SELECT
    COUNT(CASE WHEN hasil_inspeksi_status = 'MS' THEN 1 ELSE NULL END) AS total_ms,
    COUNT(CASE WHEN hasil_inspeksi_status = 'TMS' THEN 1 ELSE NULL END) AS total_tms
    FROM inspeksi_tpp {$where_sql}");
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$response_data['charts']['distribusiStatus']['ms'] = $row['total_ms'];
$response_data['charts']['distribusiStatus']['tms'] = $row['total_tms'];
$stmt->close();

// ===========================================
// 4. Top 5 Temuan Terbanyak (Grafik Batang Horizontal)
// ===========================================
// Ini akan sedikit lebih kompleks karena 'temuan' adalah string yang digabungkan.
// Kita perlu memecah string temuan dan menghitung frekuensinya.
$all_temuan = [];
$stmt = $conn->prepare("SELECT temuan FROM inspeksi_tpp {$where_sql} AND temuan IS NOT NULL AND temuan != ''");
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $individual_temuan = array_map('trim', explode('; ', $row['temuan']));
    foreach ($individual_temuan as $temuan_item) {
        if (!empty($temuan_item)) {
            $all_temuan[] = $temuan_item;
        }
    }
}
$temuan_counts = array_count_values($all_temuan);
arsort($temuan_counts); // Urutkan dari yang terbanyak
$top_5_temuan = array_slice($temuan_counts, 0, 5, true);

foreach ($top_5_temuan as $temuan_label => $count) {
    $response_data['charts']['topTemuan']['labels'][] = $temuan_label;
    $response_data['charts']['topTemuan']['counts'][] = $count;
}
$stmt->close();

// ===========================================
// 5. Tren Hasil Inspeksi per Bulan (Grafik Garis)
// ===========================================
$stmt = $conn->prepare("SELECT
    DATE_FORMAT(tanggal_input, '%Y-%m') AS month,
    COUNT(id) AS total,
    COUNT(CASE WHEN hasil_inspeksi_status = 'MS' THEN 1 ELSE NULL END) AS ms_count,
    COUNT(CASE WHEN hasil_inspeksi_status = 'TMS' THEN 1 ELSE NULL END) AS tms_count
    FROM inspeksi_tpp
    {$where_sql}
    GROUP BY month
    ORDER BY month ASC");
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$tren_data = [];
while ($row = $result->fetch_assoc()) {
    $tren_data[$row['month']] = [
        'total' => $row['total'],
        'ms_count' => $row['ms_count'],
        'tms_count' => $row['tms_count']
    ];
}
$stmt->close();

// Isi bulan yang kosong jika tidak ada data
$current_date = new DateTime($start_date);
$end_dt = new DateTime($end_date);
while ($current_date <= $end_dt) {
    $month_key = $current_date->format('Y-m');
    $response_data['charts']['trenInspeksi']['labels'][] = $current_date->format('M Y'); // Format untuk tampilan chart
    $response_data['charts']['trenInspeksi']['total'][] = $tren_data[$month_key]['total'] ?? 0;
    $response_data['charts']['trenInspeksi']['ms'][] = $tren_data[$month_key]['ms_count'] ?? 0;
    $response_data['charts']['trenInspeksi']['tms'][] = $tren_data[$month_key]['tms_count'] ?? 0;
    $current_date->modify('+1 month');
}


$conn->close();

echo json_encode($response_data);
?>