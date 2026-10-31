<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['student']);
$sid = auth_user()['id'];

if (is_post()) {
    verify_csrf();
    if (post('action') === 'apply') {
        $orgId = (int)post('org_id');
        $org = q_one('SELECT org_name FROM organizations WHERE org_id = ?', [$orgId]);
        if (!$org) {
            flash('error', 'Organization not found.');
        } else {
            $m = q_one('SELECT membership_id, status FROM memberships WHERE student_id = ? AND org_id = ?', [$sid, $orgId]);
            if (!$m) {
                try {
                    q_exec("INSERT INTO memberships (student_id, org_id, date_joined, status, member_role) VALUES (?, ?, CURDATE(), 'Pending', 'Member')", [$sid, $orgId]);
                    log_activity('Applied for membership in ' . $org['org_name']);
                    flash('success', 'Application sent to ' . $org['org_name'] . '. Wait for an officer to review it.');
                } catch (PDOException $ex) {
                    flash('error', 'You already have a membership record for this organization.'); // unique key caught a double click
                }
            } elseif ($m['status'] === 'Rejected') {
                q_exec("UPDATE memberships SET status = 'Pending', date_joined = CURDATE() WHERE membership_id = ?", [$m['membership_id']]);
                log_activity('Re-applied for membership in ' . $org['org_name']);
                flash('success', 'Your application was submitted again.');
            } else {
                flash('error', 'You already have a ' . strtolower($m['status']) . ' membership with this organization.');
            }
        }
    }
    redirect_self(http_build_query(array_filter(['q' => post('q'), 'category' => post('category')])));
}

$q = get('q');
$cat = get('category');
$where = [];
$params = [$sid];
if ($q !== '') { $where[] = '(o.org_name LIKE ? OR o.description LIKE ?)'; array_push($params, "%$q%", "%$q%"); }
if (in_array($cat, ORG_CATEGORIES, true)) { $where[] = 'o.category = ?'; $params[] = $cat; }
$orgs = q_all(
    "SELECT o.*, m.status AS my_status, m.member_role AS my_role,
        (SELECT COUNT(*) FROM memberships x WHERE x.org_id = o.org_id AND x.status = 'Active') AS members,
        (SELECT COUNT(*) FROM events e WHERE e.org_id = o.org_id AND e.event_date >= NOW()) AS upcoming
     FROM organizations o LEFT JOIN memberships m ON m.org_id = o.org_id AND m.student_id = ? "
    . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY o.org_name',
    $params
);

$pageTitle = 'Organizations';
require __DIR__ . '/../includes/header.php';
?>
<form class="row g-2 mb-3" method="get">
    <div class="col-12 col-md-5"><input type="search" name="q" class="form-control" placeholder="Search organizations" value="<?= e($q) ?>"></div>
    <div class="col-6 col-md-3"><select name="category" class="form-select" data-autosubmit><option value="">All categories</option>
        <?php foreach (ORG_CATEGORIES as $c): ?><option <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-4 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-search"></i> Search</button><a href="organizations.php" class="btn btn-outline-secondary">Reset</a></div>
</form>
<div class="row g-3">
<?php foreach ($orgs as $o): ?>
    <div class="col-md-6 col-xl-4"><div class="card org-card"><div class="card-body d-flex flex-column">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <h5 class="mb-0"><?= e($o['org_name']) ?></h5><span class="badge bg-primary"><?= e($o['category']) ?></span></div>
        <p class="text-muted flex-grow-1"><?= e($o['description'] ?: 'No description.') ?></p>
        <div class="small text-muted mb-3"><i class="bi bi-people"></i> <?= (int)$o['members'] ?> members &middot; <i class="bi bi-calendar-event"></i> <?= (int)$o['upcoming'] ?> upcoming event(s)</div>
        <?php if (!$o['my_status']): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="apply"><input type="hidden" name="org_id" value="<?= (int)$o['org_id'] ?>">
                <input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="category" value="<?= e($cat) ?>">
                <button class="btn btn-primary w-100"><i class="bi bi-send"></i> Apply for membership</button></form>
        <?php elseif ($o['my_status'] === 'Rejected'): ?>
            <div class="mb-2"><?= badge('Rejected') ?> <span class="small text-muted">Your last application was not approved.</span></div>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="apply"><input type="hidden" name="org_id" value="<?= (int)$o['org_id'] ?>">
                <button class="btn btn-outline-primary w-100">Apply again</button></form>
        <?php else: ?>
            <div>Your status: <?= badge($o['my_status']) ?> <?= $o['my_status'] === 'Active' ? badge($o['my_role']) : '' ?></div>
        <?php endif; ?>
    </div></div></div>
<?php endforeach; if (!$orgs): ?><div class="col-12"><div class="alert alert-info">No organizations found.</div></div><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
