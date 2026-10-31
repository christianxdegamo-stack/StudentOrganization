<?php
require_once __DIR__ . '/../includes/auth_check.php';

// Logging out only works from a POST form with a valid CSRF token (stops "logout by link" tricks).
if (is_post() && auth_user()) {
    verify_csrf();
    log_activity('Logged out');
    logout_now();
    session_start();
    session_regenerate_id(true);
    flash('success', 'You have been logged out.');
    redirect('auth/login.php');
}
redirect(auth_user() ? home_for_role(current_role()) : 'auth/login.php');
