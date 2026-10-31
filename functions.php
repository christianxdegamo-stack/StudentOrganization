<?php
/**
 * OrgSync - shared helper functions (security, sessions, database shortcuts).
 */
require_once __DIR__ . '/../config/database.php';

// ---------- Secure session ----------
if (session_status() === PHP_SESSION_NONE) {
    session_name('ORGSYNCSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

const SESSION_TIMEOUT = 1800; // 30 minutes of inactivity

// ---------- Output / URLs ----------
/** Escape text for safe display in HTML (prevents XSS). */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return APP_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

/** Redirect back to the current page (clears the POST). */
function redirect_self(string $query = ''): void
{
    header('Location: ' . basename($_SERVER['SCRIPT_NAME']) . ($query !== '' ? '?' . ltrim($query, '?') : ''));
    exit;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

// ---------- Flash messages ----------
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function render_flash(): void
{
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $cls = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'][$f['type']] ?? 'info';
        echo '<div class="alert alert-' . $cls . ' alert-dismissible fade show" role="alert">'
            . e($f['msg']) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    unset($_SESSION['flash']);
}

// ---------- CSRF protection ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Call at the top of every POST handler. Stops the request if the token is wrong. */
function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Security check failed (invalid or expired form). Please go back, refresh the page and try again.');
    }
}

// ---------- Database shortcuts (all use prepared statements) ----------
function q_all(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function q_one(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function q_val(string $sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return $v === false ? 0 : $v;
}

function q_exec(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

// ---------- Input helpers ----------
function post(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

function get(string $key): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

/** Parse "2025-03-01T14:30" or "2025-03-01 14:30:00" into "Y-m-d H:i:s" (or null). */
function parse_datetime(string $v): ?string
{
    foreach (['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $fmt) {
        $d = DateTime::createFromFormat($fmt, $v);
        if ($d && $d->format($fmt) === $v) {
            return $d->format('Y-m-d H:i:s');
        }
    }
    return null;
}

function valid_date(string $v): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v;
}

function fmt_dt(?string $v): string
{
    return $v ? date('M j, Y g:i A', strtotime($v)) : '-';
}

function fmt_date(?string $v): string
{
    return $v ? date('M j, Y', strtotime($v)) : '-';
}

// ---------- Constants used by forms ----------
const ORG_CATEGORIES = ['Academic', 'Non-Academic', 'Cultural', 'Sports'];
const DEFAULT_COURSES = ['BSIT', 'BSCS', 'BSBA', 'BSED', 'BSN', 'BSCE', 'BSA', 'BSHM'];

/** Colored badge for a status word. */
function badge(string $status): string
{
    $map = [
        'Active' => 'success', 'Present' => 'success', 'Registered' => 'primary', 'Officer' => 'primary',
        'Pending' => 'warning text-dark', 'Excused' => 'info text-dark', 'Inactive' => 'secondary',
        'Rejected' => 'danger', 'Cancelled' => 'danger', 'Member' => 'light text-dark border',
        'Upcoming' => 'primary', 'Past' => 'secondary', 'Full' => 'danger',
    ];
    return '<span class="badge bg-' . ($map[$status] ?? 'secondary') . '">' . e($status) . '</span>';
}

// ---------- Activity log ----------
function log_activity(string $activity): void
{
    $u = $_SESSION['user'] ?? null;
    $id = $u ? ($u['type'] === 'admin' ? 'ADMIN-' . $u['id'] : $u['id']) : 'GUEST';
    try {
        q_exec('INSERT INTO activity_logs (user_id, activity) VALUES (?, ?)', [$id, mb_substr($activity, 0, 255)]);
    } catch (Throwable $e) {
        // logging must never break the page
    }
}

// ---------- CSV export ----------
function export_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename) . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows characters correctly
    fputcsv($out, $headers, ',', '"', '\\');
    foreach ($rows as $r) {
        // Prevent spreadsheet formula injection
        $r = array_map(fn($c) => (is_string($c) && $c !== '' && strpos('=+-@', $c[0]) !== false) ? "'" . $c : $c, $r);
        fputcsv($out, $r, ',', '"', '\\');
    }
    fclose($out);
    exit;
}
