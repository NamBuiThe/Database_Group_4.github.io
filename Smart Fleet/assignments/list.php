<?php
/**
 * assignments/list.php — View current vehicle assignments.
 *
 * Use Case: UC-A1 (supporting view — "View current assignments")
 * Access:   Admin, Fleet Safety Staff
 *
 * Shows:
 *   - Current assignments (end_date IS NULL)
 *   - Assignment history (end_date IS NOT NULL) — optional, below
 *   - "New Assignment" button → assign.php
 *   - "End Assignment" button per row → POST to this same page
 */

require_once dirname(__DIR__) . '/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/header.php';

require_fleet_safety();

$depotId  = current_depot_id();
$isAdmin  = is_admin();
$message  = '';
$msgType  = '';

// ── Handle "End Assignment" action ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'end_assignment') {
    $assignmentId = (int) ($_POST['assignment_id'] ?? 0);

    try {
        $pdo->beginTransaction();

        // Get the assignment + vehicle_id (verify it belongs to user's depot if not admin)
        $stmt = $pdo->prepare(
            'SELECT va.id, va.vehicle_id, va.end_date, v.depot_id
             FROM Vehicle_Assignments va
             JOIN Vehicle v ON va.vehicle_id = v.id
             WHERE va.id = ?'
        );
        $stmt->execute([$assignmentId]);
        $assignment = $stmt->fetch();

        if (!$assignment) {
            throw new Exception('Assignment not found.');
        }
        if ($assignment['end_date'] !== null) {
            throw new Exception('This assignment has already ended.');
        }
        if (!$isAdmin && (int)$assignment['depot_id'] !== $depotId) {
            throw new Exception('You can only manage assignments in your own depot.');
        }

        // End the assignment
        $stmt = $pdo->prepare(
            'UPDATE Vehicle_Assignments SET end_date = CURDATE() WHERE id = ?'
        );
        $stmt->execute([$assignmentId]);

        // Set vehicle status back to 'Available'
        $stmt = $pdo->prepare(
            "UPDATE Vehicle SET status = 'Available' WHERE id = ?"
        );
        $stmt->execute([$assignment['vehicle_id']]);

        $pdo->commit();
        $message = 'Assignment ended successfully. Vehicle is now Available.';
        $msgType = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = $e->getMessage();
        $msgType = 'error';
    }
}

// ── Fetch current assignments ──
$currentSql = "
    SELECT  va.id           AS assignment_id,
            va.start_date,
            v.id            AS vehicle_id,
            v.registration_number,
            v.status         AS vehicle_status,
            vm.model_name,
            vm.manufacturer,
            vm.vehicle_category,
            d.id            AS driver_id,
            d.full_name      AS driver_name,
            d.license_type,
            d.employment_status,
            dep.depot_name,
            dep.city
    FROM    Vehicle_Assignments va
    JOIN    Vehicle        v   ON va.vehicle_id = v.id
    JOIN    Vehicle_Models vm  ON v.model_id    = vm.id
    JOIN    Driver         d   ON va.driver_id  = d.id
    JOIN    Depot          dep ON v.depot_id     = dep.id
    WHERE   va.end_date IS NULL
";

$params = [];

if (!$isAdmin) {
    $currentSql .= " AND v.depot_id = ?";
    $params[] = $depotId;
}

$currentSql .= " ORDER BY va.start_date DESC";

$stmt = $pdo->prepare($currentSql);
$stmt->execute($params);
$currentAssignments = $stmt->fetchAll();

// ── Fetch assignment history (last 15) ──
$historySql = "
    SELECT  va.id           AS assignment_id,
            va.start_date,
            va.end_date,
            v.registration_number,
            vm.model_name,
            vm.vehicle_category,
            d.full_name      AS driver_name,
            dep.depot_name
    FROM    Vehicle_Assignments va
    JOIN    Vehicle        v   ON va.vehicle_id = v.id
    JOIN    Vehicle_Models vm  ON v.model_id    = vm.id
    JOIN    Driver         d   ON va.driver_id  = d.id
    JOIN    Depot          dep ON v.depot_id     = dep.id
    WHERE   va.end_date IS NOT NULL
";

$historyParams = [];

if (!$isAdmin) {
    $historySql .= " AND v.depot_id = ?";
    $historyParams[] = $depotId;
}

$historySql .= " ORDER BY va.end_date DESC LIMIT 15";

$stmt = $pdo->prepare($historySql);
$stmt->execute($historyParams);
$historyAssignments = $stmt->fetchAll();

// ── Fetch available vehicles for sidebar summary ──
$availSql = "
    SELECT v.id, v.registration_number, vm.model_name, vm.vehicle_category,
           v.status
    FROM Vehicle v
    JOIN Vehicle_Models vm ON v.model_id = vm.id
    WHERE v.status IN ('Available', 'Awaiting Inspection')
";
$availParams = [];
if (!$isAdmin) {
    $availSql .= " AND v.depot_id = ?";
    $availParams[] = $depotId;
}
$availSql .= " ORDER BY v.registration_number LIMIT 10";

$stmt = $pdo->prepare($availSql);
$stmt->execute($availParams);
$availableVehicles = $stmt->fetchAll();

// ── Fetch eligible drivers for sidebar summary ──
$eligSql = "
    SELECT id, full_name, license_type, license_expiry
    FROM Driver
    WHERE employment_status = 'Active'
      AND license_expiry >= CURDATE()
