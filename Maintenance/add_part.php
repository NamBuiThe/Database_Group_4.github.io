<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_functions.php';

require_role('Workshop Staff', 'Admin');

$pageTitle = 'Add Part to Maintenance Activity';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activityId = (int) sanitizeInput($_POST['activity_id'] ?? '');
    $partId = (int) sanitizeInput($_POST['part_id'] ?? '');
    $quantityUsed = (int) sanitizeInput($_POST['quantity_used'] ?? '0');
    $unitPriceCharged = (float) sanitizeInput($_POST['unit_price_charged'] ?? '0');

    try {
        if (!$activityId || !$partId || $quantityUsed <= 0) {
            throw new Exception('Please select activity, part and enter a valid quantity.');
        }

        // Verify activity exists and belongs to an open job
        $activity = executeQuery(
            'SELECT ma.id, ma.job_id, mj.vehicle_id
             FROM Maintenance_Activities ma
             JOIN Maintenance_Jobs mj ON ma.job_id = mj.id
             WHERE ma.id = ? AND mj.date_closed IS NULL',
            [$activityId],
            false
        );
        if (!$activity) {
            throw new Exception('Maintenance activity not found or job already closed.');
        }

        // Verify part exists
        $part = executeQuery(
            'SELECT part_id, description, standard_unit_price FROM Parts WHERE part_id = ?',
            [$partId],
            false
        );
        if (!$part) {
            throw new Exception('Part not found.');
        }

        // Check duplicate entry
        $existing = executeQuery(
            'SELECT id FROM Activity_Parts WHERE activity_id = ? AND part_id = ?',
            [$activityId, $partId],
            false
        );
        if ($existing) {
            throw new Exception('This part has already been added to this activity.');
        }

        // Insert part
        $queries = [
            [
                'sql'    => 'INSERT INTO Activity_Parts (activity_id, part_id, quantity_used, unit_price_charged) VALUES (?, ?, ?, ?)',
                'params' => [$activityId, $partId, $quantityUsed, $unitPriceCharged],
            ],
        ];
        executeTransaction($queries);

        $message = 'Part ' . htmlspecialchars($part['description']) . ' added successfully (Qty: ' . $quantityUsed . ', Unit Price: $' . number_format($unitPriceCharged, 2) . ').';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Error: ' . htmlspecialchars($e->getMessage());
        $messageType = 'error';
    }
}

// Fetch active activities and parts
$activities = executeQuery(
    'SELECT ma.id, ma.activity_type, mj.id as job_id, v.registration_number
     FROM Maintenance_Activities ma
     JOIN Maintenance_Jobs mj ON ma.job_id = mj.id
     JOIN Vehicle v ON mj.vehicle_id = v.id
     WHERE mj.date_closed IS NULL
     ORDER BY mj.date_opened DESC'
);

$parts = executeQuery(
    'SELECT part_id, part_number, description, standard_unit_price
     FROM Parts
     ORDER BY description'
);

include __DIR__ . '/../includes/header.php';
?>
<div class="container" style="max-width: 700px; margin: 20px auto;">
    <h1>Add Part to Maintenance Activity</h1>

    <?php if (!empty($message)): ?>
        <div style="padding: 15px; margin: 20px 0; border-radius: 5px; <?php echo $messageType === 'success' ? 'background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="post" style="border: 1px solid #ccc; padding: 20px; border-radius: 5px;">
        <div style="margin-bottom: 20px;">
            <label for="activity_id" style="display: block; font-weight: bold; margin-bottom: 8px;">Select Maintenance Activity:</label>
            <select name="activity_id" id="activity_id" required style="width: 100%; padding: 8px; box-sizing: border-box;">
                <option value="">-- Choose an activity --</option>
                <?php foreach ($activities as $activity): ?>
                    <option value="<?php echo htmlspecialchars($activity['id']); ?>">
                        <?php echo htmlspecialchars('Job #' . $activity['job_id'] . ' - ' . $activity['registration_number'] . ' - ' . $activity['activity_type']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div style="background-color: #e7f3ff; padding: 10px; border-radius: 5px; margin-top: 10px; font-size: 14px;">
                Select an active maintenance activity to add a part to.
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="part_id" style="display: block; font-weight: bold; margin-bottom: 8px;">Select Part:</label>
            <select name="part_id" id="part_id" required style="width: 100%; padding: 8px; box-sizing: border-box;">
                <option value="">-- Choose a part --</option>
                <?php foreach ($parts as $part): ?>
                    <option value="<?php echo htmlspecialchars($part['part_id']); ?>">
                        <?php echo htmlspecialchars($part['part_number'] . ' - ' . $part['description'] . ' (Std: $' . number_format($part['standard_unit_price'], 2) . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 15px; margin-bottom: 20px;">
            <div style="flex: 1;">
                <label for="quantity_used" style="display: block; font-weight: bold; margin-bottom: 8px;">Quantity Used:</label>
                <input type="number" name="quantity_used" id="quantity_used" min="1" value="1" required style="width: 100%; padding: 8px; box-sizing: border-box;">
            </div>
            <div style="flex: 1;">
                <label for="unit_price_charged" style="display: block; font-weight: bold; margin-bottom: 8px;">Unit Price Charged ($):</label>
                <input type="number" name="unit_price_charged" id="unit_price_charged" step="0.01" min="0" value="0.00" required style="width: 100%; padding: 8px; box-sizing: border-box;">
            </div>
        </div>

        <button type="submit" style="padding: 10px 20px; background-color: #008CBA; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">Add Part</button>
    </form>

    <a href="<?php echo base_url(); ?>/index.php" style="display: block; margin-top: 20px; color: #007bff; text-decoration: none;">← Back to Dashboard</a>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
