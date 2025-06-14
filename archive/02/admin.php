<?php
require 'auth_check.php';
require 'db.php';

if (!$_SESSION["is_admin"]) {
    echo "Access denied.";
    exit;
}

// Handle user actions
if (isset($_GET["action"]) && isset($_GET["user_id"])) {
    $user_id = $_GET["user_id"];
    
    switch ($_GET["action"]) {
        case "delete":
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND id != ?");
            $stmt->execute([$user_id, $_SESSION["user_id"]]); // Prevent self-deletion
            break;
        case "block":
            $stmt = $pdo->prepare("UPDATE users SET blocked = 1 WHERE id = ? AND id != ?");
            $stmt->execute([$user_id, $_SESSION["user_id"]]); // Prevent self-blocking
            break;
        case "unblock":
            $stmt = $pdo->prepare("UPDATE users SET blocked = 0 WHERE id = ?");
            $stmt->execute([$user_id]);
            break;
    }
    header("Location: admin.php");
    exit;
}

$users = $pdo->query("SELECT id, username, is_admin, blocked FROM users")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f5f5f5; }
        .action-btn {
            padding: 5px 10px;
            margin: 2px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            color: white;
        }
        .delete-btn { background-color: #dc3545; }
        .block-btn { background-color: #ffc107; }
        .unblock-btn { background-color: #28a745; }
        .header { display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>
    <div class="header">
        <h2>User Management</h2>
        <a href="logout.php">Logout</a>
    </div>

    <table>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Admin</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($users as $user): ?>
        <tr>
            <td><?= $user["id"] ?></td>
            <td><?= htmlspecialchars($user["username"]) ?></td>
            <td><?= $user["is_admin"] ? "Yes" : "No" ?></td>
            <td><?= $user["blocked"] ? "Blocked" : "Active" ?></td>
            <td>
                <?php if ($user["id"] != $_SESSION["user_id"]): ?>
                    <button class="action-btn delete-btn" onclick="confirmAction('delete', <?= $user['id'] ?>)">Delete</button>
                    <?php if ($user["blocked"]): ?>
                        <button class="action-btn unblock-btn" onclick="confirmAction('unblock', <?= $user['id'] ?>)">Unblock</button>
                    <?php else: ?>
                        <button class="action-btn block-btn" onclick="confirmAction('block', <?= $user['id'] ?>)">Block</button>
                    <?php endif; ?>
                <?php else: ?>
                    <em>Current User</em>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <script>
    function confirmAction(action, userId) {
        const messages = {
            delete: 'Are you sure you want to delete this user?',
            block: 'Are you sure you want to block this user?',
            unblock: 'Are you sure you want to unblock this user?'
        };
        
        if (confirm(messages[action])) {
            window.location.href = `admin.php?action=${action}&user_id=${userId}`;
        }
    }
    </script>
</body>
</html>