<?php
// api/setup_doctors.php
// Visit this link once in your browser to setup the doctors: 
// https://mydentalph.ct.ws/api/setup_doctors.php

require_once '../db.php';
header('Content-Type: application/json');

$tenant_id = 'TNT_00025'; // Default tenant for Sisigdentals

$doctors = [
    ['id' => 1, 'first' => 'Sarah', 'last' => 'Lee', 'spec' => 'Senior Orthodontist'],
    ['id' => 2, 'first' => 'James', 'last' => 'Chen', 'spec' => 'General Dentist'],
    ['id' => 3, 'first' => 'Danie', 'last' => 'Chen', 'spec' => 'Cosmetic Dentist']
];

$results = [];

try {
    foreach ($doctors as $doc) {
        // Check if exists
        $stmt = $pdo->prepare("SELECT dentist_id FROM tbl_dentists WHERE dentist_id = ?");
        $stmt->execute([$doc['id']]);
        if ($stmt->fetch()) {
            $results[] = "Doctor {$doc['first']} {$doc['last']} already exists (ID: {$doc['id']})";
            continue;
        }

        $stmt = $pdo->prepare("INSERT INTO tbl_dentists (dentist_id, tenant_id, first_name, last_name, specialization, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$doc['id'], $tenant_id, $doc['first'], $doc['last'], $doc['spec']]);
        $results[] = "Registered Doctor {$doc['first']} {$doc['last']} (ID: {$doc['id']})";
    }

    echo json_encode([
        "status" => "success",
        "message" => "Doctors setup completed",
        "details" => $results
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
