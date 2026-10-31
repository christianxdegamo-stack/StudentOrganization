<?php
require_once __DIR__ . '/includes/auth_check.php';
// Send visitors to their dashboard, or to the login page.
redirect(auth_user() ? home_for_role(current_role()) : 'auth/login.php');
