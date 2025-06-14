<?php
session_start();
require 'db.php';

// Prevent direct access
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    exit("Invalid request method");
}

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    exit("User not logged in");
}

// Validate required fields
if (!isset($_POST["description"]) || !isset($_POST["amount"]) || !isset($_POST["date"]) || !isset($_POST["category"])) {
    exit("Missing required fields");
}

// Get and sanitize input
$desc = trim($_POST["description"]);
$amount = floatval($_POST["amount"]);
$date = $_POST["date"];
$category_id = intval($_POST["category"]);
$user_id = $_SESSION["user_id"];

// Validate amount
if ($amount <= 0) {
    exit("Invalid amount");
}

try {
    // Verify category belongs to user
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
    $stmt->execute([$category_id, $user_id]);
    if (!$stmt->fetch()) {
        exit("Invalid category");
    }

    // Insert expense
    $stmt = $pdo->prepare("INSERT INTO expenses (description, amount, date, category_id, user_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$desc, $amount, $date, $category_id, $user_id]);
    
    // Return success response
    http_response_code(200);
    echo "success";
} catch (PDOException $e) {
    // Log error and return error response
    error_log("Error adding expense: " . $e->getMessage());
    http_response_code(500);
    echo "error";
}
?>
