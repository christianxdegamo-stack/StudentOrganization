<?php
/** Sidebar navigation - menu depends on the logged-in role. */
$sRole  = $hu['role'];
$sDir   = basename(dirname($_SERVER['SCRIPT_NAME']));
$sFile  = basename($_SERVER['SCRIPT_NAME']);

$menus = [
    'admin' => [
        ['admin/dashboard.php', 'bi-speedometer2', 'Dashboard'],
        ['admin/students.php', 'bi-people', 'Students'],
        ['admin/organizations.php', 'bi-buildings', 'Organizations'],
        ['admin/memberships.php', 'bi-person-vcard', 'Memberships'],
        ['admin/events.php', 'bi-calendar-event', 'Events'],
        ['admin/registrations.php', 'bi-journal-check', 'Event Registrations'],
        ['admin/attendance.php', 'bi-clipboard-check', 'Attendance'],
        ['admin/reports.php', 'bi-bar-chart-line', 'Reports'],
    ],
    'officer' => [
        ['officer/dashboard.php', 'bi-speedometer2', 'Dashboard'],
        ['officer/memberships.php', 'bi-person-vcard', 'Memberships'],
        ['officer/events.php', 'bi-calendar-event', 'Events'],
        ['officer/registrations.php', 'bi-journal-check', 'Registrations'],
        ['officer/attendance.php', 'bi-clipboard-check', 'Attendance'],
        ['officer/reports.php', 'bi-bar-chart-line', 'Reports'],
    ],
    'student' => [
        ['student/dashboard.php', 'bi-speedometer2', 'Dashboard'],
        ['student/organizations.php', 'bi-buildings', 'Organizations'],
        ['student/memberships.php', 'bi-person-vcard', 'My Memberships'],
        ['student/events.php', 'bi-calendar-event', 'Browse Events'],
        ['student/my_registrations.php', 'bi-journal-check', 'My Registrations'],
        ['student/attendance.php', 'bi-clipboard-check', 'My Attendance'],
    ],
];
$items = $menus[$sRole] ?? [];
$render = function (array $list) use ($sDir, $sFile) {
    foreach ($list as [$href, $icon, $label]) {
        $active = ($href === $sDir . '/' . $sFile) ? 'active' : '';
        echo '<a class="nav-link ' . $active . '" href="' . e(url($href)) . '"><i class="bi ' . $icon . '"></i> ' . e($label) . '</a>';
    }
};
?>
<aside class="sidebar no-print" id="sidebar">
    <div class="sidebar-brand"><i class="bi bi-diagram-3-fill"></i> OrgSync</div>
    <?php if ($sRole === 'officer'): ?>
        <div class="sidebar-org"><i class="bi bi-building"></i>
            <?= e(array_column(officer_orgs(), 'org_name', 'org_id')[current_org_id()] ?? '') ?></div>
    <?php endif; ?>
    <nav class="nav flex-column">
        <?php $render($items); ?>
        <?php if ($sRole === 'officer'): ?>
            <div class="sidebar-heading">Student View</div>
            <?php $render($menus['student']); ?>
        <?php endif; ?>
    </nav>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