";
$eligParams = [];
if (!$isAdmin) {
    $eligSql .= " AND depot_id = ?";
    $eligParams[] = $depotId;
}
$eligSql .= " ORDER BY full_name LIMIT 10";

$stmt = $pdo->prepare($eligSql);
$stmt->execute($eligParams);
$eligibleDrivers = $stmt->fetchAll();
?>

<?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($msgType) ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- ── Current Assignments ── -->
<div class="card">
    <div class="flex-between mb-0">
        <div>
            <h1>Current Vehicle Assignments</h1>
            <p class="subtitle">
                Active driver-vehicle pairings
                <?php if (!$isAdmin): ?>
                    — <?= htmlspecialchars(current_depot_name()) ?>
                <?php else: ?>
                    — All depots
                <?php endif; ?>
            </p>
        </div>
        <a href="<?= base_url() ?>/assignments/assign.php" class="btn btn-success">
            + New Assignment
        </a>
    </div>

    <?php if (empty($currentAssignments)): ?>
        <div class="empty-state">
            <div class="icon">🚗</div>
            <p>No active assignments found.</p>
            <p style="margin-top:10px;">
                <a href="<?= base_url() ?>/assignments/assign.php" class="btn btn-primary btn-sm">
                    Create one now
                </a>
            </p>
        </div>
    <?php else: ?>
        <table class="data-table" style="margin-top:16px;">
            <thead>
                <tr>
                    <th>Vehicle</th>
                    <th>Model / Category</th>
                    <th>Driver</th>
                    <th>Depot</th>
                    <th>Started</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($currentAssignments as $row): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($row['registration_number']) ?></strong>
                            <div class="vehicle-info">
                                <?= htmlspecialchars($row['vehicle_category']) ?>
                            </div>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['manufacturer'] . ' ' . $row['model_name']) ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($row['driver_name']) ?></strong>
                            <div class="vehicle-info">
                                <?= htmlspecialchars($row['license_type']) ?>
                            </div>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['depot_name']) ?>
                            <div class="vehicle-info"><?= htmlspecialchars($row['city']) ?></div>
                        </td>
                        <td><?= htmlspecialchars($row['start_date']) ?></td>
                        <td>
                            <form method="post" style="display:inline;"
                                  onsubmit="return confirm('End this assignment? Vehicle will be set to Available.');">
                                <input type="hidden" name="action" value="end_assignment">
                                <input type="hidden" name="assignment_id"
                                       value="<?= (int) $row['assignment_id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    End
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- ── Sidebar: Available Vehicles & Eligible Drivers ── -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
    <!-- Available Vehicles -->
    <div class="card">
        <h2>🚚 Available Vehicles</h2>
        <p class="subtitle">Vehicles ready for assignment</p>
        <?php if (empty($availableVehicles)): ?>
            <p style="color:#9e9e9e;">No available vehicles.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Reg. Number</th>
                        <th>Model</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($availableVehicles as $v): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($v['registration_number']) ?></strong></td>
                            <td><?= htmlspecialchars($v['model_name']) ?></td>
                            <td>
                                <?php
                                $badgeClass = 'badge-available';
                                if ($v['status'] === 'Awaiting Inspection') $badgeClass = 'badge-inspection';
                                ?>
                                <span class="badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars($v['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Eligible Drivers -->
    <div class="card">
        <h2>👤 Eligible Drivers</h2>
        <p class="subtitle">Active drivers with valid licenses</p>
        <?php if (empty($eligibleDrivers)): ?>
            <p style="color:#9e9e9e;">No eligible drivers.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>License</th>
                        <th>Expires</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eligibleDrivers as $d): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($d['full_name']) ?></strong></td>
                            <td><?= htmlspecialchars($d['license_type']) ?></td>
                            <td>
                                <?php
                                $expiryDate = new DateTime($d['license_expiry']);
                                $today      = new DateTime();
                                $daysLeft   = $today->diff($expiryDate)->days;
                                $expiring   = $daysLeft <= 30;
                                ?>
                                <span style="color: <?= $expiring ? '#e65100' : '#424242' ?>;">
                                    <?= htmlspecialchars($d['license_expiry']) ?>
                                    <?php if ($expiring): ?>
                                        <small>(⚠ <?= $daysLeft ?>d left)</small>
                                    <?php endif; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- ── Assignment History ── -->
<?php if (!empty($historyAssignments)): ?>
<div class="card mt-20">
    <h2>📋 Recent Assignment History</h2>
    <p class="subtitle">Last 15 completed assignments</p>
    <table class="data-table">
        <thead>
            <tr>
                <th>Vehicle</th>
                <th>Category</th>
                <th>Driver</th>
                <th>Depot</th>
                <th>Started</th>
                <th>Ended</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($historyAssignments as $row): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['registration_number']) ?></strong></td>
                    <td><?= htmlspecialchars($row['vehicle_category']) ?></td>
                    <td><?= htmlspecialchars($row['driver_name']) ?></td>
                    <td><?= htmlspecialchars($row['depot_name']) ?></td>
                    <td><?= htmlspecialchars($row['start_date']) ?></td>
                    <td><?= htmlspecialchars($row['end_date']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require BASE_PATH . '/includes/footer.php'; ?>