<?php
require 'db.php';

try {
    // Start transaction
    $pdo->beginTransaction();

    // Check if categories table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'categories'")->rowCount() > 0;

    if (!$tableExists) {
        // Create categories table if it doesn't exist
        $pdo->exec("CREATE TABLE categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            user_id INT,
            FOREIGN KEY (user_id) REFERENCES users(id),
            UNIQUE KEY unique_category_per_user (name, user_id)
        )");

        // Insert default categories for each user
        $pdo->exec("INSERT INTO categories (name, user_id)
            SELECT c.name, u.id
            FROM (
                SELECT 'Food' as name UNION ALL
                SELECT 'Transport' UNION ALL
                SELECT 'Utilities' UNION ALL
                SELECT 'Entertainment' UNION ALL
                SELECT 'Shopping' UNION ALL
                SELECT 'Healthcare' UNION ALL
                SELECT 'Education' UNION ALL
                SELECT 'Other'
            ) c
            CROSS JOIN users u");
    } else {
        // Check if user_id column exists in categories table
        $columns = $pdo->query("SHOW COLUMNS FROM categories")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('user_id', $columns)) {
            // Add user_id column if it doesn't exist
            $pdo->exec("ALTER TABLE categories 
                ADD COLUMN user_id INT AFTER name,
                ADD FOREIGN KEY (user_id) REFERENCES users(id),
                ADD UNIQUE KEY unique_category_per_user (name, user_id)");

            // Update existing categories to be associated with all users
            $pdo->exec("INSERT INTO categories (name, user_id)
                SELECT c.name, u.id
                FROM categories c
                CROSS JOIN users u
                WHERE c.user_id IS NULL");
        }
    }

    // Check if expenses table has category_id column
    $columns = $pdo->query("SHOW COLUMNS FROM expenses")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('category_id', $columns)) {
        // Add category_id column if it doesn't exist
        $pdo->exec("ALTER TABLE expenses 
            ADD COLUMN category_id INT AFTER date,
            ADD FOREIGN KEY (category_id) REFERENCES categories(id)");

        // Update existing expenses to use category_id
        $pdo->exec("UPDATE expenses e
            JOIN categories c ON e.category = c.name AND c.user_id = e.user_id
            SET e.category_id = c.id
            WHERE e.category_id IS NULL");
    }

    // Commit transaction
    $pdo->commit();
    echo "Database migration completed successfully!";

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo "Error during migration: " . $e->getMessage();
}
?> 