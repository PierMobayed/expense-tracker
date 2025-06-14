<?php
// Default database configuration
$config = [
    'host' => 'localhost',
    'db'   => 'if0_39224150_expenses',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4'
];

// Try to load remote credentials from config file
$configFile = __DIR__ . '/db_config.php';
if (file_exists($configFile)) {
    require $configFile;
    // If config file exists, it should define $config array with remote credentials
}

$dsn = "mysql:host={$config['host']};dbname={$config['db']};charset={$config['charset']}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
} catch (PDOException $e) {
    // If connection fails and we're not already using localhost, try localhost
    if ($config['host'] !== 'localhost') {
        try {
            $config['host'] = 'localhost';
            $config['user'] = 'root';
            $config['pass'] = '';
            $dsn = "mysql:host={$config['host']};dbname={$config['db']};charset={$config['charset']}";
            $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
        } catch (PDOException $e2) {
            die("Database connection failed: " . $e2->getMessage());
        }
    } else {
        die("Database connection failed: " . $e->getMessage());
    }
}
?>
