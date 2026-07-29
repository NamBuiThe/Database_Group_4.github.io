<?php
/**
 * assignments/assign.php — UC-A1: Assign a driver to a vehicle.
 *
 * This is the REFERENCE TRANSACTION IMPLEMENTATION.
 * Person 2 and Person 3 should copy this pattern for their own screens:
 *   1. require auth
 *   2. fetch dropdown data
 *   3. on POST: beginTransaction → validate (multiple checks) → write → commit/rollback
 *
 * Access: Admin, Fleet Safety Staff
 *
 * Transaction steps:
 *   1. Check vehicle status (reject Under Maintenance, Out of Service, Retired)
 *   2. Check driver eligibility (Active status, valid license)
 *   3. Check certification requirements (driver has all required certs for vehicle category)
 *   4. Close any open assignment for this vehicle
 *   5. Insert new assignment
 *   6. Update vehicle status to 'Active'
 *   All wrapped in a transaction — all or nothing.
 */

require_once dirname(__DIR__) . '/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/header.php';

require_fleet_safety();

$depotId  = current_depot_id();
$isAdmin  = is_admin();
$message  = '';
$msgType  = '';

// ═══════════════════════════════════════════════════════════════
//  POST HANDLER — The Transaction
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
    $driverId  = (int) ($_POST['driver_id']  ?? 0);

    $errors = [];

    if ($vehicleId <= 0) $errors[] = 'Please select a vehicle.';
    if ($driverId  <= 0) $errors[] = 'Please select a driver.';

    if (empty($errors)) {
        try {
            // ── Begin transaction ──
            $pdo->beginTransaction();

            // ──────────────────────────────────────────────
            // STEP 1: Check vehicle status & depot
            // ──────────────────────────────────────────────
            $stmt = $pdo->prepare(
                'SELECT v.id, v.status, v.depot_id,
                        vm.vehicle_category, vm.model_name, vm.manufacturer,
                        v.registration_number
                 FROM Vehicle v
                 JOIN Vehicle_Models vm ON v.model_id = vm.id
                 WHERE v.id = ?'
            );
            $stmt->execute([$vehicleId]);
            $vehicle = $stmt->fetch();

            if (!$vehicle) {
                throw new Exception('Vehicle not found.');
            }

            // Depot check (non-admins can only assign vehicles in their depot)
            if (!$isAdmin && (int)$vehicle['depot_id'] !== $depotId) {
                throw new Exception('You can only assign vehicles in your own depot.');
            }

            // Status check
            $blockedStatuses = ['Under Maintenance', 'Out of Service', 'Retired'];
            if (in_array($vehicle['status'], $blockedStatuses, true)) {
                throw new Exception(
                    "Vehicle {$vehicle['registration_number']} is currently '{$vehicle['status']}' " .
                    "and cannot be assigned."
                );
            }

            // ──────────────────────────────────────────────
            // STEP 2: Check driver eligibility
            // ──────────────────────────────────────────────
            $stmt = $pdo->prepare(
                'SELECT id, full_name, depot_id, license_expiry,
                        employment_status, license_type
                 FROM Driver
                 WHERE id = ?'
            );
            $stmt->execute([$driverId]);
            $driver = $stmt->fetch();

            if (!$driver) {
                throw new Exception('Driver not found.');
            }

            // Depot check (non-admins can only assign drivers in their depot)
            if (!$isAdmin && (int)$driver['depot_id'] !== $depotId) {
                throw new Exception('You can only assign drivers in your own depot.');
            }

            // Employment status check
            if ($driver['employment_status'] !== 'Active') {
                throw new Exception(
                    "Driver {$driver['full_name']} is currently '{$driver['employment_status']}' " .
                    "and cannot be assigned."
                );
            }

            // License expiry check
            $today = date('Y-m-d');
            if ($driver['license_expiry'] < $today) {
                throw new Exception(
                    "Driver {$driver['full_name']}'s license expired on {$driver['license_expiry']}. " .
                    "Cannot assign until license is renewed."
                );
            }

            // ──────────────────────────────────────────────
            // STEP 3: Check certification requirements
            // ──────────────────────────────────────────────
            // Find all certifications required for this vehicle category
            // that the driver does NOT hold (or has expired).
            $stmt = $pdo->prepare(
                'SELECT ccr.certification_id, c.certification_name
                 FROM Category_Certification_Requirements ccr
                 JOIN Certifications c ON ccr.certification_id = c.id
                 WHERE ccr.vehicle_category = ?
                   AND NOT EXISTS (
                       SELECT 1
                       FROM Driver_Certifications dc
                       WHERE dc.driver_id = ?
                         AND dc.certification_id = ccr.certification_id
                         AND dc.expiry_date >= CURDATE()
                   )'
            );
            $stmt->execute([$vehicle['vehicle_category'], $driverId]);
            $missingCerts = $stmt->fetchAll();

            if (!empty($missingCerts)) {
                $certNames = array_map(fn($c) => $c['certification_name'], $missingCerts);
                throw new Exception(
                    "Driver {$driver['full_name']} is missing required certification(s) for " .
                    "'{$vehicle['vehicle_category']}': " . implode(', ', $certNames) . ". " .
                    "Cannot assign until certifications are obtained."
                );
            }

            // ──────────────────────────────────────────────
            // STEP 4: Close any open assignment for this vehicle
            // ──────────────────────────────────────────────
            $stmt = $pdo->prepare(
                'UPDATE Vehicle_Assignments
                 SET end_date = CURDATE()
                 WHERE vehicle_id = ? AND end_date IS NULL'
            );
            $stmt->execute([$vehicleId]);

            $closedCount = $stmt->rowCount();
            // Note: rowCount() may return 0 if no open assignment existed — that's fine.

            // ──────────────────────────────────────────────
            // STEP 5: Create new assignment
            // ──────────────────────────────────────────────
            $stmt = $pdo->prepare(
                'INSERT INTO Vehicle_Assignments (vehicle_id, driver_id, start_date, end_date)
                 VALUES (?, ?, CURDATE(), NULL)'
            );
            $stmt->execute([$vehicleId, $driverId]);

            // ──────────────────────────────────────────────
            // STEP 6: Update vehicle status to 'Active'
            // ──────────────────────────────────────────────
            $stmt = $pdo->prepare(
                "UPDATE Vehicle SET status = 'Active' WHERE id = ?"
            );
            $stmt->execute([$vehicleId]);

            // ── Commit ──
            $pdo->commit();

            $message = sprintf(
                "✅ Driver <strong>%s</strong> has been assigned to vehicle <strong>%s</strong> (%s %s). " .
                "Vehicle status updated to Active.",
                htmlspecialchars($driver['full_name']),
                htmlspecialchars($vehicle['registration_number']),
                htmlspecialchars($vehicle['manufacturer']),
                htmlspecialchars($vehicle['model_name'])
            );
            $msgType = 'success';

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = '❌ ' . $e->getMessage();
            $msgType = 'error';
        }
    } else {
        $message = '❌ ' . implode(' ', $errors);
        $msgType = 'error';
    }
}

