<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/quality_scorer.php';

corsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

Auth::requireAdmin();

if (empty($_FILES['csv']['tmp_name'])) {
    errorResponse('No CSV file uploaded');
}

$tmpFile = $_FILES['csv']['tmp_name'];
$handle  = fopen($tmpFile, 'r');
if (!$handle) {
    errorResponse('Could not read uploaded file', 500);
}

$pdo     = Database::getInstance()->getConnection();
$headers = null;
$inserted = 0;
$skipped  = 0;
$errors   = [];

while (($row = fgetcsv($handle)) !== false) {
    if ($headers === null) {
        $headers = array_map('strtolower', array_map('trim', $row));
        continue;
    }
    if (count($row) !== count($headers)) {
        $skipped++;
        continue;
    }
    $lead = array_combine($headers, $row);

    // Required fields
    $phone = trim($lead['phone'] ?? '');
    $name  = trim($lead['name']  ?? '');
    if (!$phone || !$name) {
        $errors[] = "Row skipped (missing name/phone): " . implode(',', $row);
        $skipped++;
        continue;
    }

    $email = trim($lead['email'] ?? '');
    $dupHash = QualityScorer::duplicateHash($phone, $email);

    // Duplicate check
    $stmtDup = $pdo->prepare('SELECT id FROM leads WHERE duplicate_hash = :hash LIMIT 1');
    $stmtDup->execute([':hash' => $dupHash]);
    if ($stmtDup->fetch()) {
        $skipped++;
        continue;
    }

    $leadArr = [
        'source'       => 'csv',
        'category'     => trim($lead['category'] ?? ''),
        'name'         => $name,
        'phone'        => $phone,
        'email'        => $email,
        'city'         => trim($lead['city'] ?? ''),
        'state'        => trim($lead['state'] ?? ''),
        'language'     => trim($lead['language'] ?? ''),
        'budget_range' => trim($lead['budget_range'] ?? ''),
        'notes'        => trim($lead['notes'] ?? ''),
        'created_at'   => date('Y-m-d H:i:s'),
    ];
    $score = QualityScorer::score($leadArr);

    $stmt = $pdo->prepare(
        'INSERT INTO leads (source, category, name, phone, email, city, state, language,
                            budget_range, notes, status, quality_score, duplicate_hash)
         VALUES ("csv", :cat, :name, :phone, :email, :city, :state, :lang,
                 :budget, :notes, "pending", :score, :hash)'
    );
    $stmt->execute([
        ':cat'    => $leadArr['category'],
        ':name'   => $leadArr['name'],
        ':phone'  => $leadArr['phone'],
        ':email'  => $leadArr['email'],
        ':city'   => $leadArr['city'],
        ':state'  => $leadArr['state'],
        ':lang'   => $leadArr['language'],
        ':budget' => $leadArr['budget_range'],
        ':notes'  => $leadArr['notes'],
        ':score'  => $score,
        ':hash'   => $dupHash,
    ]);
    $inserted++;
}
fclose($handle);

successResponse([
    'inserted' => $inserted,
    'skipped'  => $skipped,
    'errors'   => $errors,
], "$inserted leads imported");
