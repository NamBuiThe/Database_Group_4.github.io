<?php
/**
 * assignments/assign.php — UC-A1: Assign a driver to a vehicle.
 *
 * Features:
 * - CSRF token protection (security update)
 * - Start date is locked to today (readonly field)
 * - End date is still optional (with Clear button)
 * - Drivers who already have an open assignment are excluded
 * - Depot isolation (non-admins only see their depot)
 * - Sequential selection (select vehicle first, driver list filters)
 * - Required certifications display
 */

require_once dirname(__DIR__) . '/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_fleet_safety();

$depotId  = current_depot_id();
$isAdmin  = is_admin();
$message  = '';
$msgType  = '';
$today    = date('Y-m-d');

// ═══════════════════════════════════════════════════════════════
//  POST HANDLER — The Transaction
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── CSRF Token Validation (security update) ──
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }

    $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
    $driverId  = (int) ($_POST['driver_id']  ?? 0);
    $startDate = $today;  // Always today — no longer user-selectable
    $endDate   = $_POST['end_date'] ?? '';  // empty = ongoing

    $errors = [];
    if ($vehicleId <= 0) $errors[] = 'Please select a vehicle.';
    if ($driverId  <= 0) $errors[] = 'Please select a driver.';

    // Validate dates
    if ($endDate !== '' && $endDate < $startDate) {
        $errors[] = 'End date cannot be before start date.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // STEP 1: Check vehicle status & depot
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

            if (!$vehicle) throw new Exception('Vehicle not found.');
            if (!$isAdmin && (int)$vehicle['depot_id'] !== $depotId) {
                throw new Exception('You can only assign vehicles in your own depot.');
            }

            $blockedStatuses = ['Under Maintenance', 'Out of Service', 'Retired'];
            if (in_array($vehicle['status'], $blockedStatuses, true)) {
                throw new Exception("Vehicle {$vehicle['registration_number']} is currently '{$vehicle['status']}' and cannot be assigned.");
            }

            // STEP 2: Check driver eligibility
            $stmt = $pdo->prepare(
                'SELECT id, full_name, depot_id, license_expiry, employment_status, license_type
                 FROM Driver WHERE id = ?'
            );
            $stmt->execute([$driverId]);
            $driver = $stmt->fetch();

            if (!$driver) throw new Exception('Driver not found.');
            if (!$isAdmin && (int)$driver['depot_id'] !== $depotId) {
                throw new Exception('You can only assign drivers in your own depot.');
            }
            if ($driver['employment_status'] !== 'Active') {
                throw new Exception("Driver {$driver['full_name']} is currently '{$driver['employment_status']}' and cannot be assigned.");
            }
            if ($driver['license_expiry'] < $today) {
                throw new Exception("Driver {$driver['full_name']}'s license expired on {$driver['license_expiry']}. Cannot assign until renewed.");
            }

            // Check driver isn't already assigned to another vehicle
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM Vehicle_Assignments 
                 WHERE driver_id = ? AND end_date IS NULL'
            );
            $stmt->execute([$driverId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Driver {$driver['full_name']} is already assigned to another vehicle.");
            }

            // STEP 3: Check certification requirements
            $stmt = $pdo->prepare(
                'SELECT ccr.certification_id, c.certification_name
                 FROM Category_Certification_Requirements ccr
                 JOIN Certifications c ON ccr.certification_id = c.id
                 WHERE ccr.vehicle_category = ?
                   AND NOT EXISTS (
                       SELECT 1 FROM Driver_Certifications dc
                       WHERE dc.driver_id = ? AND dc.certification_id = ccr.certification_id AND dc.expiry_date >= CURDATE()
                   )'
            );
            $stmt->execute([$vehicle['vehicle_category'], $driverId]);
            $missingCerts = $stmt->fetchAll();

            if (!empty($missingCerts)) {
                $certNames = array_map(fn($c) => $c['certification_name'], $missingCerts);
                throw new Exception("Driver {$driver['full_name']} is missing required certification(s): " . implode(', ', $certNames) . ". Cannot assign.");
            }

            // STEP 4: Close any open assignment for this vehicle
            $stmt = $pdo->prepare('UPDATE Vehicle_Assignments SET end_date = ? WHERE vehicle_id = ? AND end_date IS NULL');
            $stmt->execute([$startDate, $vehicleId]);

            // STEP 5: Create new assignment (with optional end_date)
            if ($endDate === '') {
                $stmt = $pdo->prepare(
                    'INSERT INTO Vehicle_Assignments (vehicle_id, driver_id, start_date, end_date) VALUES (?, ?, ?, NULL)'
                );
                $stmt->execute([$vehicleId, $driverId, $startDate]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO Vehicle_Assignments (vehicle_id, driver_id, start_date, end_date) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$vehicleId, $driverId, $startDate, $endDate]);
            }

            // STEP 6: Update vehicle status to 'Active'
            $stmt = $pdo->prepare("UPDATE Vehicle SET status = 'Active' WHERE id = ?");
            $stmt->execute([$vehicleId]);

            $pdo->commit();

            $endDateMsg = $endDate ? " (until {$endDate})" : ' (ongoing)';
            $message = "✅ Driver <strong>{$driver['full_name']}</strong> assigned to vehicle <strong>{$vehicle['registration_number']}</strong> starting today{$endDateMsg}.";
            $msgType = 'success';

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = '❌ ' . htmlspecialchars($e->getMessage());
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

