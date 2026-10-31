<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['student']);
$sid = auth_user()['id'];

if (is_post()) {
    verify_csrf();
    if (post('action') === 'withdraw') {
        // Students may only withdraw their OWN application, and only while it is still Pending.
        $n = q_exec("DELETE FROM memberships WHERE membership_id = ? AND student_id = ? AND status = 'Pending'", [(int)post('membership_id'), $sid]);
        if ($n) { log_activity('Withdrew a membership application'); flash('success', 'Application withdrawn.'); }
        else { flash('error', 'Only pending applications can be withdrawn.'); }
    }
    redirect_self();
}

$rows = q_all("SELECT m.*, o.org_name, o.category FROM memberships m JOIN organizations o ON o.org_id = m.org_id
    WHERE m.student_id = ? ORDER BY FIELD(m.status, 'Pending', 'Active', 'Inactive', 'Rejected'), o.org_name", [$sid]);

$pageTitle = 'My Memberships';
require __DIR__ . '/../includes/header.php';
?>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Organization</th><th>Category</th><th>Role</th><th>Status</th><th>Date</th><th class="text-end">Action</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
        <tr><td class="fw-semibold"><?= e($r['org_name']) ?></td><td><?= e($r['category']) ?></td><td><?= badge($r['member_role']) ?></td><td><?= badge($r['status']) ?></td>
            <td><?= e(fmt_date($r['date_joined'])) ?> <small class="text-muted"><?= $r['status'] === 'Pending' ? '(applied)' : ($r['status'] === 'Active' ? '(joined)' : '') ?></small></td>
            <td class="text-end"><?php if ($r['status'] === 'Pending'): ?>
                <form method="post" data-confirm="Withdraw this application?"><?= csrf_field() ?><input type="hidden" name="action" value="withdraw"><input type="hidden" name="membership_id" value="<?= (int)$r['membership_id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Withdraw</button></form><?php endif; ?></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">You have no membership applications yet. <a href="organizations.php">Browse organizations</a>.</td></tr><?php endif; ?>
    </tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
