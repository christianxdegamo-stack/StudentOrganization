<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['student']);
$sid = auth_user()['id'];

if (is_post()) {
    verify_csrf();
    if (post('action') === 'cancel') {
        $ev = q_one('SELECT e.event_title, e.event_date FROM events e WHERE e.event_id = ?', [(int)post('event_id')]);
        if ($ev && strtotime($ev['event_date']) >= time()) {
            $n = q_exec("UPDATE event_registrations SET registration_status = 'Cancelled' WHERE event_id = ? AND student_id = ? AND registration_status = 'Registered'", [(int)post('event_id'), $sid]);
            if ($n) { log_activity('Cancelled registration for event: ' . $ev['event_title']); flash('success', 'Registration cancelled.'); }
        } else {
            flash('error', 'You can only cancel registrations for upcoming events.');
        }
    }
    redirect_self(http_build_query(array_filter(['show' => post('show')])));
}

$show = in_array(get('show'), ['upcoming', 'past', 'all'], true) ? get('show') : 'upcoming';
$cond = $show === 'upcoming' ? 'AND e.event_date >= NOW()' : ($show === 'past' ? 'AND e.event_date < NOW()' : '');
$rows = q_all("SELECT r.*, e.event_id, e.event_title, e.event_date, e.venue, o.org_name,
        (SELECT status FROM event_attendance a WHERE a.event_id = e.event_id AND a.student_id = r.student_id) AS attendance
    FROM event_registrations r JOIN events e ON e.event_id = r.event_id JOIN organizations o ON o.org_id = e.org_id
    WHERE r.student_id = ? $cond ORDER BY e.event_date " . ($show === 'past' ? 'DESC' : 'ASC'), [$sid]);

$pageTitle = 'My Event Registrations';
require __DIR__ . '/../includes/header.php';
?>
<ul class="nav nav-pills mb-3 gap-1">
    <?php foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'all' => 'All'] as $k => $label): ?>
        <li class="nav-item"><a class="nav-link <?= $show === $k ? 'active' : 'bg-white' ?>" href="?show=<?= $k ?>"><?= $label ?></a></li><?php endforeach; ?>
</ul>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Event</th><th>Organization</th><th>Date &amp; Time</th><th>Venue</th><th>Registration</th><th>Attendance</th><th class="text-end">Action</th></tr></thead><tbody>
    <?php foreach ($rows as $r): $upcoming = strtotime($r['event_date']) >= time(); ?>
        <tr><td class="fw-semibold"><?= e($r['event_title']) ?></td><td><?= e($r['org_name']) ?></td><td><?= e(fmt_dt($r['event_date'])) ?></td><td><?= e($r['venue']) ?></td>
            <td><?= badge($r['registration_status']) ?></td><td><?= $r['attendance'] ? badge($r['attendance']) : '<span class="text-muted">-</span>' ?></td>
            <td class="text-end"><?php if ($upcoming && $r['registration_status'] === 'Registered'): ?>
                <form method="post" data-confirm="Cancel your registration?"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="event_id" value="<?= (int)$r['event_id'] ?>"><input type="hidden" name="show" value="<?= e($show) ?>">
                    <button class="btn btn-sm btn-outline-danger">Cancel</button></form><?php endif; ?></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No registrations to show. <a href="events.php">Browse events</a>.</td></tr><?php endif; ?>
    </tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
