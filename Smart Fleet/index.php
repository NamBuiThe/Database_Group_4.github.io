<?php
/**
 * index.php — Dashboard / landing page after login.
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

<div class="card">
    <h2>Navigation</h2>
    <p class="subtitle">Click a card below to access your screens.</p>

    <div class="nav-grid">
        <?php if (has_role(ROLE_ADMIN, ROLE_FLEET_SAFETY)): ?>
        <a href="<?= base_url() ?>/assignments/list.php" class="nav-card">
            <div class="icon">🚚</div>
            <h3>Vehicle Assignments</h3>
            <p>Assign drivers to vehicles, view current and past assignments.</p>
        </a>
        <?php endif; ?>

        <?php if (has_role(ROLE_ADMIN, ROLE_FLEET_SAFETY)): ?>
        <a href="<?= base_url() ?>/telematics/log_event.php" class="nav-card">
            <div class="icon">📡</div>
            <h3>Log Telematics Event</h3>
            <p>Record driver behaviour events and safety incidents.</p>
        </a>
        <a href="<?= base_url() ?>/telematics/driver_score.php" class="nav-card">
            <div class="icon">📊</div>
            <h3>Driver Safety Scores</h3>
            <p>View driver safety scores and event statistics.</p>
        </a>
        <?php endif; ?>

        <?php if (has_role(ROLE_ADMIN, ROLE_WORKSHOP)): ?>
        <a href="<?= base_url() ?>/maintenance/open_job.php" class="nav-card">
            <div class="icon">🔧</div>
            <h3>Open Maintenance Job</h3>
            <p>Create a new maintenance job for a vehicle.</p>
        </a>
        <a href="<?= base_url() ?>/maintenance/close_job.php" class="nav-card">
            <div class="icon">✅</div>
            <h3>Close Maintenance Job</h3>
            <p>Close existing jobs, calculate labour costs, and set vehicle to Available.</p>
        </a>
        <a href="<?= base_url() ?>/maintenance/add_part.php" class="nav-card">
            <div class="icon">⚙️</div>
            <h3>Add Part to Activity</h3>
            <p>Record parts used in maintenance activities.</p>
        </a>
        <?php endif; ?>

        <?php if (has_role(ROLE_ADMIN, ROLE_FLEET_SAFETY)): ?>
        <a href="<?= base_url() ?>/reports/expired_certifications.php" class="nav-card">
            <div class="icon">⚠️</div>
            <h3>Expired Certifications</h3>
            <p>View drivers with expired or soon-to-expire certifications.</p>
        </a>
        <?php endif; ?>

        <?php if (has_role(ROLE_ADMIN, ROLE_WORKSHOP)): ?>
        <a href="<?= base_url() ?>/reports/repeated_faults.php" class="nav-card">
            <div class="icon">🔁</div>
            <h3>Repeated Faults</h3>
            <p>Identify vehicles with recurring component failures.</p>
        </a>
        <a href="<?= base_url() ?>/reports/parts_cost_by_model.php" class="nav-card">
            <div class="icon">💰</div>
            <h3>Parts Cost by Model</h3>
            <p>Compare maintenance costs across vehicle models.</p>
        </a>
        <?php endif; ?>
    </div>
</div>

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

        $sql = "SELECT COUNT(*) FROM Vehicle" . $depotFilter;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($depotParams);
        $totalVehicles = $stmt->fetchColumn();

        $sql = $isAdmin ? "SELECT COUNT(*) FROM Vehicle WHERE status = 'Available'" : "SELECT COUNT(*) FROM Vehicle WHERE status = 'Available' AND depot_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($isAdmin ? [] : [current_depot_id()]);
        $availVehicles = $stmt->fetchColumn();

        $sql = $isAdmin ? "SELECT COUNT(*) FROM Vehicle WHERE status = 'Under Maintenance'" : "SELECT COUNT(*) FROM Vehicle WHERE status = 'Under Maintenance' AND depot_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($isAdmin ? [] : [current_depot_id()]);
        $maintVehicles = $stmt->fetchColumn();

        $sql = $isAdmin ? "SELECT COUNT(*) FROM Driver WHERE employment_status = 'Active'" : "SELECT COUNT(*) FROM Driver WHERE employment_status = 'Active' AND depot_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($isAdmin ? [] : [current_depot_id()]);
        $activeDrivers = $stmt->fetchColumn();

        $sql = $isAdmin 
            ? "SELECT COUNT(*) FROM Vehicle_Assignments WHERE end_date IS NULL"
            : "SELECT COUNT(*) FROM Vehicle_Assignments va JOIN Vehicle v ON va.vehicle_id = v.id WHERE va.end_date IS NULL AND v.depot_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($isAdmin ? [] : [current_depot_id()]);
        $currentAssignments = $stmt->fetchColumn();
        
        $openJobs = $pdo->query("SELECT COUNT(*) FROM Maintenance_Jobs WHERE date_closed IS NULL")->fetchColumn();
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
        <div style="text-align:center; padding:20px; background:#ffe0b2; border-radius:10px;">
            <div style="font-size:2rem; font-weight:700; color:#e65100;"><?= $openJobs ?></div>
            <div style="font-size:0.85rem; color:#bf360c;">Open Maint. Jobs</div>
        </div>
        <div style="text-align:center; padding:20px; background:#eceff1; border-radius:10px;">
            <div style="font-size:2rem; font-weight:700; color:#37474f;"><?= $totalVehicles ?></div>
            <div style="font-size:0.85rem; color:#263238;">Total Vehicles</div>
        </div>
    </div>
</div>

<?php require BASE_PATH . '/includes/footer.php'; ?>