<?php
require_once __DIR__ . '/../includes/auth_check.php';

if (auth_user()) {
    redirect(home_for_role(current_role()));
}

$error = '';
$identifier = '';

if (is_post()) {
    verify_csrf();
    $identifier = post('identifier');
    $password   = $_POST['password'] ?? '';

    // Simple brute-force protection: 5 failed tries = 60 second lock
    $lockedUntil = $_SESSION['login_locked_until'] ?? 0;
    if ($lockedUntil > time()) {
        $error = 'Too many failed attempts. Please wait ' . ($lockedUntil - time()) . ' seconds.';
    } elseif ($identifier === '' || !is_string($password) || $password === '') {
        $error = 'Please enter your ID/username and password.';
    } else {
        $user = null;

        // 1) Super Admin accounts (users table): username or email
        $admin = q_one('SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1', [$identifier, $identifier]);
        if ($admin && password_verify($password, $admin['password_hash'])) {
            $user = ['type' => 'admin', 'id' => (int)$admin['user_id'], 'name' => $admin['full_name'], 'role' => 'admin'];
        }

        // 2) Students / officers (students table): student ID or email
        if (!$user) {
            $stu = q_one('SELECT * FROM students WHERE student_id = ? OR email = ?', [$identifier, $identifier]);
            if ($stu && password_verify($password, $stu['password_hash'])) {
                $user = ['type' => 'student', 'id' => $stu['student_id'], 'name' => $stu['first_name'] . ' ' . $stu['last_name'], 'role' => 'student'];
            }
        }

        if ($user) {
            session_regenerate_id(true);           // prevents session fixation
            unset($_SESSION['login_fails'], $_SESSION['login_locked_until']);
            $_SESSION['user'] = $user;
            $_SESSION['last_activity'] = time();
            if ($user['type'] === 'student') {
                $user['role'] = officer_orgs() ? 'officer' : 'student';
                $_SESSION['user']['role'] = $user['role'];
            }
            log_activity('Logged in');
            redirect(home_for_role($user['role']));
        }

        $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
        if ($_SESSION['login_fails'] >= 5) {
            $_SESSION['login_locked_until'] = time() + 60;
            $_SESSION['login_fails'] = 0;
        }
        $error = 'Incorrect ID/username or password.';
    }
}

$pageTitle = 'Login';
$authPage = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="card auth-card shadow-lg">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="auth-logo"><i class="bi bi-diagram-3-fill"></i> OrgSync</div>
            <p class="text-muted mb-0">Student Organization Event &amp; Membership Management</p>
        </div>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="identifier">Student ID, email, or admin username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="identifier" name="identifier" value="<?= e($identifier) ?>" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
            </div>
            <button class="btn btn-primary w-100 py-2" type="submit">Log in</button>
        </form>
        <hr>
        <p class="text-center mb-0">New student? <a href="register.php">Create an account</a></p>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
