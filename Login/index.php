<?php
/**
 * index.php — Dashboard / landing page after login.
 *
 * Shows role-based navigation cards so users can jump to their screens.
 * Access: any logged-in user (require_login only — no role gating).
 */

require_once __DIR__ . '/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_login();

require_once BASE_PATH . '/includes/header.php';

$username  = current_username();
$roleName  = current_role();
$depotName = current_depot_name();
$isAdmin   = is_admin();
?>

<!-- ── Welcome banner ── -->
<div class="card" style="background: linear-gradient(135deg, #1a237e 0%, #0d47a1 100%); color: #fff; border: none;">
    <h1 style="color: #fff; margin-bottom: 4px;">
        Welcome back, <?= htmlspecialchars($username ?? 'User') ?> 👋
    </h1>
    <p style="color: rgba(255,255,255,0.85); font-size: 1rem; margin-bottom: 0;">
        You are logged in as <strong><?= htmlspecialchars($roleName ?? '') ?></strong>
        <?php if ($depotName): ?>
            at <strong><?= htmlspecialchars($depotName) ?></strong>
        <?php endif; ?>
    </p>
</div>

<!-- ── Navigation cards ── -->
<div class="card">
    <h2>Navigation</h2>
    <p class="subtitle">Click a card below to access your screens.</p>

    <div class="nav-grid">

        <!-- ── Vehicle Assignments (Fleet Safety + Admin) ── -->
        <?php if (has_role(ROLE_ADMIN, ROLE_FLEET_SAFETY)): ?>
        <a href="<?= base_url() ?>/assignments/list.php" class="nav-card">
            <div class="icon">🚚</div>
            <h3>Vehicle Assignments</h3>
            <p>Assign drivers to vehicles, view current and past assignments.</p>
        </a>
        <?php endif; ?>

        <!-- ── Telematics Events (Fleet Safety + Admin) ── -->
        <?php if (has_role(ROLE_ADMIN, ROLE_FLEET_SAFETY)): ?>
        <a href="<?= base_url() ?>/telematics/log_event.php" class="nav-card">
            <div class="icon">📡</div>
            <h3>Telematics Events</h3>
            <p>Log driver behaviour events and view safety scores.</p>
        </a>
        <?php endif; ?>

        <!-- ── Maintenance Jobs (Workshop + Admin) ── -->
        <?php if (has_role(ROLE_ADMIN, ROLE_WORKSHOP)): ?>
        <a href="<?= base_url() ?>/maintenance/open_job.php" class="nav-card">
            <div class="icon">🔧</div>
            <h3>Maintenance Jobs</h3>
            <p>Open and close maintenance jobs, record parts and labour.</p>
        </a>
        <?php endif; ?>

        <!-- ── Reports: Expired Certifications (Fleet Safety + Admin) ── -->
        <?php if (has_role(ROLE_ADMIN, ROLE_FLEET_SAFETY)): ?>
        <a href="<?= base_url() ?>/reports/expired_certifications.php" class="nav-card">
            <div class="icon">⚠️</div>
            <h3>Expired Certifications</h3>
            <p>View drivers with expired or soon-to-expire certifications.</p>
        </a>
        <?php endif; ?>

        <!-- ── Reports: Repeated Faults (Workshop + Admin) ── -->
        <?php if (has_role(ROLE_ADMIN, ROLE_WORKSHOP)): ?>
        <a href="<?= base_url() ?>/reports/repeated_faults.php" class="nav-card">
            <div class="icon">🔁</div>
            <h3>Repeated Faults</h3>
            <p>Identify vehicles with recurring component failures.</p>
        </a>
        <?php endif; ?>

        <!-- ── Reports: Parts Cost by Model (Workshop + Admin) ── -->
        <?php if (has_role(ROLE_ADMIN, ROLE_WORKSHOP)): ?>
        <a href="<?= base_url() ?>/reports/parts_cost_by_model.php" class="nav-card">
            <div class="icon">💰</div>
            <h3>Parts Cost by Model</h3>
            <p>Compare maintenance costs across vehicle models.</p>
        </a>
        <?php endif; ?>

        <!-- ── Driver (placeholder for future) ── -->
        <?php if (is_driver()): ?>
        <div class="nav-card" style="opacity: 0.5; cursor: default;">
            <div class="icon">🚗</div>
            <h3>My Safety History</h3>
            <p>Coming soon — driver-facing safety score dashboard.</p>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- ── Quick stats ── -->
