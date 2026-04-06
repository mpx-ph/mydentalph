<?php
require_once 'db.php';
try {
    $stmt = $pdo->query("DESCRIBE tbl_patient_files");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($columns, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
