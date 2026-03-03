<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

// ── Filters ───────────────────────────────────────────────────────
$filterCategory = trim($_GET['category'] ?? '');
$filterSource   = trim($_GET['source']   ?? '');
$page           = max(1, (int) ($_GET['page'] ?? 1));

$qs = http_build_query(array_filter([
    'status'   => 'pending',
    'page'     => $page,
    'per_page' => 25,
    'category' => $filterCategory,
    'source'   => $filterSource,
]));

$resp       = apiCall('GET', '/admin/leads/list?' . $qs);
$leads      = $resp['data']['leads']      ?? [];
$pagination = $resp['data']['pagination'] ?? [];

$pageTitle  = 'Approval Queue';
$activePage = 'queue';
require_once __DIR__ . '/../partials/header.php';
?>

<!-- Page heading -->
<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-clipboard2-check me-2 text-warning"></i>Approval Queue</h4>
        <p class="text-muted mb-0 small">
            <?= number_format((int) ($pagination['total'] ?? 0)) ?> pending lead(s) awaiting review
        </p>
    </div>
</div>

<!-- ── Filters ─────────────────────────────────────────────────── -->
<form method="GET" class="section-card p-3 mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-sm-4 col-md-3">
            <label class="form-label small fw-semibold mb-1">Category</label>
            <input type="text" class="form-control form-control-sm" name="category"
                   placeholder="e.g. marriage" value="<?= htmlspecialchars($filterCategory) ?>">
        </div>
        <div class="col-sm-4 col-md-3">
            <label class="form-label small fw-semibold mb-1">Source</label>
            <select class="form-select form-select-sm" name="source">
                <option value="">All Sources</option>
                <?php foreach (['csv', 'meta', 'web', 'manual'] as $src): ?>
                    <option value="<?= $src ?>" <?= $filterSource === $src ? 'selected' : '' ?>>
                        <?= ucfirst($src) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-4 col-md-2">
            <button type="submit" class="btn btn-sm btn-primary w-100">
                <i class="bi bi-funnel me-1"></i>Filter
            </button>
        </div>
        <?php if ($filterCategory || $filterSource): ?>
            <div class="col-sm-4 col-md-2">
                <a href="/admin/leads/queue.php" class="btn btn-sm btn-outline-secondary w-100">
                    <i class="bi bi-x-circle me-1"></i>Clear
                </a>
            </div>
        <?php endif; ?>
    </div>
</form>

<!-- ── Notification area ───────────────────────────────────────── -->
<div id="queueNotice"></div>

<!-- ── Table ──────────────────────────────────────────────────── -->
<div class="admin-table mb-4">
    <div class="table-responsive">
        <table class="table" id="queueTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Category</th>
                    <th>City</th>
                    <th>Quality</th>
                    <th>Badge</th>
                    <th>Source</th>
                    <th>Created</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            No pending leads found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leads as $lead):
                        $score = (int) $lead['quality_score'];
                        $badge = $lead['badge'] ?? '';
                        $badgeCls = 'quality-bar-' . strtolower($badge ?: 'basic');
                        $srcCls   = 'source-' . strtolower($lead['source'] ?? '');
                    ?>
                        <tr id="row-<?= (int) $lead['id'] ?>">
                            <td class="text-muted small">#<?= (int) $lead['id'] ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($lead['name']) ?></td>
                            <td><span class="phone-masked"><?= htmlspecialchars(maskPhone($lead['phone'])) ?></span></td>
                            <td><?= htmlspecialchars($lead['category'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($lead['city'] ?: '—') ?></td>
                            <td>
                                <div class="quality-bar-wrap">
                                    <small class="fw-bold"><?= $score ?></small>
                                    <div class="progress mt-1">
                                        <div class="progress-bar <?= $badgeCls ?>" style="width:<?= $score ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= qualityBadge($score, $badge) ?></td>
                            <td><span class="badge <?= $srcCls ?>"><?= htmlspecialchars(strtoupper($lead['source'] ?? '')) ?></span></td>
                            <td><small class="text-muted"><?= htmlspecialchars(substr($lead['created_at'] ?? '', 0, 10)) ?></small></td>
                            <td class="text-center">
                                <button class="btn btn-success btn-action-sm me-1"
                                        onclick="approveLead(<?= (int) $lead['id'] ?>)"
                                        title="Approve">
                                    <i class="bi bi-check-lg"></i> Approve
                                </button>
                                <button class="btn btn-danger btn-action-sm"
                                        onclick="openRejectModal(<?= (int) $lead['id'] ?>, <?= htmlspecialchars(json_encode($lead['name']), ENT_QUOTES) ?>)"
                                        title="Reject">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if (!empty($pagination['last_page']) && $pagination['last_page'] > 1): ?>
        <div class="p-3 border-top d-flex justify-content-between align-items-center">
            <small class="text-muted">
                Page <?= (int) $pagination['page'] ?> of <?= (int) $pagination['last_page'] ?>
                &mdash; <?= number_format((int) $pagination['total']) ?> total
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($p = 1; $p <= $pagination['last_page']; $p++): ?>
                        <?php
                        $pqs = http_build_query(array_filter([
                            'page' => $p, 'category' => $filterCategory, 'source' => $filterSource,
                        ]));
                        ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= $pqs ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     REJECT MODAL
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="rejectModalLabel">
                    <i class="bi bi-x-circle text-danger me-2"></i>Reject Lead
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted" id="rejectLeadName">Lead: —</p>
                <div class="mb-3">
                    <label for="rejectReason" class="form-label fw-semibold">Rejection Reason</label>
                    <textarea class="form-control" id="rejectReason" rows="3"
                              placeholder="e.g. Duplicate, fake number, wrong category..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmRejectBtn">
                    <i class="bi bi-x-circle me-1"></i>Reject Lead
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     CREATE LISTING MODAL (shown after successful approve)
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="listingModal" tabindex="-1" aria-labelledby="listingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="listingModalLabel">
                    <i class="bi bi-tags text-success me-2"></i>Create Listing
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="listingResult"></div>
                <input type="hidden" id="listingLeadId">

                <div class="mb-3">
                    <label for="listingPrice" class="form-label fw-semibold">
                        Listing Price (₹) <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input type="number" class="form-control" id="listingPrice"
                               min="1" step="0.01" placeholder="e.g. 499">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="listingVisibility" class="form-label fw-semibold">Visibility</label>
                    <select class="form-select" id="listingVisibility">
                        <option value="public">Public (all astrologers)</option>
                        <option value="private">Targeted / Private</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="listingMinRating" class="form-label fw-semibold">
                        Min Rating Required
                        <span class="text-muted small">(0 = no restriction)</span>
                    </label>
                    <input type="number" class="form-control" id="listingMinRating"
                           min="0" max="5" step="0.1" value="0">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Skip for now
                </button>
                <button type="button" class="btn btn-success" id="createListingBtn"
                        onclick="submitListing()">
                    <i class="bi bi-plus-circle me-1"></i>Create Listing
                </button>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * Mask phone number, showing only last 4 digits.
 */
function maskPhone(string $phone): string
{
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) >= 4) {
        return str_repeat('*', strlen($phone) - 4) . substr($phone, -4);
    }
    return str_repeat('*', strlen($phone));
}
?>