<div class="card">
    <h2>Quick Overview</h2>
    <p class="subtitle">Live counts from the database (scoped to your depot).</p>

    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:16px;">

        <?php
        $depotFilter = '';
        $depotParams = [];
        if (!$isAdmin) {
            $depotFilter = ' WHERE depot_id = ?';
            $depotParams[] = current_depot_id();
        }

        // Active vehicles
        $sql = "SELECT COUNT(*) FROM Vehicle" . $depotFilter;
        if (!$isAdmin) $sql = "SELECT COUNT(*) FROM Vehicle WHERE depot_id = ?";
        else $sql = "SELECT COUNT(*) FROM Vehicle";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($depotParams);
        $totalVehicles = $stmt->fetchColumn();

        // Available vehicles
        $sql = $isAdmin
            ? "SELECT COUNT(*) FROM Vehicle WHERE status = 'Available'"
            : "SELECT COUNT(*) FROM Vehicle WHERE status = 'Available' AND depot_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($isAdmin ? [] : [current_depot_id()]);
        $availVehicles = $stmt->fetchColumn();

        // Under maintenance
        $sql = $isAdmin
            ? "SELECT COUNT(*) FROM Vehicle WHERE status = 'Under Maintenance'"
            : "SELECT COUNT(*) FROM Vehicle WHERE status = 'Under Maintenance' AND depot_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($isAdmin ? [] : [current_depot_id()]);
        $maintVehicles = $stmt->fetchColumn();

        // Active drivers
        $sql = $isAdmin
            ? "SELECT COUNT(*) FROM Driver WHERE employment_status = 'Active'"
            : "SELECT COUNT(*) FROM Driver WHERE employment_status = 'Active' AND depot_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($isAdmin ? [] : [current_depot_id()]);
        $activeDrivers = $stmt->fetchColumn();

        // Current assignments
        $sql = $isAdmin
            ? "SELECT COUNT(*) FROM Vehicle_Assignments WHERE end_date IS NULL"
            : "SELECT COUNT(*) FROM Vehicle_Assignments va JOIN Vehicle v ON va.vehicle_id = v.id WHERE va.end_date IS NULL AND v.depot_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($isAdmin ? [] : [current_depot_id()]);
        $currentAssignments = $stmt->fetchColumn();
        ?>

        <div style="text-align:center; padding:20px; background:#e8f5e9; border-radius:10px;">
            <div style="font-size:2rem; font-weight:700; color:#2e7d32;"><?= $availVehicles ?></div>
            <div style="font-size:0.85rem; color:#558b2f;">Available Vehicles</div>
        </div>

        <div style="text-align:center; padding:20px; background:#fff9c4; border-radius:10px;">
            <div style="font-size:2rem; font-weight:700; color:#f57f17;"><?= $maintVehicles ?></div>
            <div style="font-size:0.85rem; color:#7c6f00;">Under Maintenance</div>
        </div>

        <div style="text-align:center; padding:20px; background:#e3f2fd; border-radius:10px;">
            <div style="font-size:2rem; font-weight:700; color:#1565c0;"><?= $activeDrivers ?></div>
            <div style="font-size:0.85rem; color:#0d47a1;">Active Drivers</div>
        </div>

        <div style="text-align:center; padding:20px; background:#f3e5f5; border-radius:10px;">
            <div style="font-size:2rem; font-weight:700; color:#6a1b9a;"><?= $currentAssignments ?></div>
            <div style="font-size:0.85rem; color:#4a148c;">Current Assignments</div>
        </div>

        <div style="text-align:center; padding:20px; background:#eceff1; border-radius:10px;">
            <div style="font-size:2rem; font-weight:700; color:#37474f;"><?= $totalVehicles ?></div>
            <div style="font-size:0.85rem; color:#263238;">Total Vehicles</div>
        </div>

    </div>
</div>

<!-- ── Role info card ── -->
<div class="card">
    <h2>Your Access Level</h2>
    <p class="subtitle">What you can do based on your role.</p>

    <?php if (is_admin()): ?>
        <div class="alert alert-info">
            <strong>👑 Admin</strong> — You have full access to all screens across all depots.
            You can manage vehicle assignments, telematics events, maintenance jobs, and view all reports.
        </div>
    <?php elseif (is_fleet_safety()): ?>
        <div class="alert alert-info">
            <strong>🛡️ Fleet Safety Staff</strong> — You can manage vehicle assignments,
            log telematics events, view driver safety scores, and access safety-related reports.
        </div>
    <?php elseif (is_workshop()): ?>
        <div class="alert alert-info">
            <strong>🔧 Workshop Staff</strong> — You can open and close maintenance jobs,
            record parts usage, and access workshop-related reports.
        </div>
    <?php elseif (is_driver()): ?>
        <div class="alert alert-info">
            <strong>🚗 Driver</strong> — Your dashboard is currently limited.
            Driver-facing features (viewing your safety score and event history) will be added in a future update.
        </div>
    <?php endif; ?>
</div>

<?php require BASE_PATH . '/includes/footer.php'; ?>