// ═══════════════════════════════════════════════════════════════
//  FETCH DROPDOWN DATA
// ═══════════════════════════════════════════════════════════════

// ── Available vehicles (not Under Maintenance / Out of Service / Retired) ──
$vehicleSql = "
    SELECT v.id, v.registration_number, v.status,
           vm.model_name, vm.manufacturer, vm.vehicle_category,
           dep.depot_name
    FROM Vehicle v
    JOIN Vehicle_Models vm ON v.model_id = vm.id
    JOIN Depot          dep ON v.depot_id = dep.id
    WHERE v.status NOT IN ('Under Maintenance', 'Out of Service', 'Retired')
";
$vehicleParams = [];
if (!$isAdmin) {
    $vehicleSql .= " AND v.depot_id = ?";
    $vehicleParams[] = $depotId;
}
$vehicleSql .= " ORDER BY v.registration_number";

$stmt = $pdo->prepare($vehicleSql);
$stmt->execute($vehicleParams);
$vehicles = $stmt->fetchAll();

// ── Eligible drivers (Active + valid license) ──
$driverSql = "
    SELECT d.id, d.full_name, d.license_type, d.license_expiry, d.employment_status,
           dep.depot_name
    FROM Driver d
    JOIN Depot dep ON d.depot_id = dep.id
    WHERE d.employment_status = 'Active'
      AND d.license_expiry >= CURDATE()
";
$driverParams = [];
if (!$isAdmin) {
    $driverSql .= " AND d.depot_id = ?";
    $driverParams[] = $depotId;
}
$driverSql .= " ORDER BY d.full_name";