// 1. Available vehicles (not assigned, not in maintenance)
$vehicleSql = "
    SELECT v.id, v.registration_number, v.status,
           vm.model_name, vm.manufacturer, vm.vehicle_category,
           dep.depot_name
    FROM Vehicle v
    JOIN Vehicle_Models vm ON v.model_id = vm.id
    JOIN Depot dep ON v.depot_id = dep.id
    WHERE v.status NOT IN ('Under Maintenance', 'Out of Service', 'Retired')
      AND v.id NOT IN (SELECT vehicle_id FROM Vehicle_Assignments WHERE end_date IS NULL)
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

// 2. Eligible drivers (active, valid license, NOT currently assigned to any vehicle)
$driverSql = "
    SELECT id, full_name, license_type, license_expiry
    FROM Driver
    WHERE employment_status = 'Active' AND license_expiry >= CURDATE()
      AND id NOT IN (SELECT driver_id FROM Vehicle_Assignments WHERE end_date IS NULL)
";
$driverParams = [];
if (!$isAdmin) {
    $driverSql .= " AND depot_id = ?";
    $driverParams[] = $depotId;
}
$driverSql .= " ORDER BY full_name";
$stmt = $pdo->prepare($driverSql);
$stmt->execute($driverParams);
$drivers = $stmt->fetchAll();

// 3. Fetch all driver certifications
$certSql = "SELECT dc.driver_id, dc.certification_id FROM Driver_Certifications dc WHERE dc.expiry_date >= CURDATE()";
$certParams = [];
if (!$isAdmin) {
    $certSql .= " AND dc.driver_id IN (SELECT id FROM Driver WHERE depot_id = ?)";
    $certParams[] = $depotId;
}
$stmt = $pdo->prepare($certSql);
$stmt->execute($certParams);
$driverCerts = $stmt->fetchAll();

// 4. Fetch certification requirements per vehicle category
$categoryReqs = $pdo->query("SELECT vehicle_category, certification_id FROM Category_Certification_Requirements")->fetchAll();

// 5. Fetch certification names
$certNames = $pdo->query("SELECT id, certification_name FROM Certifications")->fetchAll(PDO::FETCH_KEY_PAIR);

// Generate CSRF token (security update)
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));

require BASE_PATH . '/includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($msgType) ?>"><?= $message ?></div>
<?php endif; ?>

