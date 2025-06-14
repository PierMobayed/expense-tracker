<?php
session_start();
require 'db.php';

$user_id = $_SESSION["user_id"] ?? null;
if (!$user_id) {
    http_response_code(403);
    exit("Unauthorized");
}

// Handle AJAX delete request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_id"])) {
    $id = $_POST["delete_id"];
    $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    echo "deleted";
    exit;
}

// Handle date range filtering
$dateRange = $_GET["date_range"] ?? "all";
$startDate = $_GET["start_date"] ?? null;
$endDate = $_GET["end_date"] ?? null;

function getDateRangeCondition($dateRange, $startDate, $endDate) {
    $conditions = [];
    $params = [];
    
    switch ($dateRange) {
        case 'today':
            $conditions[] = "DATE(e.date) = CURDATE()";
            break;
        case 'week':
            $conditions[] = "YEARWEEK(e.date) = YEARWEEK(CURDATE())";
            break;
        case 'month':
            $conditions[] = "YEAR(e.date) = YEAR(CURDATE()) AND MONTH(e.date) = MONTH(CURDATE())";
            break;
        case 'year':
            $conditions[] = "YEAR(e.date) = YEAR(CURDATE())";
            break;
        case 'custom':
            if ($startDate) {
                $conditions[] = "e.date >= ?";
                $params[] = $startDate;
            }
            if ($endDate) {
                $conditions[] = "e.date <= ?";
                $params[] = $endDate;
            }
            break;
    }
    
    return [
        'conditions' => $conditions,
        'params' => $params
    ];
}

// Return JSON for charts
if (isset($_GET["json"])) {
    header('Content-Type: application/json');
    
    try {
        $dateRangeInfo = getDateRangeCondition($dateRange, $startDate, $endDate);
        $dateConditions = $dateRangeInfo['conditions'];
        $dateParams = $dateRangeInfo['params'];
        
        // Get category data
        $categoryQuery = "
            SELECT c.name as category, COALESCE(ROUND(SUM(e.amount), 2), 0) as total 
            FROM categories c
            LEFT JOIN expenses e ON e.category_id = c.id AND e.user_id = ?
            WHERE c.user_id = ?
        ";
        
        if (!empty($dateConditions)) {
            $categoryQuery .= " AND " . implode(" AND ", $dateConditions);
        }

        // Add category filter if selected
        if (!empty($_GET["category"])) {
            $categoryQuery = "
                SELECT c.name as category, COALESCE(ROUND(SUM(e.amount), 2), 0) as total 
                FROM categories c
                JOIN expenses e ON e.category_id = c.id AND e.user_id = ?
                WHERE c.id = ? AND c.user_id = ?
            ";
            if (!empty($dateConditions)) {
                $categoryQuery .= " AND " . implode(" AND ", $dateConditions);
            }
            $categoryQuery .= " GROUP BY c.id, c.name ORDER BY total DESC";
            $stmt = $pdo->prepare($categoryQuery);
            $params = array_merge([$user_id, $_GET["category"], $user_id], $dateParams);
        } else {
            $categoryQuery .= " GROUP BY c.id, c.name ORDER BY total DESC";
            $stmt = $pdo->prepare($categoryQuery);
            $params = array_merge([$user_id, $user_id], $dateParams);
        }
        
        $stmt->execute($params);
        $categoryData = $stmt->fetchAll();

        // Get timeline data
        $timelineQuery = "
            WITH RECURSIVE dates AS (
                SELECT CURDATE() - INTERVAL (a.a + (10 * b.a)) DAY as date
                FROM (SELECT 0 as a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) as a
                CROSS JOIN (SELECT 0 as a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) as b
                WHERE (a.a + (10 * b.a)) < 30
            )
            SELECT DATE(dates.date) as date, COALESCE(ROUND(SUM(e.amount), 2), 0) as total 
            FROM dates
            LEFT JOIN expenses e ON DATE(e.date) = dates.date AND e.user_id = ?
        ";
        
        if (!empty($dateConditions)) {
            $timelineQuery .= " AND " . implode(" AND ", $dateConditions);
        }

        // Add category filter if selected
        if (!empty($_GET["category"])) {
            $timelineQuery .= " AND e.category_id = ?";
            $dateParams[] = $_GET["category"];
        }
        
        $timelineQuery .= " GROUP BY dates.date ORDER BY dates.date ASC";
        
        $stmt = $pdo->prepare($timelineQuery);
        $params = array_merge([$user_id], $dateParams);
        $stmt->execute($params);
        $timelineData = $stmt->fetchAll();

        // Format dates for display
        $formattedDates = array_map(function($item) {
            return date('M d', strtotime($item['date']));
        }, $timelineData);

        echo json_encode([
            "labels" => array_column($categoryData, 'category'),
            "amounts" => array_map('floatval', array_column($categoryData, 'total')),
            "dates" => $formattedDates,
            "dailyAmounts" => array_map('floatval', array_column($timelineData, 'total'))
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate chart data: ' . $e->getMessage()]);
    }
    exit;
}

// Handle sorting and filtering
$sort = $_GET["sort"] ?? "date";
$order = $_GET["order"] ?? "desc";
$category = $_GET["category"] ?? "";

$validSortFields = ["date", "amount", "category", "description"];
$validOrders = ["asc", "desc"];

if (!in_array($sort, $validSortFields)) $sort = "date";
if (!in_array($order, $validOrders)) $order = "desc";

// Build the query
$query = "SELECT e.id, e.description, e.amount, e.date, c.name as category 
          FROM expenses e 
          JOIN categories c ON e.category_id = c.id 
          WHERE e.user_id = ?";
$params = [$user_id];

if (!empty($category)) {
    $query .= " AND c.id = ?";
    $params[] = $category;
}

$dateRangeInfo = getDateRangeCondition($dateRange, $startDate, $endDate);
if (!empty($dateRangeInfo['conditions'])) {
    $query .= " AND " . implode(" AND ", $dateRangeInfo['conditions']);
    $params = array_merge($params, $dateRangeInfo['params']);
}

$query .= " ORDER BY " . ($sort === "category" ? "c.name" : "e.$sort") . " $order";

// Return HTML for expense list
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

echo "<ul class='expense-list'>";
foreach ($expenses as $exp) {
    echo "<li class='expense-item'>";
    echo "<div class='expense-info'>";
    echo "<span class='expense-date'>" . date('Y-m-d', strtotime($exp['date'])) . "</span> - ";
    echo "<span class='expense-desc'>" . htmlspecialchars($exp['description']) . "</span> ";
    echo "<span class='expense-category'>(" . htmlspecialchars($exp['category']) . ")</span>: ";
    echo "<span class='expense-amount'>$" . number_format($exp['amount'], 2) . "</span>";
    echo "</div>";
    echo "<button class='delete-expense' data-id='{$exp['id']}'>🗑️</button>";
    echo "</li>";
}
echo "</ul>";
?>
