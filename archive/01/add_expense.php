<?php
include 'db_config.php';

$description = $_POST['description'];
$amount = $_POST['amount'];
$date = $_POST['date'];
$category = $_POST['category'];

$sql = "INSERT INTO expenses (description, amount, date, category) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sdss", $description, $amount, $date, $category);
$stmt->execute();
$stmt->close();
$conn->close();

header("Location: index.html");
?>
