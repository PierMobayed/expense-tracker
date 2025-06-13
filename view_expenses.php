<?php
include 'db_config.php';

$sql = "SELECT category, SUM(amount) as total FROM expenses GROUP BY category";
$result = $conn->query($sql);

$labels = [];
$amounts = [];

while ($row = $result->fetch_assoc()) {
    $labels[] = $row['category'];
    $amounts[] = $row['total'];
}

echo json_encode(['labels' => $labels, 'amounts' => $amounts]);
$conn->close();
?>
