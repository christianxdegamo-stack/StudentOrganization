<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['student']);

$sid = auth_user()['id'];
$stu = q_one('SELECT * FROM students WHERE student_id = ?', [$sid]);
$stats = [
    ['My Organizations', q_val("SELECT COUNT(*) FROM memberships WHERE student_id = ? AND status = 'Active'", [$sid]), 'bi-buildings', 'memberships.php'],
    ['Pending Applications', q_val("SELECT COUNT(*) FROM memberships WHERE student_id = ? AND status = 'Pending'", [$sid]), 'bi-hourglass-split', 'memberships.php'],
    ['Upcoming Registrations', q_val("SELECT COUNT(*) FROM event_registrations r JOIN events e ON e.event_id = r.event_id WHERE r.student_id = ? AND r.registration_status = 'Registered' AND e.event_date >= NOW()", [$sid]), 'bi-journal-check', 'my_registrations.php'],
    ['Events Attended', q_val('SELECT COUNT(*) FROM event_attendance WHERE student_id = ?', [$sid]), 'bi-clipboard-check', 'attendance.php'],
];
$myEvents = q_all("SELECT e.event_id, e.event_title, e.event_date, e.venue, o.org_name
    FROM event_registrations r JOIN events e ON e.event_id = r.event_id JOIN organizations o ON o.org_id = e.org_id
    WHERE r.student_id = ? AND r.registration_status = 'Registered' AND e.event_date >= NOW() ORDER BY e.event_date LIMIT 5", [$sid]);
$myOrgs = q_all("SELECT o.org_name, o.category, m.member_role, m.date_joined FROM memberships m JOIN organizations o ON o.org_id = m.org_id
    WHERE m.student_id = ? AND m.status = 'Active' ORDER BY o.org_name", [$sid]);
$suggested = q_all("SELECT e.event_id, e.event_title, e.event_date, o.org_name FROM events e JOIN organizations o ON o.org_id = e.org_id
    WHERE e.event_date >= NOW() AND e.event_id NOT IN (SELECT event_id FROM event_registrations WHERE student_id = ? AND registration_status = 'Registered')
    ORDER BY e.event_date LIMIT 4", [$sid]);

$pageTitle = 'My Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="card mb-4"><div class="card-body">
    <h5 class="mb-1">Welcome, <?= e($stu['first_name']) ?>!</h5>
    <div class="text-muted"><?= e($stu['student_id']) ?> &middot; <?= e($stu['course']) ?>, Year <?= (int)$stu['year_level'] ?></div>
</div></div>
<div class="row g-3 mb-4">
    <?php foreach ($stats as [$label, $value, $icon, $link]): ?>
        <div class="col-6 col-lg-3"><a href="<?= e($link) ?>" class="text-decoration-none text-reset">
            <div class="card stat-card h-100"><div class="card-body">
                <div class="icon mb-2"><i class="bi <?= $icon ?>"></i></div>
                <div class="value"><?= (int)$value ?></div><div class="text-muted small"><?= e($label) ?></div>
            </div></div></a></div>
    <?php endforeach; ?>
</div>
<div class="row g-3">
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">My Upcoming Events</div>
        <ul class="list-group list-group-flush">
        <?php foreach ($myEvents as $ev): ?>
            <li class="list-group-item"><strong><?= e($ev['event_title']) ?></strong>
                <div class="small text-muted"><?= e($ev['org_name']) ?> &middot; <?= e(fmt_dt($ev['event_date'])) ?> &middot; <?= e($ev['venue']) ?></div></li>
        <?php endforeach; if (!$myEvents): ?><li class="list-group-item text-muted">You have not registered for any upcoming event. <a href="events.php">Browse events</a>.</li><?php endif; ?>
        </ul></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">My Organizations</div>
        <ul class="list-group list-group-flush">
        <?php foreach ($myOrgs as $o): ?>
            <li class="list-group-item d-flex justify-content-between"><div><strong><?= e($o['org_name']) ?></strong>
                <div class="small text-muted"><?= e($o['category']) ?> &middot; joined <?= e(fmt_date($o['date_joined'])) ?></div></div><?= badge($o['member_role']) ?></li>
        <?php endforeach; if (!$myOrgs): ?><li class="list-group-item text-muted">You are not a member of any organization yet. <a href="organizations.php">Find one</a>.</li><?php endif; ?>
        </ul></div></div>
    <?php if ($suggested): ?>
    <div class="col-12"><div class="card"><div class="card-header">Events You Might Like</div>
        <div class="list-group list-group-flush">
        <?php foreach ($suggested as $ev): ?>
            <a class="list-group-item list-group-item-action d-flex justify-content-between" href="events.php">
                <span><?= e($ev['event_title']) ?> <small class="text-muted">by <?= e($ev['org_name']) ?></small></span>
                <small><?= e(fmt_dt($ev['event_date'])) ?></small></a>
        <?php endforeach; ?>
        </div></div></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
