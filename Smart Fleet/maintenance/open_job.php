<?php
require_once dirname(__DIR__) . '/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_role(ROLE_ADMIN, ROLE_WORKSHOP);

$pageTitle = 'Open Maintenance Job';
$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicleId  = (int)$_POST['vehicle_id'];
    $workshopId = (int)$_POST['workshop_id'];

    try {
        if (!$vehicleId || !$workshopId) {
            throw new Exception('Please select both a vehicle and a workshop.');
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

        $stmt = $pdo->prepare("INSERT INTO Maintenance_Jobs (vehicle_id, workshop_id, date_opened) VALUES (?, ?, NOW())");
        $stmt->execute([$vehicleId, $workshopId]);

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
        <button type="submit" class="btn btn-success">Open Job</button>
    </form>
</div>

<?php require BASE_PATH . '/includes/footer.php'; ?>