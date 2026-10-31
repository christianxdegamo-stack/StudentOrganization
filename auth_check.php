<?php
/**
 * OrgSync - access control. Include this at the top of every protected page,
 * then call require_role([...]) with the roles allowed to see the page.
 * Roles: 'admin' (Super Admin), 'officer' (Club Officer), 'student'.
 */
require_once __DIR__ . '/functions.php';

function auth_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function current_role(): ?string
{
    return $_SESSION['user']['role'] ?? null;
}

function home_for_role(?string $role): string
{
    return match ($role) {
        'admin'   => 'admin/dashboard.php',
        'officer' => 'officer/dashboard.php',
        'student' => 'student/dashboard.php',
        default   => 'auth/login.php',
    };
}

/** Organizations where the logged-in student is an active Officer. */
function officer_orgs(): array
{
    static $cache = null;
    $u = auth_user();
    if (!$u || $u['type'] !== 'student') {
        return [];
    }
    if ($cache === null) {
        $cache = q_all(
            "SELECT o.org_id, o.org_name FROM memberships m
             JOIN organizations o ON o.org_id = m.org_id
             WHERE m.student_id = ? AND m.status = 'Active' AND m.member_role = 'Officer'
             ORDER BY o.org_name",
            [$u['id']]
        );
    }
    return $cache;
}

/** The organization an officer is currently managing (they may officer several). */
function current_org_id(): ?int
{
    $orgs = officer_orgs();
    if (!$orgs) {
        return null;
    }
    $ids = array_map('intval', array_column($orgs, 'org_id'));
    $sel = (int)($_SESSION['org_id'] ?? 0);
    if (!in_array($sel, $ids, true)) {
        $sel = $ids[0];
        $_SESSION['org_id'] = $sel;
    }
    return $sel;
}

/** null for Super Admin (sees everything); org_id for Club Officer (sees only their org). */
function scope_org_id(): ?int
{
    return current_role() === 'officer' ? current_org_id() : null;
}

function logout_now(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Stops the page unless the visitor is logged in with an allowed role.
 * Officers are also students, so pages allowing 'student' accept 'officer' too.
 */
function require_role(array $allowed): void
{
    $u = auth_user();
    if (!$u) {
        flash('warning', 'Please log in to continue.');
        redirect('auth/login.php');
    }
    // Session timeout
    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        logout_now();
        session_start();
        flash('warning', 'Your session expired. Please log in again.');
        redirect('auth/login.php');
    }
    $_SESSION['last_activity'] = time();

    // Re-check the account still exists and refresh the role (an admin may have changed it)
    if ($u['type'] === 'admin') {
        $ok = q_one('SELECT user_id FROM users WHERE user_id = ? AND is_active = 1', [$u['id']]);
        $newRole = 'admin';
    } else {
        $ok = q_one('SELECT student_id FROM students WHERE student_id = ?', [$u['id']]);
        $newRole = ($ok && officer_orgs()) ? 'officer' : 'student';
    }
    if (!$ok) {
        logout_now();
        session_start();
        flash('error', 'Your account is no longer available.');
        redirect('auth/login.php');
    }
    $_SESSION['user']['role'] = $newRole;
    $role = $newRole;

    // Officer: switch organization (only to one they officer)
    if ($role === 'officer' && isset($_GET['switch_org'])) {
        foreach (officer_orgs() as $o) {
            if ((int)$o['org_id'] === (int)$_GET['switch_org']) {
                $_SESSION['org_id'] = (int)$o['org_id'];
            }
        }
        redirect_self();
    }

    $allowedNow = $allowed;
    if (in_array('student', $allowed, true)) {
        $allowedNow[] = 'officer';   // officers are students too
    }
    if (!in_array($role, $allowedNow, true)) {
        flash('error', 'You do not have permission to open that page.');
        redirect(home_for_role($role));
    }
}