$stmt = $pdo->prepare($driverSql);
$stmt->execute($driverParams);
$drivers = $stmt->fetchAll();
?>

<!-- ── Success / Error message ── -->
<?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($msgType) ?>">
        <?= $message /* HTML allowed — already escaped where needed */ ?>
    </div>
<?php endif; ?>

<!-- ── Assignment Form ── -->
<div class="card">
    <h1>Assign Driver to Vehicle</h1>
    <p class="subtitle">
        Select a vehicle and a driver to create a new assignment.
        <?php if (!$isAdmin): ?>
            Only vehicles and drivers from <strong><?= htmlspecialchars(current_depot_name()) ?></strong> are shown.
        <?php else: ?>
            Showing all vehicles and drivers across all depots.
        <?php endif; ?>
    </p>

    <form method="post" action="">
        <div class="form-group">
            <label for="vehicle_id">🚚 Vehicle</label>
            <select name="vehicle_id" id="vehicle_id" required>
                <option value="">— Select a vehicle —</option>
                <?php foreach ($vehicles as $v): ?>
                    <option value="<?= (int) $v['id'] ?>"
                        <?= (isset($_POST['vehicle_id']) && (int)$_POST['vehicle_id'] === (int)$v['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($v['registration_number']) ?>
                        — <?= htmlspecialchars($v['manufacturer'] . ' ' . $v['model_name']) ?>
                        (<?= htmlspecialchars($v['vehicle_category']) ?>)
                        [<?= htmlspecialchars($v['status']) ?>]
                        <?php if ($isAdmin): ?>
                            — <?= htmlspecialchars($v['depot_name']) ?>
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="hint">
                Vehicles currently Under Maintenance, Out of Service, or Retired are excluded.
            </div>
        </div>

        <div class="form-group">
            <label for="driver_id">👤 Driver</label>
            <select name="driver_id" id="driver_id" required>
                <option value="">— Select a driver —</option>
                <?php foreach ($drivers as $d): ?>
                    <option value="<?= (int) $d['id'] ?>"
                        <?= (isset($_POST['driver_id']) && (int)$_POST['driver_id'] === (int)$d['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['full_name']) ?>
                        — <?= htmlspecialchars($d['license_type']) ?>
                        (license expires: <?= htmlspecialchars($d['license_expiry']) ?>)
                        <?php if ($isAdmin): ?>
                            — <?= htmlspecialchars($d['depot_name']) ?>
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="hint">
                Only active drivers with non-expired licenses are listed.
                Certification requirements will be checked on submission.
            </div>
        </div>

        <div style="display:flex; gap:12px; margin-top:24px;">
            <button type="submit" class="btn btn-primary">
                ✅ Confirm Assignment
            </button>
            <a href="<?= base_url() ?>/assignments/list.php" class="btn btn-secondary">
                Cancel
            </a>
        </div>
    </form>
</div>

<!-- ── How it works (for the team — reference documentation) ── -->
<div class="card">
    <h2>📋 How This Assignment Works</h2>
    <p class="subtitle">The validation logic executed on submission</p>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:60px;">Step</th>
                <th>Check</th>
                <th>Rejects if...</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>1</strong></td>
                <td>Vehicle status</td>
                <td>Vehicle is Under Maintenance, Out of Service, or Retired</td>
            </tr>
            <tr>
                <td><strong>2</strong></td>
                <td>Driver employment</td>
                <td>Driver is not Active (On Leave, Inactive)</td>
            </tr>
            <tr>
                <td><strong>3</strong></td>
                <td>Driver license</td>
                <td>License expiry date has passed</td>
            </tr>
            <tr>
                <td><strong>4</strong></td>
                <td>Certification matrix</td>
                <td>Driver lacks any certification required for the vehicle's category,
                    or the certification has expired</td>
            </tr>
            <tr>
                <td><strong>5</strong></td>
                <td>Close old assignment</td>
                <td>(always succeeds — may close 0 or 1 existing assignment)</td>
            </tr>
            <tr>
                <td><strong>6</strong></td>
                <td>Insert + update</td>
                <td>Create new assignment row, set vehicle status to Active</td>
            </tr>
        </tbody>
    </table>
    <p style="margin-top:12px; color:#757575; font-size:0.85rem;">
        All 6 steps run inside a single database transaction. If any step fails,
        all changes are rolled back — no partial assignment is ever left in the database.
    </p>
</div>

<?php require BASE_PATH . '/includes/footer.php'; ?>