<?php
require_once 'includes/db_connect.php';

try {
    echo "Checking Pending News...\n";
    $stmt = $pdo->query("SELECT news_id, title, status, author_id FROM news WHERE status = 'pending'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Count: " . count($rows) . "\n";
    print_r($rows);
    
    echo "\nChecking Enum Stats:\n";
    $stmt = $pdo->query("SELECT status, COUNT(*) as c FROM news GROUP BY status");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
