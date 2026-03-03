    </div><!-- /page-body -->
</div><!-- /main-content -->

</div><!-- /wrapper -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<script>
// ── Sidebar toggle (mobile) ──────────────────────────────────────
(function () {
    const toggle   = document.getElementById('sidebarToggle');
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebarOverlay');

    if (toggle) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });
    }
    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }
})();

// ── Global CSRF token ────────────────────────────────────────────
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
const API_BASE   = '/api'; // relative to webroot

// ── Generic fetch helper ─────────────────────────────────────────
async function adminFetch(endpoint, options = {}) {
    const defaults = {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN,
        },
    };
    const merged = Object.assign({}, defaults, options);
    if (options.headers) {
        merged.headers = Object.assign({}, defaults.headers, options.headers);
    }
    const res = await fetch(API_BASE + endpoint, merged);
    return res.json();
}

// ── Auto-dismiss alerts ──────────────────────────────────────────
document.querySelectorAll('.alert-autodismiss').forEach(el => {
    setTimeout(() => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
        bsAlert.close();
    }, 5000);
});
</script>

</body>
</html>
