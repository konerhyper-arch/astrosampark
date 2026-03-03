<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// ── Fetch analytics from backend ─────────────────────────────────
$analytics  = apiCall('GET', '/admin/analytics');
$summary    = $analytics['data']['summary']          ?? [];
$daily      = $analytics['data']['daily_last_30d']   ?? [];
$byCategory = $analytics['data']['by_category']      ?? [];
$topAstro   = $analytics['data']['top_astrologers']  ?? [];
$leadDist   = $analytics['data']['lead_status_dist'] ?? [];

// ── Pending count badge in sidebar ───────────────────────────────
$pendingResp = apiCall('GET', '/admin/leads?status=pending&per_page=1');
$_SESSION['pending_count'] = (int) ($pendingResp['data']['pagination']['total'] ?? 0);

// ── Build chart data ─────────────────────────────────────────────
$chartLabels  = array_column($daily, 'date');
$chartSales   = array_column($daily, 'sales');
$chartRevenue = array_column($daily, 'revenue');

// ── Recent sales (reuse top astrologers endpoint for now) ────────
$recentSales = apiCall('GET', '/admin/leads?status=sold&per_page=10');
$recentLeads = $recentSales['data']['leads'] ?? [];

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/partials/header.php';
?>

<!-- ── Stat Cards ──────────────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- Total Leads -->
    <?php
    $totalLeads = 0;
    foreach ($leadDist as $ld) {
        $totalLeads += (int) $ld['count'];
    }
    ?>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3 mb-2">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <div class="stat-value"><?= number_format($totalLeads) ?></div>
                    <div class="stat-label">Total Leads</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Approval -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3 mb-2">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <div class="stat-value"><?= number_format((int) ($_SESSION['pending_count'] ?? 0)) ?></div>
                    <div class="stat-label">Pending Approval</div>
                </div>
            </div>
            <a href="/admin/leads/queue.php" class="btn btn-sm btn-warning btn-action-sm mt-1">
                Review <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>

    <!-- Total Sales -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3 mb-2">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-bag-check-fill"></i>
                </div>
                <div>
                    <div class="stat-value"><?= number_format((int) ($summary['total_sales'] ?? 0)) ?></div>
                    <div class="stat-label">Total Sales</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Today -->
    <?php
    $todayRevenue = 0.0;
    $today = date('Y-m-d');
    foreach ($daily as $d) {
        if ($d['date'] === $today) {
            $todayRevenue = (float) $d['revenue'];
            break;
        }
    }
    ?>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3 mb-2">
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-currency-rupee"></i>
                </div>
                <div>
                    <div class="stat-value"><?= formatInr($todayRevenue) ?></div>
                    <div class="stat-label">Revenue Today</div>
                </div>
            </div>
        </div>
    </div>

</div><!-- /row stat cards -->

