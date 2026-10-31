<?php
/** Student management (Super Admin only): add, view, edit, delete, search, filter. */

// ---------- Handle form submissions ----------
if (is_post()) {
    verify_csrf();
    $action = post('action');

    if ($action === 'delete') {
        $id = post('student_id');
        $row = q_one('SELECT first_name, last_name FROM students WHERE student_id = ?', [$id]);
        if ($row) {
            q_exec('DELETE FROM students WHERE student_id = ?', [$id]);
            log_activity("Deleted student $id ({$row['first_name']} {$row['last_name']})");
            flash('success', 'Student deleted.');
        } else {
            flash('error', 'Student not found.');
        }
        redirect_self();
    }

    if ($action === 'add' || $action === 'edit') {
        $d = [
            'student_id' => post('student_id'), 'first_name' => post('first_name'), 'last_name' => post('last_name'),
            'email' => post('email'), 'course' => post('course'), 'year_level' => post('year_level'),
        ];
        $password = $_POST['password'] ?? '';
        $errors = [];
        if (!preg_match('/^[A-Za-z0-9-]{3,20}$/', $d['student_id'])) $errors[] = 'Student ID must be 3-20 letters, numbers, or dashes.';
        if ($d['first_name'] === '' || mb_strlen($d['first_name']) > 50) $errors[] = 'First name is required (max 50 characters).';
        if ($d['last_name'] === '' || mb_strlen($d['last_name']) > 50) $errors[] = 'Last name is required (max 50 characters).';
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($d['email']) > 100) $errors[] = 'Enter a valid email address.';
        if ($d['course'] === '' || mb_strlen($d['course']) > 50) $errors[] = 'Course is required (max 50 characters).';
        if (!ctype_digit($d['year_level']) || (int)$d['year_level'] < 1 || (int)$d['year_level'] > 5) $errors[] = 'Year level must be 1 to 5.';
        if (!is_string($password)) $password = '';
        if ($action === 'add' && strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($action === 'edit' && $password !== '' && strlen($password) < 8) $errors[] = 'New password must be at least 8 characters.';

        if (!$errors) {
            $dupEmail = q_one('SELECT student_id FROM students WHERE email = ? AND student_id <> ?', [$d['email'], $d['student_id']]);
            if ($dupEmail) $errors[] = 'That email is already used by another student.';
            $exists = q_one('SELECT 1 FROM students WHERE student_id = ?', [$d['student_id']]);
            if ($action === 'add' && $exists) $errors[] = 'That Student ID already exists.';
            if ($action === 'edit' && !$exists) $errors[] = 'Student not found.';
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
        } elseif ($action === 'add') {
            q_exec('INSERT INTO students (student_id, first_name, last_name, email, course, year_level, password_hash) VALUES (?,?,?,?,?,?,?)',
                [$d['student_id'], $d['first_name'], $d['last_name'], $d['email'], $d['course'], (int)$d['year_level'], password_hash($password, PASSWORD_DEFAULT)]);
            log_activity("Added student {$d['student_id']}");
            flash('success', 'Student added.');
        } else {
            q_exec('UPDATE students SET first_name=?, last_name=?, email=?, course=?, year_level=? WHERE student_id=?',
                [$d['first_name'], $d['last_name'], $d['email'], $d['course'], (int)$d['year_level'], $d['student_id']]);
            if ($password !== '') {
                q_exec('UPDATE students SET password_hash=? WHERE student_id=?', [password_hash($password, PASSWORD_DEFAULT), $d['student_id']]);
            }
            log_activity("Updated student {$d['student_id']}");
            flash('success', 'Student updated.');
        }
        redirect_self();
    }
}

// ---------- Search & filters ----------
$q = get('q');
$course = get('course');
$year = get('year');
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR CONCAT(s.first_name, CHAR(32), s.last_name) LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($course !== '') { $where[] = 's.course = ?'; $params[] = $course; }
if ($year !== '' && ctype_digit($year)) { $where[] = 's.year_level = ?'; $params[] = (int)$year; }

