<?php
require 'auth_check.php';
require 'db.php';

$user_id = $_SESSION["user_id"];

// Handle category actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["add"])) {
        $name = trim($_POST["name"]);
        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name, user_id) VALUES (?, ?)");
                $stmt->execute([$name, $user_id]);
            } catch (PDOException $e) {
                $error = "Category already exists.";
            }
        }
    } elseif (isset($_POST["delete"])) {
        $id = $_POST["id"];
        // Check if category is in use
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE category_id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Cannot delete category that is in use.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
        }
    }
}

// Get user's categories
$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Categories</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .error { color: red; margin: 10px 0; }
        .category-list { margin: 20px 0; }
        .category-item {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .delete-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
        }
        .add-form {
            margin: 20px 0;
        }
        input[type="text"] {
            padding: 5px;
            margin-right: 10px;
        }
        button {
            padding: 5px 10px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <h1>Manage Categories</h1>
    <?php if (isset($error)): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <form method="post" class="add-form">
        <input type="text" name="name" placeholder="New category name" required>
        <button type="submit" name="add">Add Category</button>
    </form>

    <div class="category-list">
        <?php foreach ($categories as $category): ?>
            <div class="category-item">
                <span><?= htmlspecialchars($category["name"]) ?></span>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="id" value="<?= $category["id"] ?>">
                    <button type="submit" name="delete" class="delete-btn" 
                            onclick="return confirm('Delete this category?')">Delete</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

    <p><a href="dashboard.php">Back to Dashboard</a></p>
</body>
</html> 