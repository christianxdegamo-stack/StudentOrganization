<?php
/**
 * Page header. Set $pageTitle before including.
 * Set $authPage = true for login/register pages (no sidebar).
 */
$pageTitle = $pageTitle ?? 'OrgSync';
$authPage  = $authPage ?? false;
$hu        = auth_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | OrgSync</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<?php if ($authPage || !$hu): ?>
<body class="auth-body">
<main class="auth-wrap">
    <?php render_flash(); ?>
<?php else: ?>
<body>
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="main-content">
    <nav class="topbar no-print">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-light d-lg-none" id="sidebarToggle" type="button" aria-label="Menu"><i class="bi bi-list"></i></button>
            <h1 class="page-title mb-0"><?= e($pageTitle) ?></h1>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php if ($hu['role'] === 'officer' && count(officer_orgs()) > 1): ?>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-building"></i> Switch organization
                    </button>
                    <ul class="dropdown-menu">
                        <?php foreach (officer_orgs() as $o): ?>
                            <li><a class="dropdown-item <?= (int)$o['org_id'] === current_org_id() ? 'active' : '' ?>"
                                   href="?switch_org=<?= (int)$o['org_id'] ?>"><?= e($o['org_name']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                    <span class="avatar"><?= e(strtoupper(substr($hu['name'], 0, 1))) ?></span>
                    <span class="d-none d-sm-inline"><?= e($hu['name']) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text small text-muted">
                        <?= e(['admin' => 'Super Admin', 'officer' => 'Club Officer', 'student' => 'Student'][$hu['role']] ?? '') ?>
                    </span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="post" action="<?= e(url('auth/logout.php')) ?>" class="px-2">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-danger w-100"><i class="bi bi-box-arrow-right"></i> Log out</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="content-area">
        <?php render_flash(); ?>
<?php endif; ?>
