<?php
// koneksi ke database
$koneksi = new mysqli("localhost", "root", "", "db_sanitasi");

// query ringkasan hasil inspeksi TPP
$query = $koneksi->query("SELECT hasil_inspeksi, COUNT(*) as jumlah FROM inspeksi_tpp GROUP BY hasil_inspeksi");

$data = [];
while ($row = $query->fetch_assoc()) {
    $data[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Utama - Sanitasi</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">

<div class="container my-4">
  <h1 class="text-center">📊 Dashboard Utama</h1>

  <div class="card p-3">
    <h5>📍 Ringkasan Hasil Inspeksi TPP</h5>
    <canvas id="barChart"></canvas>
  </div>

  <div class="mt-3 text-center">
    <a href="tpp.php" class="btn btn-primary">🍴 Dashboard TPP</a>
    <a href="air.php" class="btn btn-info">💧 Dashboard Air Bersih</a>
    <a href="ttu.php" class="btn btn-success">🏛️ Dashboard TTU</a>
  </div>
</div>

<script>
  const dataFromPHP = <?php echo json_encode($data); ?>;

  const labels = dataFromPHP.map(d => d.hasil_inspeksi);
  const values = dataFromPHP.map(d => d.jumlah);

  new Chart(document.getElementById("barChart"), {
    type: "bar",
    data: {
      labels: labels,
      datasets: [{
        label: "Jumlah",
        data: values,
        backgroundColor: ["rgba(75,192,192,0.7)", "rgba(255,99,132,0.7)"]
      }]
    }
  });
</script>
</body>
</html>
