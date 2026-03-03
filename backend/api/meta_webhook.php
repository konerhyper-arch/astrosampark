<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/quality_scorer.php';

corsHeaders();

$method = $_SERVER['REQUEST_METHOD'];

// ---------------------------------------------------------------
// GET: Webhook verification challenge from Meta
// ---------------------------------------------------------------
if ($method === 'GET') {
    $mode      = $_GET['hub_mode']         ?? '';
    $token     = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge']    ?? '';

    $expectedToken = getenv('META_VERIFY_TOKEN') ?: 'astrosampark_meta_token';

    if ($mode === 'subscribe' && hash_equals($expectedToken, $token)) {
        http_response_code(200);
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }
    errorResponse('Forbidden', 403);
}

// ---------------------------------------------------------------
// POST: Receive lead data from Meta Lead Ads
// ---------------------------------------------------------------
if ($method === 'POST') {
    $rawBody = file_get_contents('php://input');

    // Verify X-Hub-Signature-256
    $appSecret = getenv('META_APP_SECRET') ?: '';
    $hubSig    = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

    if ($appSecret && $hubSig) {
        $expectedSig = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
        if (!hash_equals($expectedSig, $hubSig)) {
            errorResponse('Invalid signature', 403);
        }
    }

    $payload = json_decode($rawBody, true);
    if (!$payload) {
        errorResponse('Invalid JSON payload');
    }

    $pdo = Database::getInstance()->getConnection();

    // Meta sends an array of entries (pages) each with changes (forms)
    $entries = $payload['entry'] ?? [];
    $mapped  = 0;

    foreach ($entries as $entry) {
        $pageId  = $entry['id'] ?? null;
        $changes = $entry['changes'] ?? [];

        foreach ($changes as $change) {
            $value  = $change['value'] ?? [];
            $formId = $value['form_id']    ?? null;
            $metaLeadId = $value['leadgen_id'] ?? null;

            if (!$metaLeadId) continue;

            // Store raw payload (idempotent)
            $stmtRaw = $pdo->prepare(
                'INSERT IGNORE INTO meta_lead_raw (page_id, form_id, lead_id_meta, raw_payload, received_at)
                 VALUES (:pid, :fid, :lid, :payload, NOW())'
            );
            $stmtRaw->execute([
                ':pid'     => $pageId,
                ':fid'     => $formId,
                ':lid'     => $metaLeadId,
                ':payload' => json_encode($value),
            ]);
            if ($stmtRaw->rowCount() === 0) {
                // Already processed
                continue;
            }

            // Extract field data from Meta Lead Ads payload
            $fields     = [];
            $fieldData  = $value['field_data'] ?? [];
            foreach ($fieldData as $fd) {
                $fields[$fd['name']] = $fd['values'][0] ?? '';
            }

            $phone = trim($fields['phone_number'] ?? $fields['phone'] ?? '');
            $name  = trim($fields['full_name']    ?? $fields['name']  ?? 'Unknown');
            $email = trim($fields['email']         ?? '');

            if (!$phone) continue;

            $category = trim($fields['category'] ?? 'general');
            $city     = trim($fields['city']     ?? '');
            $notes    = trim($fields['notes']    ?? $fields['message'] ?? '');

            $dupHash = QualityScorer::duplicateHash($phone, $email);

            // Duplicate check
            $stmtDup = $pdo->prepare('SELECT id FROM leads WHERE duplicate_hash = :hash LIMIT 1');
            $stmtDup->execute([':hash' => $dupHash]);
            $existing = $stmtDup->fetch();

            if ($existing) {
                $pdo->prepare('UPDATE meta_lead_raw SET mapped_lead_id = :lid WHERE lead_id_meta = :mid')
                    ->execute([':lid' => $existing['id'], ':mid' => $metaLeadId]);
                continue;
            }

            $leadData = [
                'source'     => 'meta_ads',
                'category'   => $category,
                'name'       => $name,
                'phone'      => $phone,
                'email'      => $email,
                'city'       => $city,
                'notes'      => $notes,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $score = QualityScorer::score($leadData);

            $stmtLead = $pdo->prepare(
                'INSERT INTO leads (source, category, name, phone, email, city, notes,
                                    status, quality_score, duplicate_hash)
                 VALUES ("meta_ads", :cat, :name, :phone, :email, :city, :notes,
                         "pending", :score, :hash)'
            );
            $stmtLead->execute([
                ':cat'   => $category,
                ':name'  => $name,
                ':phone' => $phone,
                ':email' => $email,
                ':city'  => $city,
                ':notes' => $notes,
                ':score' => $score,
                ':hash'  => $dupHash,
            ]);
            $newLeadId = (int) $pdo->lastInsertId();

            // Link raw record to mapped lead
            $pdo->prepare('UPDATE meta_lead_raw SET mapped_lead_id = :lid WHERE lead_id_meta = :mid')
                ->execute([':lid' => $newLeadId, ':mid' => $metaLeadId]);

            // Activity log
            $pdo->prepare(
                'INSERT INTO lead_activity_logs (lead_id, actor_type, action, meta_json)
                 VALUES (:lid, "webhook", "meta_lead_received", :meta)'
            )->execute([
                ':lid'  => $newLeadId,
                ':meta' => json_encode(['meta_lead_id' => $metaLeadId, 'form_id' => $formId]),
            ]);

            $mapped++;
        }
    }

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'mapped' => $mapped]);
    exit;
}

errorResponse('Method not allowed', 405);
