<?php
require_once 'includes/db_connect.php';

try {
    echo "Checking JOIN validity for Pending News...\n";
    $sql = "
        SELECT n.news_id, n.title, c.name as cat_name, u.full_name as author
        FROM news n 
        LEFT JOIN categories c ON n.category_id = c.category_id 
        LEFT JOIN users u ON n.author_id = u.user_id 
        WHERE n.status = 'pending'
    ";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);

    echo "\nChecking Author ID 6:\n";
    $stmt = $pdo->query("SELECT * FROM users WHERE user_id = 6");
    print_r($stmt->fetch(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
