<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['student']);
$sid = auth_user()['id'];

$rows = q_all('SELECT a.*, e.event_title, e.event_date, e.venue, o.org_name FROM event_attendance a
    JOIN events e ON e.event_id = a.event_id JOIN organizations o ON o.org_id = e.org_id
    WHERE a.student_id = ? ORDER BY a.time_in DESC', [$sid]);
$present = count(array_filter($rows, fn($r) => $r['status'] === 'Present'));
$excused = count($rows) - $present;

$pageTitle = 'My Attendance History';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3 mb-3">
    <div class="col-4"><div class="card stat-card"><div class="card-body"><div class="value"><?= count($rows) ?></div><div class="small text-muted">Total records</div></div></div></div>
    <div class="col-4"><div class="card stat-card"><div class="card-body"><div class="value"><?= $present ?></div><div class="small text-muted">Present</div></div></div></div>
    <div class="col-4"><div class="card stat-card"><div class="card-body"><div class="value"><?= $excused ?></div><div class="small text-muted">Excused</div></div></div></div>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Event</th><th>Organization</th><th>Event Date</th><th>Venue</th><th>Check-in</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
        <tr><td class="fw-semibold"><?= e($r['event_title']) ?></td><td><?= e($r['org_name']) ?></td><td><?= e(fmt_dt($r['event_date'])) ?></td>
            <td><?= e($r['venue']) ?></td><td><?= e(fmt_dt($r['time_in'])) ?></td><td><?= badge($r['status']) ?></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No attendance records yet.</td></tr><?php endif; ?>
    </tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
