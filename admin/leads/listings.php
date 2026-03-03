<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

// ── Filters & pagination ──────────────────────────────────────────
$filterStatus   = trim($_GET['status']   ?? '');
$filterCategory = trim($_GET['category'] ?? '');
$page           = max(1, (int) ($_GET['page'] ?? 1));

// Allowed status values for the filter dropdown
$statusOptions = ['', 'approved', 'sold', 'expired'];

if (!in_array($filterStatus, $statusOptions, true)) {
    $filterStatus = '';
}

// ── Fetch listings from backend (uses leads list + join data) ─────
// We query "approved" + sold + expired leads and merge with listing data.
// The backend /admin/leads/list endpoint supports all statuses.
$allListings = [];
$pagination  = ['page' => $page, 'last_page' => 1, 'total' => 0];

$qs = http_build_query(array_filter([
    'status'   => $filterStatus,
    'page'     => $page,
    'per_page' => 20,
    'category' => $filterCategory,
]));

$resp        = apiCall('GET', '/admin/leads?' . $qs);
$allListings = $resp['data']['leads']      ?? [];
$pagination  = $resp['data']['pagination'] ?? $pagination;

// ── Handle deactivate / reactivate (AJAX handled by JS) ──────────
// These are done client-side with fetch(), so no server-side form handling needed here.

$pageTitle  = 'Listings';
$activePage = 'listings';
require_once __DIR__ . '/../partials/header.php';
?>

<!-- Page heading -->
<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <div>
        <h4 class="mb-0 fw-bold">
            <i class="bi bi-grid-3x3-gap me-2 text-success"></i>Listings Management
        </h4>
        <p class="text-muted mb-0 small">
            <?= number_format((int) ($pagination['total'] ?? 0)) ?> listing(s) found
        </p>
    </div>
    <div class="ms-auto">
        <a href="/admin/leads/queue.php" class="btn btn-warning btn-sm">
            <i class="bi bi-clipboard2-check me-1"></i>Approval Queue
        </a>
    </div>
</div>