<!-- ── Charts Row ─────────────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- Daily Sales Chart -->
    <div class="col-xl-8">
        <div class="section-card h-100">
            <div class="section-card-header">
                <i class="bi bi-bar-chart-line text-primary"></i>
                <h5>Daily Sales — Last 30 Days</h5>
                <span class="badge bg-primary bg-opacity-10 text-primary ms-auto">
                    Total Revenue: <?= formatInr((float) ($summary['total_revenue'] ?? 0)) ?>
                </span>
            </div>
            <div class="p-3">
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Breakdown -->
    <div class="col-xl-4">
        <div class="section-card h-100">
            <div class="section-card-header">
                <i class="bi bi-pie-chart text-success"></i>
                <h5>Sales by Category</h5>
            </div>
            <div class="p-3">
                <div class="chart-container" style="height:200px;">
                    <canvas id="categoryChart"></canvas>
                </div>
                <div class="mt-3">
                    <?php foreach (array_slice($byCategory, 0, 5) as $cat): ?>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-truncate me-2"><?= htmlspecialchars($cat['category'] ?: 'Other') ?></span>
                            <span class="badge bg-light text-dark"><?= number_format((int) $cat['count']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

</div><!-- /charts row -->

<!-- ── Quick Actions ──────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="section-card">
            <div class="section-card-header">
                <i class="bi bi-lightning-fill text-warning"></i>
                <h5>Quick Actions</h5>
            </div>
            <div class="p-3 d-flex flex-wrap gap-2">
                <a href="/admin/leads/import.php" class="btn btn-primary">
                    <i class="bi bi-cloud-upload me-2"></i>Import Leads
                </a>
                <a href="/admin/leads/queue.php" class="btn btn-warning text-dark">
                    <i class="bi bi-clipboard2-check me-2"></i>Approval Queue
                    <?php if (!empty($_SESSION['pending_count'])): ?>
                        <span class="badge bg-danger ms-1"><?= (int) $_SESSION['pending_count'] ?></span>
                    <?php endif; ?>
                </a>
                <a href="/admin/leads/listings.php" class="btn btn-success">
                    <i class="bi bi-grid-3x3-gap me-2"></i>Manage Listings
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ── Recent Sold Leads ───────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- Recent sold table -->
    <div class="col-xl-8">
        <div class="admin-table">
            <div class="section-card-header p-3 border-bottom">
                <i class="bi bi-clock-history text-primary"></i>
                <h5 class="mb-0">Recently Sold Leads</h5>
                <a href="/admin/leads/listings.php" class="btn btn-sm btn-outline-primary ms-auto">
                    View All
                </a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Category</th>
                            <th>City</th>
                            <th>Quality</th>
                            <th>Badge</th>
                            <th>Sold At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentLeads)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No sold leads yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentLeads as $lead): ?>
                                <tr>
                                    <td><span class="text-muted">#<?= (int) $lead['id'] ?></span></td>
                                    <td><?= htmlspecialchars($lead['category'] ?: '—') ?></td>
                                    <td><?= htmlspecialchars($lead['city'] ?: '—') ?></td>
                                    <td>
                                        <div class="quality-bar-wrap">
                                            <?php
                                            $score = (int) $lead['quality_score'];
                                            $badge = $lead['badge'] ?? '';
                                            $barCls = 'quality-bar-' . strtolower($badge ?: 'basic');
                                            ?>
                                            <small class="fw-semibold"><?= $score ?></small>
                                            <div class="progress mt-1">
                                                <div class="progress-bar <?= $barCls ?>"
                                                     style="width:<?= $score ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= qualityBadge($score, $badge) ?></td>
                                    <td><small class="text-muted"><?= htmlspecialchars(substr($lead['created_at'] ?? '', 0, 10)) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Top Astrologers -->
    <div class="col-xl-4">
        <div class="section-card h-100">
            <div class="section-card-header">
                <i class="bi bi-trophy text-warning"></i>
                <h5>Top Astrologers</h5>
            </div>
            <div class="p-3">
                <?php if (empty($topAstro)): ?>
                    <p class="text-muted text-center small py-3">No data yet.</p>
                <?php else: ?>
                    <?php foreach (array_slice($topAstro, 0, 7) as $idx => $astro): ?>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-secondary bg-opacity-15 text-secondary fw-bold" style="width:24px;">
                                <?= $idx + 1 ?>
                            </span>
                            <div class="flex-1 text-truncate">
                                <div class="fw-semibold small"><?= htmlspecialchars($astro['name']) ?></div>
                                <div class="text-muted" style="font-size:0.7rem;">
                                    <?= (int) $astro['purchases'] ?> purchases · <?= formatInr((float) $astro['spent']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- Chart.js initialisation -->
<script>
(function () {
    const labels  = <?= json_encode(array_values($chartLabels)) ?>;
    const sales   = <?= json_encode(array_values(array_map('intval', $chartSales))) ?>;
    const revenue = <?= json_encode(array_values(array_map('floatval', $chartRevenue))) ?>;

    // ── Daily Sales line chart ──────────────────────────────────
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: labels.length ? labels : ['No data'],
            datasets: [
                {
                    label: 'Sales Count',
                    data: sales,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13,110,253,0.08)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y',
                },
                {
                    label: 'Revenue (₹)',
                    data: revenue,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25,135,84,0.08)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top' } },
            scales: {
                y:  { type: 'linear', display: true, position: 'left',  title: { display: true, text: 'Sales' } },
                y1: { type: 'linear', display: true, position: 'right', title: { display: true, text: 'Revenue (₹)' }, grid: { drawOnChartArea: false } },
            },
        },
    });

    // ── Category doughnut chart ─────────────────────────────────
    const catLabels  = <?= json_encode(array_values(array_column($byCategory, 'category'))) ?>;
    const catCounts  = <?= json_encode(array_values(array_map('intval', array_column($byCategory, 'count')))) ?>;
    const palette = ['#0d6efd','#198754','#ffc107','#dc3545','#6f42c1','#0dcaf0','#fd7e14','#6c757d'];

    const catCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(catCtx, {
        type: 'doughnut',
        data: {
            labels: catLabels.length ? catLabels : ['No data'],
            datasets: [{
                data: catCounts.length ? catCounts : [1],
                backgroundColor: palette,
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
            },
        },
    });
})();
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
