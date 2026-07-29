<?php
/**
 * includes/header.php — Shared HTML header with role-aware navigation.
 */
if (!isset($pageTitle)) $pageTitle = 'Smart Fleet';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — Smart Fleet</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f0f2f5;
            color: #1a1a1a;
            min-height: 100vh;
        }
        /* ── Navbar ── */
        .navbar {
            background: linear-gradient(135deg, #1a237e 0%, #0d47a1 100%);
            color: #fff;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            position: relative;
            z-index: 1000;
        }
        .navbar-brand {
            font-size: 1.3rem;
            font-weight: 700;
            text-decoration: none;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .navbar-nav {
            display: flex;
            gap: 4px;
            align-items: center;
        }
        .navbar-nav > div {
            position: relative;
        }
        .navbar-nav a, .navbar-nav .dropdown-toggle {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: background 0.2s, color 0.2s;
            cursor: pointer;
            display: block;
        }
        .navbar-nav a:hover, .navbar-nav .dropdown-toggle:hover {
            background: rgba(255,255,255,0.15);
            color: #fff;
        }
        .navbar-nav a.active {
            background: rgba(255,255,255,0.2);
            color: #fff;
        }
        /* Dropdown */
        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: #fff;
            min-width: 220px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-radius: 8px;
            overflow: hidden;
            margin-top: 0; /* Changed this */
            padding-top: 4px; /* Added this to bridge the gap */
        }
        .navbar-nav > div:hover .dropdown-menu {
            display: block;
        }
        .dropdown-menu a {
            color: #424242;
            padding: 10px 16px;
            font-size: 0.88rem;
            border-bottom: 1px solid #f0f0f0;
        }
        .dropdown-menu a:last-child { border-bottom: none; }
        .dropdown-menu a:hover {
            background: #f5f7fa;
            color: #1a73e8;
        }
        
        .navbar-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .navbar-user .user-info {
            text-align: right;
            font-size: 0.85rem;
            line-height: 1.3;
        }
        .navbar-user .user-info .name { font-weight: 600; }
        .navbar-user .role-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .navbar-user .btn-logout {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 6px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: background 0.2s;
        }
        .navbar-user .btn-logout:hover {
            background: rgba(255,255,255,0.25);
        }
        /* ── Container ── */
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 30px 24px;
        }
        /* ── Cards ── */
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 28px;
            margin-bottom: 24px;
        }
        .card h1 { color: #1a237e; font-size: 1.5rem; margin-bottom: 6px; }
        .card h2 { color: #1a237e; font-size: 1.2rem; margin-bottom: 16px; }
        .card .subtitle { color: #757575; font-size: 0.95rem; margin-bottom: 20px; }
        /* ── Table ── */
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .data-table th {
            background: #f5f7fa; color: #37474f; text-align: left; padding: 12px 14px;
            font-weight: 600; border-bottom: 2px solid #e0e0e0; font-size: 0.85rem;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .data-table td { padding: 12px 14px; border-bottom: 1px solid #eee; }
        .data-table tr:hover { background: #f9fafe; }
        /* ── Badges ── */
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
        .badge-active    { background: #c8e6c9; color: #2e7d32; }
        .badge-available { background: #bbdefb; color: #1565c0; }
        .badge-maintenance{ background: #fff9c4; color: #f57f17; }
        .badge-inspection { background: #e1bee7; color: #6a1b9a; }
        .badge-outservice { background: #ffcdd2; color: #c62828; }
        .badge-retired    { background: #e0e0e0; color: #616161; }
        .badge-inactive   { background: #ffcdd2; color: #c62828; }
        .badge-onleave    { background: #fff9c4; color: #f57f17; }
        /* ── Forms ── */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 6px; color: #424242; font-weight: 600; font-size: 0.9rem; }
        .form-group select, .form-group input {
            width: 100%; padding: 11px 14px; border: 2px solid #e0e0e0;
            border-radius: 8px; font-size: 1rem; transition: border-color 0.2s; background: #fff;
        }
        .form-group select:focus, .form-group input:focus { outline: none; border-color: #1a73e8; }
        .form-group .hint { font-size: 0.8rem; color: #9e9e9e; margin-top: 4px; }
        /* ── Buttons ── */
        .btn { display: inline-block; padding: 10px 24px; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; text-decoration: none; cursor: pointer; transition: background 0.2s, transform 0.1s; }
        .btn:active { transform: translateY(1px); }
        .btn-primary  { background: #1a73e8; color: #fff; }
        .btn-primary:hover  { background: #1557b0; }
        .btn-success  { background: #2e7d32; color: #fff; }
        .btn-success:hover  { background: #1b5e20; }
        .btn-danger   { background: #d32f2f; color: #fff; }
        .btn-danger:hover   { background: #b71c1c; }
        .btn-secondary { background: #e0e0e0; color: #424242; }
        .btn-secondary:hover { background: #bdbdbd; }
        .btn-sm { padding: 6px 14px; font-size: 0.85rem; }
        /* ── Alerts ── */
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; border-left: 4px solid; }
        .alert-success { background: #e8f5e9; border-color: #4caf50; color: #2e7d32; }
        .alert-error   { background: #ffebee; border-color: #f44336; color: #c62828; }
        .alert-warning { background: #fff8e1; border-color: #ff9800; color: #e65100; }
        .alert-info    { background: #e3f2fd; border-color: #2196f3; color: #1565c0; }
        /* ── Dashboard grid ── */
        .nav-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .nav-card {
            background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 24px; text-decoration: none; color: inherit;
            transition: box-shadow 0.2s, transform 0.2s; border: 1px solid #eee;
        }
        .nav-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.12); transform: translateY(-2px); }
        .nav-card .icon { font-size: 2rem; margin-bottom: 12px; }
        .nav-card h3 { color: #1a237e; font-size: 1.1rem; margin-bottom: 6px; }
        .nav-card p { color: #757575; font-size: 0.85rem; }
        /* ── Misc ── */
        .text-center { text-align: center; }
        .mt-20 { margin-top: 20px; }
        .mb-0 { margin-bottom: 0; }
        .empty-state { text-align: center; padding: 40px; color: #9e9e9e; }
        .empty-state .icon { font-size: 3rem; margin-bottom: 12px; }
        .flex-between { display: flex; justify-content: space-between; align-items: center; }
        .vehicle-info { font-size: 0.8rem; color: #757575; margin-top: 2px; }
    </style>
</head>
<body>

<div class="navbar">
    <a class="navbar-brand" href="<?= base_url() ?>/index.php">🚛 Smart Fleet</a>
    
    <div class="navbar-nav">
        <div>
            <a href="<?= base_url() ?>/index.php">Dashboard</a>
        </div>

        <?php if (has_role(ROLE_ADMIN, ROLE_FLEET_SAFETY)): ?>
        <div>
            <div class="dropdown-toggle">Assignments ▾</div>
            <div class="dropdown-menu">
                <a href="<?= base_url() ?>/assignments/list.php">View Assignments</a>
                <a href="<?= base_url() ?>/assignments/assign.php">New Assignment</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (has_role(ROLE_ADMIN, ROLE_FLEET_SAFETY)): ?>
        <div>
            <div class="dropdown-toggle">Telematics ▾</div>
            <div class="dropdown-menu">
                <a href="<?= base_url() ?>/telematics/log_event.php">Log Event</a>
                <a href="<?= base_url() ?>/telematics/driver_score.php">Driver Scores</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (has_role(ROLE_ADMIN, ROLE_WORKSHOP)): ?>
        <div>
            <div class="dropdown-toggle">Maintenance ▾</div>
            <div class="dropdown-menu">
                <a href="<?= base_url() ?>/maintenance/open_job.php">Open Job</a>
                <a href="<?= base_url() ?>/maintenance/close_job.php">Close Job</a>
                <a href="<?= base_url() ?>/maintenance/add_part.php">Add Part</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (has_role(ROLE_ADMIN, ROLE_FLEET_SAFETY, ROLE_WORKSHOP)): ?>
        <div>
            <div class="dropdown-toggle">Reports ▾</div>
            <div class="dropdown-menu">
                <?php if (has_role(ROLE_ADMIN, ROLE_FLEET_SAFETY)): ?>
                    <a href="<?= base_url() ?>/reports/expired_certifications.php">Expired Certifications</a>
                <?php endif; ?>
                <?php if (has_role(ROLE_ADMIN, ROLE_WORKSHOP)): ?>
                    <a href="<?= base_url() ?>/reports/repeated_faults.php">Repeated Faults</a>
                    <a href="<?= base_url() ?>/reports/parts_cost_by_model.php">Parts Cost by Model</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="navbar-user">
        <div class="user-info">
            <div class="name"><?= htmlspecialchars(current_username() ?? 'User') ?></div>
            <span class="role-badge"><?= htmlspecialchars(current_role() ?? '') ?></span>
            <?php if (current_depot_name()): ?>
                <div style="font-size:0.78rem; opacity:0.7;"><?= htmlspecialchars(current_depot_name()) ?></div>
            <?php endif; ?>
        </div>
        <a href="<?= base_url() ?>/logout.php" class="btn-logout">Logout</a>
    </div>
</div>

<div class="container">