<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

$pageTitle  = 'Import Leads';
$activePage = 'import';

// ── Handle CSV upload ─────────────────────────────────────────────
$uploadResult  = null;
$uploadError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_csv') {
    verifyCsrf();

    if (empty($_FILES['csv']['tmp_name'])) {
        $uploadError = 'Please select a CSV file to upload.';
    } elseif ($_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $uploadError = 'File upload error code: ' . (int) $_FILES['csv']['error'];
    } else {
        $mime = mime_content_type($_FILES['csv']['tmp_name']);
        $ext  = strtolower(pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION));
        // Validate it is really a CSV / plain text
        $allowedMimes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
        if ($ext !== 'csv' || (!in_array($mime, $allowedMimes, true) && !str_starts_with($mime, 'text/'))) {
            $uploadError = 'Invalid file type. Only .csv files are accepted.';
        } else {
            $uploadResult = apiUpload('/admin/leads/upload_csv', $_FILES['csv']['tmp_name'], 'csv');
        }
    }
}

require_once __DIR__ . '/../partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-xl-9">

        <!-- Page heading -->
        <div class="d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-cloud-upload fs-4 text-primary"></i>
            <div>
                <h4 class="mb-0 fw-bold">Import Leads</h4>
                <p class="text-muted mb-0 small">Upload a CSV file or sync from Meta Lead Ads</p>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-0" id="importTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="csv-tab" data-bs-toggle="tab"
                        data-bs-target="#csvTab" type="button">
                    <i class="bi bi-file-earmark-spreadsheet me-2"></i>CSV Upload
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="meta-tab" data-bs-toggle="tab"
                        data-bs-target="#metaTab" type="button">
                    <i class="bi bi-facebook me-2"></i>Meta Lead Ads
                </button>
            </li>
        </ul>

        <div class="tab-content section-card" style="border-top-left-radius:0; border-top:none;">

            <!-- ═══ Tab 1: CSV Upload ═══════════════════════════════ -->
            <div class="tab-pane fade show active p-4" id="csvTab" role="tabpanel">

                <?php if ($uploadError): ?>
                    <div class="alert alert-danger alert-dismissible fade show alert-autodismiss">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?= htmlspecialchars($uploadError) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($uploadResult): ?>
                    <?php if (!empty($uploadResult['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <strong>Import complete!</strong>
                            <?= htmlspecialchars($uploadResult['message'] ?? '') ?>
                            <ul class="mt-2 mb-0 small">
                                <li><strong><?= (int) ($uploadResult['data']['inserted'] ?? 0) ?></strong> leads imported</li>
                                <li><strong><?= (int) ($uploadResult['data']['skipped']  ?? 0) ?></strong> skipped (duplicates / missing fields)</li>
                                <?php if (!empty($uploadResult['data']['errors'])): ?>
                                    <li class="text-danger">
                                        <?= count($uploadResult['data']['errors']) ?> row error(s) —
                                        <a class="text-danger" data-bs-toggle="collapse" href="#errorList">show</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                            <?php if (!empty($uploadResult['data']['errors'])): ?>
                                <div class="collapse mt-2" id="errorList">
                                    <ul class="small mb-0">
                                        <?php foreach ($uploadResult['data']['errors'] as $err): ?>
                                            <li><?= htmlspecialchars($err) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-x-circle-fill me-2"></i>
                            <?= htmlspecialchars($uploadResult['message'] ?? 'Import failed.') ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Upload form -->
                <form method="POST" enctype="multipart/form-data" id="csvForm">
                    <input type="hidden" name="action" value="upload_csv">
                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="mb-4">
                        <label for="csvFile" class="form-label fw-semibold">Choose CSV File</label>
                        <input type="file" class="form-control" id="csvFile" name="csv"
                               accept=".csv" required>
                        <div class="form-text">Maximum file size: 10 MB</div>
                    </div>

                    <button type="submit" class="btn btn-primary" id="uploadBtn">
                        <i class="bi bi-upload me-2"></i>Upload & Import
                    </button>
                </form>

                <!-- CSV format guide -->
                <hr class="my-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2 text-info"></i>CSV Format</h6>
                <p class="text-muted small mb-2">
                    The first row must be a header row with the following column names (order does not matter):
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered small">
                        <thead class="table-light">
                            <tr>
                                <th>Column</th>
                                <th>Required</th>
                                <th>Description / Example</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><code>name</code></td>        <td><span class="badge bg-danger">Yes</span></td>  <td>Full name — <em>Ramesh Kumar</em></td></tr>
                            <tr><td><code>phone</code></td>       <td><span class="badge bg-danger">Yes</span></td>  <td>10-digit mobile — <em>9876543210</em></td></tr>
                            <tr><td><code>email</code></td>       <td><span class="badge bg-secondary">No</span></td><td>Email address — <em>ramesh@example.com</em></td></tr>
                            <tr><td><code>city</code></td>        <td><span class="badge bg-secondary">No</span></td><td><em>Mumbai</em></td></tr>
                            <tr><td><code>state</code></td>       <td><span class="badge bg-secondary">No</span></td><td><em>Maharashtra</em></td></tr>
                            <tr><td><code>category</code></td>    <td><span class="badge bg-secondary">No</span></td><td>Astrology niche — <em>marriage / career / health</em></td></tr>
                            <tr><td><code>language</code></td>    <td><span class="badge bg-secondary">No</span></td><td><em>Hindi</em></td></tr>
                            <tr><td><code>budget_range</code></td><td><span class="badge bg-secondary">No</span></td><td><em>500-1000</em></td></tr>
                            <tr><td><code>notes</code></td>       <td><span class="badge bg-secondary">No</span></td><td>Customer notes / query details</td></tr>
                            <tr><td><code>consent_proof</code></td><td><span class="badge bg-secondary">No</span></td><td>Consent reference / URL</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-light border small mt-3">
                    <i class="bi bi-download me-2 text-primary"></i>
                    <strong>Sample CSV:</strong>
                    <code>name,phone,email,city,state,category,language,budget_range,notes,consent_proof</code><br>
                    <code>Ramesh Kumar,9876543210,ramesh@gmail.com,Mumbai,Maharashtra,marriage,Hindi,500-1000,Need urgent help,yes</code>
                </div>
            </div>
            <!-- /CSV tab -->

            <!-- ═══ Tab 2: Meta Lead Ads ════════════════════════════ -->
            <div class="tab-pane fade p-4" id="metaTab" role="tabpanel">

                <div class="alert alert-info">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <strong>How it works:</strong> Configure your Meta webhook URL in Facebook Business Manager
                    to push leads automatically. You can also trigger a manual sync below.
                </div>

                <!-- Webhook URL -->
                <div class="mb-4">
                    <label class="form-label fw-semibold">Webhook URL (copy to Meta Business Manager)</label>
                    <div class="input-group">
                        <input type="text" class="form-control font-monospace"
                               id="webhookUrl" readonly
                               value="<?= htmlspecialchars((getenv('APP_URL') ?: 'https://your-domain.com') . '/api/meta_webhook.php') ?>">
                        <button class="btn btn-outline-secondary" type="button" id="copyWebhookBtn"
                                onclick="copyWebhook()">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                    <div class="form-text">
                        Set this as the callback URL in your Meta App → Webhooks → leads subscription.
                    </div>
                </div>

                <!-- Manual sync form -->
                <hr>
                <h6 class="fw-bold mb-3">Manual Sync / Webhook Test</h6>
                <div id="metaResult"></div>

                <div class="mb-3">
                    <label for="metaToken" class="form-label fw-semibold">Page Access Token</label>
                    <input type="text" class="form-control font-monospace" id="metaToken"
                           placeholder="EAAx...long token...">
                </div>
                <div class="mb-3">
                    <label for="metaFormId" class="form-label fw-semibold">Lead Form ID</label>
                    <input type="text" class="form-control" id="metaFormId"
                           placeholder="123456789012345">
                </div>

                <button class="btn btn-primary" type="button" id="metaSyncBtn"
                        onclick="triggerMetaSync()">
                    <i class="bi bi-arrow-repeat me-2"></i>Trigger Webhook Test
                </button>

                <div class="mt-4">
                    <h6 class="fw-semibold small text-muted">VERIFY TOKEN (set in Meta App)</h6>
                    <code class="d-block p-2 bg-light rounded">astrosampark_meta_webhook_verify</code>
                </div>
            </div>
            <!-- /Meta tab -->

        </div><!-- /tab-content -->
    </div>
</div>

<script>
function copyWebhook() {
    const el = document.getElementById('webhookUrl');
    navigator.clipboard.writeText(el.value).then(() => {
        const btn = document.getElementById('copyWebhookBtn');
        btn.innerHTML = '<i class="bi bi-check2"></i> Copied!';
        setTimeout(() => { btn.innerHTML = '<i class="bi bi-clipboard"></i> Copy'; }, 2000);
    });
}

async function triggerMetaSync() {
    const token  = document.getElementById('metaToken').value.trim();
    const formId = document.getElementById('metaFormId').value.trim();
    const resultEl = document.getElementById('metaResult');

    if (!token || !formId) {
        resultEl.innerHTML = `<div class="alert alert-warning">Please fill in the Page Access Token and Form ID.</div>`;
        return;
    }

    const btn = document.getElementById('metaSyncBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Syncing...';

    try {
        const resp = await adminFetch('/webhook/meta', {
            method: 'POST',
            body: JSON.stringify({ token, form_id: formId, _test: true }),
        });
        if (resp.success) {
            resultEl.innerHTML = `<div class="alert alert-success alert-autodismiss"><i class="bi bi-check-circle-fill me-2"></i>${resp.message || 'Sync triggered successfully.'}</div>`;
        } else {
            resultEl.innerHTML = `<div class="alert alert-danger"><i class="bi bi-x-circle-fill me-2"></i>${resp.message || 'Sync failed.'}</div>`;
        }
    } catch (e) {
        resultEl.innerHTML = `<div class="alert alert-danger">Request failed: ${e.message}</div>`;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Trigger Webhook Test';
    }
}

// Show upload progress
document.getElementById('csvForm').addEventListener('submit', function () {
    const btn = document.getElementById('uploadBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading...';
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
