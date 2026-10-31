<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['student']);
$sid = auth_user()['id'];

if (is_post()) {
    verify_csrf();
    $action = post('action');
    $eventId = (int)post('event_id');

    if ($action === 'register') {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            // Lock the event row so two students cannot take the last seat at the same time.
            $st = $pdo->prepare('SELECT * FROM events WHERE event_id = ? FOR UPDATE');
            $st->execute([$eventId]);
            $ev = $st->fetch();
            if (!$ev) {
                flash('error', 'Event not found.');
            } elseif (strtotime($ev['event_date']) < time()) {
                flash('error', 'This event has already started or finished.');
            } else {
                $cnt = (int)q_val("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND registration_status = 'Registered'", [$eventId]);
                $mine = q_one('SELECT registration_id, registration_status FROM event_registrations WHERE event_id = ? AND student_id = ?', [$eventId, $sid]);
                if ($mine && $mine['registration_status'] === 'Registered') {
                    flash('error', 'You are already registered for this event.');
                } elseif ($cnt >= (int)$ev['capacity']) {
                    flash('error', 'Sorry, this event is full.');
                } else {
                    if ($mine) {
                        q_exec("UPDATE event_registrations SET registration_status = 'Registered', registration_date = NOW() WHERE registration_id = ?", [$mine['registration_id']]);
                    } else {
                        q_exec("INSERT INTO event_registrations (event_id, student_id, registration_status) VALUES (?, ?, 'Registered')", [$eventId, $sid]);
                    }
                    log_activity('Registered for event: ' . $ev['event_title']);
                    flash('success', 'You are registered for ' . $ev['event_title'] . '!');
                }
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', 'Could not complete your registration. Please try again.');
        }
    } elseif ($action === 'cancel') {
        $ev = q_one('SELECT event_title, event_date FROM events WHERE event_id = ?', [$eventId]);
        if ($ev && strtotime($ev['event_date']) >= time()) {
            $n = q_exec("UPDATE event_registrations SET registration_status = 'Cancelled' WHERE event_id = ? AND student_id = ? AND registration_status = 'Registered'", [$eventId, $sid]);
            if ($n) { log_activity('Cancelled registration for event: ' . $ev['event_title']); flash('success', 'Your registration was cancelled.'); }
        } else {
            flash('error', 'You can only cancel registrations for upcoming events.');
        }
    }
    redirect_self(http_build_query(array_filter(['q' => post('q'), 'scope' => post('scope')])));
}

$q = get('q');
$scopeSel = get('scope') === 'mine' ? 'mine' : 'all';
$where = ['e.event_date >= NOW()'];
$params = [$sid];
if ($q !== '') { $where[] = '(e.event_title LIKE ? OR e.venue LIKE ? OR o.org_name LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%"); }
if ($scopeSel === 'mine') { $where[] = "e.org_id IN (SELECT org_id FROM memberships WHERE student_id = ? AND status = 'Active')"; $params[] = $sid; }
$events = q_all(
    "SELECT e.*, o.org_name, r.registration_status AS my_status,
        (SELECT COUNT(*) FROM event_registrations x WHERE x.event_id = e.event_id AND x.registration_status = 'Registered') AS registered
     FROM events e JOIN organizations o ON o.org_id = e.org_id
     LEFT JOIN event_registrations r ON r.event_id = e.event_id AND r.student_id = ?
     WHERE " . implode(' AND ', $where) . ' ORDER BY e.event_date',
    $params
);

$pageTitle = 'Browse Upcoming Events';
require __DIR__ . '/../includes/header.php';
?>
<form class="row g-2 mb-3" method="get">
    <div class="col-12 col-md-5"><input type="search" name="q" class="form-control" placeholder="Search events, venues, organizations" value="<?= e($q) ?>"></div>
    <div class="col-6 col-md-3"><select name="scope" class="form-select" data-autosubmit>
        <option value="all" <?= $scopeSel === 'all' ? 'selected' : '' ?>>All organizations</option><option value="mine" <?= $scopeSel === 'mine' ? 'selected' : '' ?>>Only my organizations</option></select></div>
    <div class="col-6 col-md-4 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-search"></i> Search</button><a href="events.php" class="btn btn-outline-secondary">Reset</a></div>
</form>
<div class="row g-3">
<?php foreach ($events as $ev):
    $left = max(0, (int)$ev['capacity'] - (int)$ev['registered']);
    $isReg = $ev['my_status'] === 'Registered'; ?>
    <div class="col-md-6 col-xl-4"><div class="card org-card"><div class="card-body d-flex flex-column">
        <div class="small text-muted mb-1"><?= e($ev['org_name']) ?></div>
        <h5><?= e($ev['event_title']) ?></h5>
        <p class="text-muted small flex-grow-1"><?= e($ev['event_description'] ?: '') ?></p>
        <div class="small mb-1"><i class="bi bi-clock"></i> <?= e(fmt_dt($ev['event_date'])) ?></div>
        <div class="small mb-1"><i class="bi bi-geo-alt"></i> <?= e($ev['venue']) ?></div>
        <div class="small mb-3"><i class="bi bi-people"></i> <?= (int)$ev['registered'] ?> / <?= (int)$ev['capacity'] ?> registered
            <?= $left === 0 ? badge('Full') : '<span class="text-muted">(' . $left . ' seats left)</span>' ?></div>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int)$ev['event_id'] ?>">
            <input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="scope" value="<?= e($scopeSel) ?>">
            <?php if ($isReg): ?>
                <div class="mb-2"><?= badge('Registered') ?> <span class="small text-muted">You are going.</span></div>
                <input type="hidden" name="action" value="cancel"><button class="btn btn-outline-danger w-100">Cancel registration</button>
            <?php elseif ($left === 0): ?>
                <button class="btn btn-secondary w-100" type="button" disabled>Event is full</button>
            <?php else: ?>
                <input type="hidden" name="action" value="register"><button class="btn btn-primary w-100"><i class="bi bi-check2-circle"></i> Register</button>
            <?php endif; ?>
        </form>
    </div></div></div>
<?php endforeach; if (!$events): ?><div class="col-12"><div class="alert alert-info">No upcoming events found.</div></div><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
