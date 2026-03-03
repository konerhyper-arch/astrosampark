<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers/response.php';

corsHeaders();

// Parse the request URI, strip query string and leading slash
$requestUri  = $_SERVER['REQUEST_URI']  ?? '/';
$scriptName  = $_SERVER['SCRIPT_NAME']  ?? '';

// Remove script path prefix (useful when not in docroot)
$basePath = rtrim(dirname($scriptName), '/');
$path     = '/' . trim(substr(parse_url($requestUri, PHP_URL_PATH), strlen($basePath)), '/');

$method = $_SERVER['REQUEST_METHOD'];

// ---------------------------------------------------------------
// Route table
// Pattern => [file, param_names_from_path_segment]
// We support simple {id} placeholders.
// ---------------------------------------------------------------
$routes = [
    // Auth
    'POST /auth/register'     => ['api/auth/register.php',  []],
    'POST /auth/login'        => ['api/auth/login.php',     []],

    // Marketplace
    'GET /leads/marketplace'  => ['api/leads/marketplace.php', []],
    'GET /leads/my'           => ['api/leads/my_leads.php',    []],

    // Lead actions with {lead_id}
    'GET /leads/{id}/preview'         => ['api/leads/preview.php',        ['lead_id']],
    'POST /leads/{id}/buy'            => ['api/leads/buy.php',            ['lead_id']],
    'POST /leads/{id}/status'         => ['api/leads/update_status.php',  ['lead_id']],
    'POST /leads/{id}/rate'           => ['api/leads/rate.php',           ['lead_id']],
    'POST /leads/{id}/refund_request' => ['api/leads/refund_request.php', ['lead_id']],

    // Wallet
    'GET /wallet/balance'             => ['api/wallet/balance.php',         []],
    'GET /wallet/transactions'        => ['api/wallet/transactions.php',    []],
    'POST /wallet/recharge/create_order' => ['api/wallet/recharge_create.php', []],
    'POST /wallet/recharge/verify'    => ['api/wallet/recharge_verify.php', []],

    // Admin
    'GET /admin/leads'                => ['api/admin/leads_list.php',    []],
    'POST /admin/leads/upload_csv'    => ['api/admin/upload_csv.php',    []],
    'POST /admin/leads/approve'       => ['api/admin/approve.php',       []],
    'POST /admin/leads/reject'        => ['api/admin/reject.php',        []],
    'POST /admin/leads/listing'       => ['api/admin/listing_create.php',[]],
    'GET /admin/analytics'            => ['api/admin/analytics.php',     []],

    // Meta Webhook
    'GET /webhook/meta'               => ['api/meta_webhook.php', []],
    'POST /webhook/meta'              => ['api/meta_webhook.php', []],
];

/**
 * Match the incoming path against a route pattern.
 * Returns ['file'=>..., 'params'=>[...]] or null.
 */
function matchRoute(string $method, string $path, array $routes): ?array
{
    $key = "$method $path";

    // Exact match first
    if (isset($routes[$key])) {
        return ['file' => $routes[$key][0], 'params' => []];
    }

    // Pattern match
    foreach ($routes as $pattern => $config) {
        [$routeMethod, $routePath] = explode(' ', $pattern, 2);
        if ($routeMethod !== $method) continue;

        // Convert {id} placeholders to named capture groups
        $regex = preg_replace('/\{[^}]+\}/', '([^/]+)', $routePath);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            array_shift($matches); // remove full match
            $params = [];
            foreach ($config[1] as $i => $name) {
                $params[$name] = $matches[$i] ?? '';
            }
            return ['file' => $config[0], 'params' => $params];
        }
    }
    return null;
}

$match = matchRoute($method, $path, $routes);

if (!$match) {
    errorResponse("Route not found: $method $path", 404);
}

// Inject path params into $_GET so endpoint files can read them
foreach ($match['params'] as $k => $v) {
    $_GET[$k] = $v;
}

$file = __DIR__ . '/' . $match['file'];
if (!file_exists($file)) {
    errorResponse('Endpoint not implemented', 501);
}

require $file;