<script>
const rejectModal  = new bootstrap.Modal(document.getElementById('rejectModal'));
const listingModal = new bootstrap.Modal(document.getElementById('listingModal'));
let currentRejectId = null;

// ── Show notice ──────────────────────────────────────────────────
function showNotice(msg, type = 'success') {
    const el = document.getElementById('queueNotice');
    el.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show alert-autodismiss">
            <i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'} me-2"></i>
            ${msg}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
}

// ── Approve ──────────────────────────────────────────────────────
async function approveLead(leadId) {
    const btn = document.querySelector(`#row-${leadId} .btn-success`);
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }

    try {
        const resp = await adminFetch('/admin/leads/approve', {
            method: 'POST',
            body: JSON.stringify({ lead_id: leadId }),
        });
        if (resp.success) {
            showNotice(`Lead #${leadId} approved successfully.`);
            // Remove row from table
            const row = document.getElementById(`row-${leadId}`);
            if (row) row.remove();
            // Open listing modal
            document.getElementById('listingLeadId').value = leadId;
            document.getElementById('listingResult').innerHTML = '';
            document.getElementById('listingModalLabel').innerHTML =
                `<i class="bi bi-tags text-success me-2"></i>Create Listing — Lead #${leadId}`;
            listingModal.show();
        } else {
            showNotice(resp.message || 'Approval failed.', 'danger');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Approve'; }
        }
    } catch (e) {
        showNotice('Request failed: ' + e.message, 'danger');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Approve'; }
    }
}

// ── Open reject modal ────────────────────────────────────────────
function openRejectModal(leadId, leadName) {
    currentRejectId = leadId;
    document.getElementById('rejectLeadName').textContent = `Lead: #${leadId} — ${leadName}`;
    document.getElementById('rejectReason').value = '';
    rejectModal.show();
}

// ── Confirm reject ───────────────────────────────────────────────
document.getElementById('confirmRejectBtn').addEventListener('click', async function () {
    if (!currentRejectId) return;
    const reason = document.getElementById('rejectReason').value.trim();
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Rejecting...';

    try {
        const resp = await adminFetch('/admin/leads/reject', {
            method: 'POST',
            body: JSON.stringify({ lead_id: currentRejectId, reason }),
        });
        rejectModal.hide();
        if (resp.success) {
            showNotice(`Lead #${currentRejectId} rejected.`);
            const row = document.getElementById(`row-${currentRejectId}`);
            if (row) row.remove();
        } else {
            showNotice(resp.message || 'Rejection failed.', 'danger');
        }
    } catch (e) {
        rejectModal.hide();
        showNotice('Request failed: ' + e.message, 'danger');
    } finally {
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-x-circle me-1"></i>Reject Lead';
    }
});

// ── Submit listing ───────────────────────────────────────────────
async function submitListing() {
    const leadId    = parseInt(document.getElementById('listingLeadId').value, 10);
    const price     = parseFloat(document.getElementById('listingPrice').value);
    const vis       = document.getElementById('listingVisibility').value;
    const minRating = parseFloat(document.getElementById('listingMinRating').value) || 0;
    const resultEl  = document.getElementById('listingResult');

    if (!price || price <= 0) {
        resultEl.innerHTML = '<div class="alert alert-warning small">Please enter a valid price.</div>';
        return;
    }

    const btn = document.getElementById('createListingBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating...';

    try {
        const resp = await adminFetch('/admin/leads/listing_create', {
            method: 'POST',
            body: JSON.stringify({ lead_id: leadId, price, visibility: vis, min_rating_required: minRating }),
        });
        if (resp.success) {
            resultEl.innerHTML = `<div class="alert alert-success small"><i class="bi bi-check-circle-fill me-2"></i>Listing #${resp.data?.listing_id} created!</div>`;
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Created';
            setTimeout(() => listingModal.hide(), 1500);
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
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