<!-- ── Filters ─────────────────────────────────────────────────── -->
<form method="GET" class="section-card p-3 mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-sm-4 col-md-3">
            <label class="form-label small fw-semibold mb-1">Status</label>
            <select class="form-select form-select-sm" name="status">
                <option value="">All Statuses</option>
                <?php foreach (['approved', 'sold', 'expired', 'pending', 'rejected'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>>
                        <?= ucfirst($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-4 col-md-3">
            <label class="form-label small fw-semibold mb-1">Category</label>
            <input type="text" class="form-control form-control-sm" name="category"
                   placeholder="e.g. marriage" value="<?= htmlspecialchars($filterCategory) ?>">
        </div>
        <div class="col-sm-4 col-md-2">
            <button type="submit" class="btn btn-sm btn-primary w-100">
                <i class="bi bi-funnel me-1"></i>Filter
            </button>
        </div>
        <?php if ($filterStatus || $filterCategory): ?>
            <div class="col-sm-4 col-md-2">
                <a href="/admin/leads/listings.php" class="btn btn-sm btn-outline-secondary w-100">
                    <i class="bi bi-x-circle me-1"></i>Clear
                </a>
            </div>
        <?php endif; ?>
    </div>
</form>

<!-- ── Notice area ─────────────────────────────────────────────── -->
<div id="listingsNotice"></div>

<!-- ── Table ──────────────────────────────────────────────────── -->
<div class="admin-table mb-4">
    <div class="table-responsive">
        <table class="table" id="listingsTable">
            <thead>
                <tr>
                    <th>Lead ID</th>
                    <th>Category</th>
                    <th>City / State</th>
                    <th>Quality</th>
                    <th>Badge</th>
                    <th>Status</th>
                    <th>Source</th>
                    <th>Created</th>
                    <th>Approved</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allListings)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            No listings found for the selected filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($allListings as $lead):
                        $score    = (int) $lead['quality_score'];
                        $badge    = $lead['badge'] ?? '';
                        $badgeCls = 'quality-bar-' . strtolower($badge ?: 'basic');
                        $srcCls   = 'source-' . strtolower($lead['source'] ?? '');
                        $status   = $lead['status'] ?? 'unknown';
                    ?>
                        <tr id="lrow-<?= (int) $lead['id'] ?>">
                            <td class="text-muted small fw-semibold">#<?= (int) $lead['id'] ?></td>
                            <td><?= htmlspecialchars($lead['category'] ?: '—') ?></td>
                            <td>
                                <span><?= htmlspecialchars($lead['city'] ?: '—') ?></span>
                                <?php if (!empty($lead['state'])): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($lead['state']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="quality-bar-wrap">
                                    <small class="fw-bold"><?= $score ?></small>
                                    <div class="progress mt-1">
                                        <div class="progress-bar <?= $badgeCls ?>" style="width:<?= $score ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= qualityBadge($score, $badge) ?></td>
                            <td><?= statusPill($status) ?></td>
                            <td>
                                <span class="badge <?= $srcCls ?>">
                                    <?= htmlspecialchars(strtoupper($lead['source'] ?? '')) ?>
                                </span>
                            </td>
                            <td><small class="text-muted"><?= htmlspecialchars(substr($lead['created_at'] ?? '', 0, 10)) ?></small></td>
                            <td><small class="text-muted"><?= htmlspecialchars(substr($lead['approved_at'] ?? '', 0, 10) ?: '—') ?></small></td>
                            <td class="text-center">
                                <?php if ($status === 'approved'): ?>
                                    <!-- Create listing -->
                                    <button class="btn btn-success btn-action-sm me-1"
                                            onclick="openCreateListing(<?= (int) $lead['id'] ?>)"
                                            title="Create listing">
                                        <i class="bi bi-tags"></i>
                                    </button>
                                <?php endif; ?>

                                <?php if (in_array($status, ['approved', 'sold'], true)): ?>
                                    <!-- Mark expired / deactivate -->
                                    <button class="btn btn-outline-secondary btn-action-sm"
                                            onclick="deactivateLead(<?= (int) $lead['id'] ?>)"
                                            title="Mark as expired">
                                        <i class="bi bi-slash-circle"></i>
                                    </button>
                                <?php elseif ($status === 'expired'): ?>
                                    <!-- Re-approve -->
                                    <button class="btn btn-outline-primary btn-action-sm"
                                            onclick="reactivateLead(<?= (int) $lead['id'] ?>)"
                                            title="Re-activate (approve)">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if (!empty($pagination['last_page']) && (int) $pagination['last_page'] > 1): ?>
        <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">
                Page <?= (int) $pagination['page'] ?> of <?= (int) $pagination['last_page'] ?>
                &mdash; <?= number_format((int) $pagination['total']) ?> total
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $lastPage = (int) $pagination['last_page'];
                    // Show max 7 page links with ellipsis
                    $range = [];
                    for ($p = max(1, $page - 3); $p <= min($lastPage, $page + 3); $p++) {
                        $range[] = $p;
                    }
                    if (!in_array(1, $range)) {
                        array_unshift($range, 1);
                    }
                    if (!in_array($lastPage, $range)) {
                        $range[] = $lastPage;
                    }
                    $prev = null;
                    foreach ($range as $p):
                        if ($prev !== null && $p - $prev > 1): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif;
                        $pqs = http_build_query(array_filter([
                            'page' => $p, 'status' => $filterStatus, 'category' => $filterCategory,
                        ]));
                        ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= $pqs ?>"><?= $p ?></a>
                        </li>
                    <?php $prev = $p; endforeach; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     CREATE LISTING MODAL
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="createListingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-tags text-success me-2"></i>
                    Create Listing — Lead <span id="clLeadIdLabel"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="clResult"></div>
                <input type="hidden" id="clLeadId">

                <div class="mb-3">
                    <label for="clPrice" class="form-label fw-semibold">
                        Price (₹) <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input type="number" class="form-control" id="clPrice" min="1" step="0.01">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="clVisibility" class="form-label fw-semibold">Visibility</label>
                    <select class="form-select" id="clVisibility">
                        <option value="public">Public (all astrologers)</option>
                        <option value="private">Targeted / Private</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="clMinRating" class="form-label fw-semibold">
                        Min Rating Required
                        <span class="text-muted small">(0 = no restriction)</span>
                    </label>
                    <input type="number" class="form-control" id="clMinRating"
                           min="0" max="5" step="0.1" value="0">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="clSubmitBtn" onclick="submitCreateListing()">
                    <i class="bi bi-plus-circle me-1"></i>Create Listing
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const createListingModal = new bootstrap.Modal(document.getElementById('createListingModal'));

function showNotice(msg, type = 'success') {
    document.getElementById('listingsNotice').innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show alert-autodismiss">
            <i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'} me-2"></i>
            ${msg}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
}

function openCreateListing(leadId) {
    document.getElementById('clLeadId').value       = leadId;
    document.getElementById('clLeadIdLabel').textContent = '#' + leadId;
    document.getElementById('clResult').innerHTML   = '';
    document.getElementById('clPrice').value        = '';
    document.getElementById('clVisibility').value   = 'public';
    document.getElementById('clMinRating').value    = '0';
    const btn = document.getElementById('clSubmitBtn');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Create Listing';
    createListingModal.show();
}

async function submitCreateListing() {
    const leadId    = parseInt(document.getElementById('clLeadId').value, 10);
    const price     = parseFloat(document.getElementById('clPrice').value);
    const vis       = document.getElementById('clVisibility').value;
    const minRating = parseFloat(document.getElementById('clMinRating').value) || 0;
    const resultEl  = document.getElementById('clResult');

    if (!price || price <= 0) {
        resultEl.innerHTML = '<div class="alert alert-warning small">Please enter a valid price.</div>';
        return;
    }

    const btn = document.getElementById('clSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating...';

    try {
        const resp = await adminFetch('/admin/leads/listing', {
            method: 'POST',
            body: JSON.stringify({ lead_id: leadId, price, visibility: vis, min_rating_required: minRating }),
        });
        if (resp.success) {
            resultEl.innerHTML = `<div class="alert alert-success small"><i class="bi bi-check-circle-fill me-2"></i>Listing #${resp.data?.listing_id} created!</div>`;
            btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Created';
            showNotice(`Listing created for Lead #${leadId}.`);
            setTimeout(() => createListingModal.hide(), 1500);
        } else {
            resultEl.innerHTML = `<div class="alert alert-danger small">${resp.message || 'Create failed.'}</div>`;
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Create Listing';
        }
    } catch (e) {
        resultEl.innerHTML = `<div class="alert alert-danger small">Request failed: ${e.message}</div>`;
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Create Listing';
    }
}

async function deactivateLead(leadId) {
    if (!confirm(`Mark lead #${leadId} as expired?`)) return;
    try {
        const resp = await adminFetch(`/leads/${leadId}/status`, {
            method: 'POST',
            body: JSON.stringify({ status: 'expired' }),
        });
        if (resp.success) {
            showNotice(`Lead #${leadId} marked as expired.`);
            const row = document.getElementById(`lrow-${leadId}`);
            if (row) setTimeout(() => location.reload(), 800);
        } else {
            showNotice(resp.message || 'Operation failed.', 'danger');
        }
    } catch (e) {
        showNotice('Request failed: ' + e.message, 'danger');
    }
}

async function reactivateLead(leadId) {
    if (!confirm(`Re-activate lead #${leadId} (set to approved)?`)) return;
    try {
        const resp = await adminFetch('/admin/leads/approve', {
            method: 'POST',
            body: JSON.stringify({ lead_id: leadId }),
        });
        if (resp.success) {
            showNotice(`Lead #${leadId} re-activated.`);
            setTimeout(() => location.reload(), 800);
        } else {
            showNotice(resp.message || 'Operation failed.', 'danger');
        }
    } catch (e) {
        showNotice('Request failed: ' + e.message, 'danger');
    }
}
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
