<?php
require_once dirname(__DIR__) . '/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_role(ROLE_ADMIN, ROLE_WORKSHOP);

$pageTitle = 'Open Maintenance Job';
$message = '';
$msgType = '';

// Fetch activity types and required certifications for the form
$activityTypes = [
    'Routine Inspection', 'Preventative Servicing', 'Diagnostic Testing',
    'Emergency Repair', 'Component Replacement', 'EV Battery / Electrical Repair',
    'Refrigeration System Repair', 'Heavy Vehicle Repair'
];

// Map activity types to the required certification ID from your Certifications table
$certMap = [
    'Routine Inspection' => 6,
    'Preventative Servicing' => 6,
    'Diagnostic Testing' => 6,
    'Emergency Repair' => 6,
    'Component Replacement' => 6,
    'EV Battery / Electrical Repair' => 7,
    'Refrigeration System Repair' => 8,
    'Heavy Vehicle Repair' => 9
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }

    $vehicleId  = (int)$_POST['vehicle_id'];
    $workshopId = (int)$_POST['workshop_id'];
    $activityType = $_POST['activity_type'] ?? '';
    $diagnosticResult = $_POST['diagnostic_result'] ?? null;

    try {
        if (!$vehicleId || !$workshopId || !$activityType) {
            throw new Exception('Please select vehicle, workshop, and activity type.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, status, registration_number FROM Vehicle WHERE id = ?");
        $stmt->execute([$vehicleId]);
        $vehicle = $stmt->fetch();
        
        if (!$vehicle) throw new Exception('Vehicle not found.');
        if ($vehicle['status'] === 'Under Maintenance') throw new Exception('Vehicle is already Under Maintenance.');

        $stmt = $pdo->prepare("SELECT id FROM Workshops WHERE id = ?");
        $stmt->execute([$workshopId]);
        if (!$stmt->fetch()) throw new Exception('Workshop not found.');

        // 1. Insert the Job
        $stmt = $pdo->prepare("INSERT INTO Maintenance_Jobs (vehicle_id, workshop_id, date_opened) VALUES (?, ?, NOW())");
        $stmt->execute([$vehicleId, $workshopId]);
        $jobId = $pdo->lastInsertId();

        // 2. Insert the Activity for this Job
        $reqCertId = $certMap[$activityType] ?? 6;
        $stmt = $pdo->prepare("
            INSERT INTO Maintenance_Activities 
            (job_id, activity_type, required_certification_id, diagnostic_result, repeat_fault, warranty_indicator) 
            VALUES (?, ?, ?, ?, 0, 0)
        ");
        $stmt->execute([$jobId, $activityType, $reqCertId, $diagnosticResult]);

        // 3. Update Vehicle Status
        $stmt = $pdo->prepare("UPDATE Vehicle SET status = 'Under Maintenance' WHERE id = ?");
        $stmt->execute([$vehicleId]);

        $pdo->commit();
        $message = 'Maintenance job opened successfully for vehicle ' . htmlspecialchars($vehicle['registration_number']) . '.';
        $msgType = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Error: ' . $e->getMessage();
        $msgType = 'error';
    }
}

$vehicles = $pdo->query("
    SELECT v.id, v.registration_number, v.status, vm.model_name
    FROM Vehicle v
    JOIN Vehicle_Models vm ON v.model_id = vm.id
    ORDER BY v.registration_number
")->fetchAll();

$workshops = $pdo->query("
    SELECT w.id, w.workshop_name, d.depot_name
    FROM Workshops w
    JOIN Depot d ON w.depot_id = d.id
    ORDER BY w.workshop_name
")->fetchAll();

require BASE_PATH . '/includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="card">
    <h1>Open a New Maintenance Job</h1>
    <form method="post" action="">
        <?php $_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32)); ?>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="form-group">
            <label>Select Vehicle</label>
            <select name="vehicle_id" required>
                <option value="">-- Choose a vehicle --</option>
                <?php foreach ($vehicles as $v): ?>
                    <option value="<?= $v['id'] ?>">
                        <?= htmlspecialchars($v['registration_number'] . ' - ' . $v['model_name'] . ' (' . $v['status'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Select Workshop</label>
            <select name="workshop_id" required>
                <option value="">-- Choose a workshop --</option>
                <?php foreach ($workshops as $w): ?>
                    <option value="<?= $w['id'] ?>">
                        <?= htmlspecialchars($w['workshop_name'] . ' (' . $w['depot_name'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Initial Activity Type</label>
            <select name="activity_type" required>
                <option value="">-- Choose activity type --</option>
                <?php foreach ($activityTypes as $type): ?>
                    <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="hint">An activity record will be created for this job so you can add parts to it immediately.</div>
        </div>
        <div class="form-group">
            <label>Initial Diagnostic Result (Optional)</label>
            <input type="text" name="diagnostic_result" placeholder="e.g., Check brake pads">
        </div>
        <button type="submit" class="btn btn-success">Open Job & Create Activity</button>
    </form>
</div>

<?php require BASE_PATH . '/includes/footer.php'; ?>
