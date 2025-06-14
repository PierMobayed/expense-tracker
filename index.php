
<?php
ob_start(); // Start output buffering
session_start();

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    //exit;
}
ob_end_flush(); // Send output after header check
?>



<!DOCTYPE html>
<html>
<head>
    <title>Welcome to Expense Tracker</title>
    <style>
        body { font-family: Arial; text-align: center; padding: 50px; background: #f0f0f0; }
        a.button {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px;
            background: #007BFF;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        a.button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>Welcome to the Expense Tracker</h1>
    <p>Please choose an option:</p>
    <a class="button" href="login.php">Log In</a>
    <a class="button" href="signup.php">Sign Up</a>
</body>
</html>
