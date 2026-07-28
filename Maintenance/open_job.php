<?php name=maintenance/open_job.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_functions.php';

require_role('Workshop Staff', 'Admin');

$pageTitle = 'Open Maintenance Job';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicleId  = (int) sanitizeInput($_POST['vehicle_id'] ?? '');
    $workshopId = (int) sanitizeInput($_POST['workshop_id'] ?? '');

    try {
        if (!$vehicleId || !$workshopId) {
            throw new Exception('Please select both a vehicle and a workshop.');
        }

        // Verify vehicle exists and is not already under maintenance
        $vehicle = executeQuery(
            'SELECT id, status, registration_number FROM Vehicle WHERE id = ?',
            [$vehicleId],
            false
        );
        if (!$vehicle) {
            throw new Exception('Vehicle not found.');
        }
        if (strcasecmp($vehicle['status'], 'Under Maintenance') === 0) {
            throw new Exception('Vehicle is already Under Maintenance.');
        }

        // Verify workshop exists
        $workshop = executeQuery(
            'SELECT id, workshop_name FROM Workshops WHERE id = ?',
            [$workshopId],
            false
        );
        if (!$workshop) {
            throw new Exception('Workshop not found.');
        }

        // Create job and update vehicle status atomically
        $queries = [
            [
                'sql'    => 'INSERT INTO Maintenance_Jobs (vehicle_id, workshop_id, date_opened) VALUES (?, ?, NOW())',
                'params' => [$vehicleId, $workshopId],
            ],
            [
                'sql'    => "UPDATE Vehicle SET status = 'Under Maintenance' WHERE id = ?",
                'params' => [$vehicleId],
            ],
        ];

        executeTransaction($queries);

        $message = 'Maintenance job opened successfully for vehicle ' . htmlspecialchars($vehicle['registration_number']) . '.';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Error: ' . htmlspecialchars($e->getMessage());
        $messageType = 'error';
    }
}

// Fetch vehicles and workshops for selectors
$vehicles = executeQuery(
    'SELECT v.id, v.registration_number, v.status, vm.model_name
     FROM Vehicle v
     JOIN Vehicle_Models vm ON v.model_id = vm.id
     ORDER BY v.registration_number'
);

$workshops = executeQuery(
    'SELECT w.id, w.workshop_name, d.depot_name
     FROM Workshops w
     JOIN Depot d ON w.depot_id = d.id
     ORDER BY w.workshop_name'
);

include __DIR__ . '/../includes/header.php';
?>
<div class="container" style="max-width: 600px; margin: 20px auto;">
    <h1>Open a New Maintenance Job</h1>

    <?php if (!empty($message)): ?>
        <div style="padding: 15px; margin: 20px 0; border-radius: 5px; <?php echo $messageType === 'success' ? 'background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="post" style="border: 1px solid #ccc; padding: 20px; border-radius: 5px;">
        <div style="margin-bottom: 20px;">
            <label for="vehicle_id" style="display: block; font-weight: bold; margin-bottom: 8px;">Select Vehicle:</label>
            <select name="vehicle_id" id="vehicle_id" required style="width: 100%; padding: 8px; box-sizing: border-box;">
                <option value="">-- Choose a vehicle --</option>
                <?php foreach ($vehicles as $vehicle): ?>
                    <option value="<?php echo htmlspecialchars($vehicle['id']); ?>">
                        <?php echo htmlspecialchars($vehicle['registration_number'] . ' - ' . $vehicle['model_name'] . ' (' . $vehicle['status'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="workshop_id" style="display: block; font-weight: bold; margin-bottom: 8px;">Select Workshop:</label>
            <select name="workshop_id" id="workshop_id" required style="width: 100%; padding: 8px; box-sizing: border-box;">
                <option value="">-- Choose a workshop --</option>
                <?php foreach ($workshops as $workshop): ?>
                    <option value="<?php echo htmlspecialchars($workshop['id']); ?>">
                        <?php echo htmlspecialchars($workshop['workshop_name'] . ' (' . $workshop['depot_name'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" style="padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">Open Job</button>
    </form>

    <a href="<?php echo base_url(); ?>/index.php" style="display: block; margin-top: 20px; color: #007bff; text-decoration: none;">← Back to Dashboard</a>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