$students = q_all(
    'SELECT s.*, (SELECT COUNT(*) FROM memberships m WHERE m.student_id = s.student_id AND m.status = \'Active\') AS org_count
     FROM students s ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY s.last_name, s.first_name',
    $params
);
$courses = array_column(q_all('SELECT DISTINCT course FROM students ORDER BY course'), 'course');

$pageTitle = 'Student Management';
require __DIR__ . '/../header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <form class="row g-2 flex-grow-1" method="get">
        <div class="col-12 col-md-4"><input type="search" name="q" class="form-control" placeholder="Search ID, name, or email" value="<?= e($q) ?>"></div>
        <div class="col-6 col-md-3"><select name="course" class="form-select" data-autosubmit>
            <option value="">All courses</option>
            <?php foreach ($courses as $c): ?><option <?= $course === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-6 col-md-2"><select name="year" class="form-select" data-autosubmit>
            <option value="">All years</option>
            <?php for ($i = 1; $i <= 5; $i++): ?><option value="<?= $i ?>" <?= $year === (string)$i ? 'selected' : '' ?>>Year <?= $i ?></option><?php endfor; ?>
        </select></div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
            <a href="students.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal" data-action="add" data-title="Add Student">
        <i class="bi bi-plus-lg"></i> Add Student</button>
</div>

<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Student ID</th><th>Name</th><th>Email</th><th>Course</th><th>Year</th><th>Orgs</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($students as $s): ?>
        <tr>
            <td><?= e($s['student_id']) ?></td>
            <td><?= e($s['last_name'] . ', ' . $s['first_name']) ?></td>
            <td><?= e($s['email']) ?></td>
            <td><?= e($s['course']) ?></td>
            <td><?= (int)$s['year_level'] ?></td>
            <td><?= (int)$s['org_count'] ?></td>
            <td class="text-end text-nowrap">
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#studentModal" data-action="edit" data-title="Edit Student"
                    data-fill="<?= e(json_encode(['student_id' => $s['student_id'], 'first_name' => $s['first_name'], 'last_name' => $s['last_name'],
                        'email' => $s['email'], 'course' => $s['course'], 'year_level' => $s['year_level']])) ?>"><i class="bi bi-pencil"></i> Edit</button>
                <form method="post" class="d-inline" data-confirm="Delete this student? Their memberships, registrations and attendance records will also be deleted.">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="student_id" value="<?= e($s['student_id']) ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete</button></form>
            </td>
        </tr>
    <?php endforeach; if (!$students): ?><tr><td colspan="7" class="text-center text-muted py-4">No students found.</td></tr><?php endif; ?>
    </tbody></table></div>
    <div class="card-footer bg-white text-muted small"><?= count($students) ?> student(s)</div>
</div>

<div class="modal fade" id="studentModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Student</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <div class="mb-3"><label class="form-label">Student ID</label>
            <input type="text" name="student_id" class="form-control" maxlength="20" required data-lock-on-edit></div>
        <div class="row g-3 mb-3">
            <div class="col-6"><label class="form-label">First name</label><input type="text" name="first_name" class="form-control" maxlength="50" required></div>
            <div class="col-6"><label class="form-label">Last name</label><input type="text" name="last_name" class="form-control" maxlength="50" required></div>
        </div>
        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" maxlength="100" required></div>
        <div class="row g-3 mb-3">
            <div class="col-8"><label class="form-label">Course</label>
                <input type="text" name="course" class="form-control" list="courseList" maxlength="50" required>
                <datalist id="courseList"><?php foreach (array_unique(array_merge(DEFAULT_COURSES, $courses)) as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
            <div class="col-4"><label class="form-label">Year level</label>
                <select name="year_level" class="form-select" required><?php for ($i = 1; $i <= 5; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></div>
        </div>
        <div class="mb-0"><label class="form-label">Password <small class="text-muted">(min 8 characters; leave blank when editing to keep the current one)</small></label>
            <input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password" data-required-on-add required></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
</form></div></div>
<?php require __DIR__ . '/../footer.php'; ?>