<div class="card">
    <h1>Assign Driver to Vehicle</h1>
    <p class="subtitle">
        Select a vehicle first. Only qualified, available drivers will appear.
        <?php if (!$isAdmin): ?>
            Only vehicles and drivers from <strong><?= htmlspecialchars(current_depot_name()) ?></strong> are shown.
        <?php endif; ?>
    </p>

    <form method="post" action="">
        <!-- CSRF Token (security update) -->
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <!-- Step 1: Select Vehicle -->
        <div class="form-group">
            <label for="vehicle_id">🚚 Step 1: Select Vehicle</label>
            <select name="vehicle_id" id="vehicle_id" required>
                <option value="">— Select a vehicle —</option>
                <?php foreach ($vehicles as $v): ?>
                    <option value="<?= (int) $v['id'] ?>"
                            data-category="<?= htmlspecialchars($v['vehicle_category']) ?>"
                            <?= (isset($_POST['vehicle_id']) && (int)$_POST['vehicle_id'] === (int)$v['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($v['registration_number']) ?>
                        — <?= htmlspecialchars($v['manufacturer'] . ' ' . $v['model_name']) ?>
                        (<?= htmlspecialchars($v['vehicle_category']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Required Certs Display -->
        <div id="req-certs-box" style="background:#f5f7fa; padding:12px; border-radius:8px; margin-bottom:20px; display:none;">
            <strong>Required Certifications:</strong>
            <span id="req-certs-list" style="color:#1a73e8; font-weight:600;"></span>
        </div>

        <!-- Step 2: Select Driver -->
        <div class="form-group">
            <label for="driver_id">👤 Step 2: Select Qualified Driver</label>
            <select name="driver_id" id="driver_id" required disabled>
                <option value="">— Select a vehicle first —</option>
            </select>
            <div class="hint" id="driver-hint">Only available drivers with the required certifications will appear.</div>
        </div>

        <!-- Step 3: Dates -->
        <div style="display:flex; gap:15px;">
            <div class="form-group" style="flex:1;">
                <label for="start_date">📅 Start Date</label>
                <input type="date" name="start_date" id="start_date" value="<?= $today ?>" readonly>
                <div class="hint">Start date is always today.</div>
            </div>
            <div class="form-group" style="flex:1;">
                <label for="end_date">📅 End Date (optional — leave blank for ongoing)</label>
                <div style="display:flex; gap:8px;">
                    <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>" min="<?= $today ?>" style="flex:1;">
                    <button type="button" onclick="document.getElementById('end_date').value=''" class="btn btn-secondary btn-sm" style="white-space:nowrap;">Clear</button>
                </div>
                <div class="hint">Leave blank for an ongoing assignment. Click "Clear" if your browser auto-fills it.</div>
            </div>
        </div>

        <div style="display:flex; gap:12px; margin-top:24px;">
            <button type="submit" class="btn btn-primary">✅ Confirm Assignment</button>
            <a href="<?= base_url() ?>/assignments/list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
const categoryReqs = <?= json_encode($categoryReqs) ?>;
const driverCerts = <?= json_encode($driverCerts) ?>;
const certNames = <?= json_encode($certNames) ?>;
const allDrivers = <?= json_encode($drivers) ?>;

document.getElementById('vehicle_id').addEventListener('change', function() {
    const vehicleSelect = this;
    const driverSelect = document.getElementById('driver_id');
    const reqCertsBox = document.getElementById('req-certs-box');
    const reqCertsList = document.getElementById('req-certs-list');
    const driverHint = document.getElementById('driver-hint');

    const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];
    const category = selectedOption.getAttribute('data-category');

    driverSelect.innerHTML = '<option value="">— Select a driver —</option>';

    if (!vehicleSelect.value) {
        driverSelect.disabled = true;
        driverSelect.innerHTML = '<option value="">— Select a vehicle first —</option>';
        reqCertsBox.style.display = 'none';
        return;
    }

    const reqCerts = categoryReqs.filter(r => r.vehicle_category === category).map(r => r.certification_id);

    if (reqCerts.length > 0) {
        const names = reqCerts.map(id => certNames[id] || 'Unknown').join(', ');
        reqCertsList.textContent = names;
        reqCertsBox.style.display = 'block';
    } else {
        reqCertsList.textContent = 'None required';
        reqCertsBox.style.display = 'block';
    }

    const qualifiedDrivers = [];
    allDrivers.forEach(driver => {
        let isQualified = true;
        reqCerts.forEach(certId => {
            const hasCert = driverCerts.some(dc =>
                dc.driver_id == driver.id && dc.certification_id == certId
            );
            if (!hasCert) isQualified = false;
        });
        if (isQualified) qualifiedDrivers.push(driver);
    });

    if (qualifiedDrivers.length > 0) {
        driverSelect.disabled = false;
        qualifiedDrivers.forEach(driver => {
            const opt = document.createElement('option');
            opt.value = driver.id;
            opt.textContent = driver.full_name + ' — ' + driver.license_type;
            driverSelect.appendChild(opt);
        });
        driverHint.textContent = qualifiedDrivers.length + ' qualified driver(s) available.';
        driverHint.style.color = '#2e7d32';
    } else {
        driverSelect.disabled = true;
        driverSelect.innerHTML = '<option value="">No qualified drivers available</option>';
        driverHint.textContent = 'No available drivers hold the required certifications.';
        driverHint.style.color = '#c62828';
    }
});

window.addEventListener('DOMContentLoaded', function() {
    const vehicleSelect = document.getElementById('vehicle_id');
    if (vehicleSelect.value) vehicleSelect.dispatchEvent(new Event('change'));
});
</script>

<?php require BASE_PATH . '/includes/footer.php'; ?>