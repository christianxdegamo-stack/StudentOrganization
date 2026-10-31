<?php
require_once __DIR__ . '/../includes/auth_check.php';

if (auth_user()) {
    redirect(home_for_role(current_role()));
}

$errors = [];
$old = ['student_id' => '', 'first_name' => '', 'last_name' => '', 'email' => '', 'course' => '', 'year_level' => ''];

if (is_post()) {
    verify_csrf();
    foreach ($old as $k => $_) {
        $old[$k] = post($k);
    }
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!preg_match('/^[A-Za-z0-9-]{3,20}$/', $old['student_id'])) $errors[] = 'Student ID must be 3-20 letters, numbers, or dashes.';
    if ($old['first_name'] === '' || mb_strlen($old['first_name']) > 50) $errors[] = 'First name is required (max 50 characters).';
    if ($old['last_name'] === ''  || mb_strlen($old['last_name']) > 50)  $errors[] = 'Last name is required (max 50 characters).';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 100) $errors[] = 'Please enter a valid email address.';
    if ($old['course'] === '' || mb_strlen($old['course']) > 50) $errors[] = 'Course/Program is required.';
    if (!ctype_digit($old['year_level']) || (int)$old['year_level'] < 1 || (int)$old['year_level'] > 5) $errors[] = 'Year level must be 1 to 5.';
    if (!is_string($password) || strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        if (q_one('SELECT 1 FROM students WHERE student_id = ?', [$old['student_id']])) $errors[] = 'That Student ID is already registered.';
        if (q_one('SELECT 1 FROM students WHERE email = ?', [$old['email']])) $errors[] = 'That email is already registered.';
    }

    if (!$errors) {
        q_exec(
            'INSERT INTO students (student_id, first_name, last_name, email, course, year_level, password_hash) VALUES (?,?,?,?,?,?,?)',
            [$old['student_id'], $old['first_name'], $old['last_name'], $old['email'], $old['course'], (int)$old['year_level'], password_hash($password, PASSWORD_DEFAULT)]
        );
        q_exec('INSERT INTO activity_logs (user_id, activity) VALUES (?, ?)', [$old['student_id'], 'Registered a new student account']);
        flash('success', 'Account created! You can now log in.');
        redirect('auth/login.php');
    }
}

$pageTitle = 'Register';
$authPage = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="card auth-card shadow-lg">
    <div class="card-body p-4">
        <div class="text-center mb-3">
            <div class="auth-logo"><i class="bi bi-diagram-3-fill"></i> OrgSync</div>
            <h5 class="mb-0">Student Registration</h5>
        </div>
        <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Student ID number</label>
                <input type="text" name="student_id" class="form-control" maxlength="20" value="<?= e($old['student_id']) ?>" required>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-6"><label class="form-label">First name</label>
                    <input type="text" name="first_name" class="form-control" maxlength="50" value="<?= e($old['first_name']) ?>" required></div>
                <div class="col-6"><label class="form-label">Last name</label>
                    <input type="text" name="last_name" class="form-control" maxlength="50" value="<?= e($old['last_name']) ?>" required></div>
            </div>
            <div class="mb-3">
                <label class="form-label">Institutional email</label>
                <input type="email" name="email" class="form-control" maxlength="100" value="<?= e($old['email']) ?>" required>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-8"><label class="form-label">Course / Program</label>
                    <input type="text" name="course" class="form-control" list="courses" maxlength="50" value="<?= e($old['course']) ?>" required>
                    <datalist id="courses"><?php foreach (DEFAULT_COURSES as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
                <div class="col-4"><label class="form-label">Year</label>
                    <select name="year_level" class="form-select" required>
                        <option value="">-</option>
                        <?php for ($i = 1; $i <= 5; $i++): ?><option value="<?= $i ?>" <?= $old['year_level'] === (string)$i ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?>
                    </select></div>
            </div>
            <div class="mb-3">
                <label class="form-label">Password <small class="text-muted">(at least 8 characters)</small></label>
                <input type="password" name="password" class="form-control" minlength="8" required>
            </div>
            <div class="mb-4">
                <label class="form-label">Confirm password</label>
                <input type="password" name="confirm_password" class="form-control" minlength="8" required>
            </div>
            <button class="btn btn-primary w-100 py-2">Create account</button>
        </form>
        <hr>
        <p class="text-center mb-0">Already registered? <a href="login.php">Log in</a></p>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
