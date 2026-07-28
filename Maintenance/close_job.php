<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_functions.php';

require_role('Workshop Staff', 'Admin');

$pageTitle = 'Close Maintenance Job';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jobId = (int) sanitizeInput($_POST['job_id'] ?? '');

    try {
        if (!$jobId) {
            throw new Exception('Please select a job to close.');
        }

        // 1. Verify job exists and is open
        $job = executeQuery(
            'SELECT mj.id, mj.vehicle_id, mj.date_opened, mj.date_closed, v.registration_number
             FROM Maintenance_Jobs mj
             JOIN Vehicle v ON mj.vehicle_id = v.id
             WHERE mj.id = ?',
            [$jobId],
            false
        );
        if (!$job) {
            throw new Exception('Maintenance job not found.');
        }
        if ($job['date_closed'] !== null) {
            throw new Exception('This job has already been closed.');
        }

        // 2. Calculate labour cost (may be null => 0)
        $labourResult = executeQuery(
            'SELECT COALESCE(SUM(am.labour_hours * sl.hourly_rate), 0) AS labour_cost
             FROM Activity_Mechanics am
             JOIN Mechanics m ON am.mechanic_id = m.id
             JOIN Skill_Levels sl ON m.skill_id = sl.id
             JOIN Maintenance_Activities ma ON am.activity_id = ma.id
             WHERE ma.job_id = ?',
            [$jobId],
            false
        );
        $labourCost = (float) ($labourResult['labour_cost'] ?? 0.0);

        // 3. Calculate down time in hours
        $dateOpened = new DateTime($job['date_opened']);
        $now = new DateTime();
        $interval = $now->diff($dateOpened);
        $downTimeHours = ($interval->days * 24) + $interval->h + ($interval->i / 60.0);
        $downTimeHours = round($downTimeHours, 2);

        // 4. Update job and vehicle atomically
        $queries = [
            [
                'sql' => 'UPDATE Maintenance_Jobs SET date_closed = NOW(), total_cost = ?, down_time_hours = ? WHERE id = ?',
                'params' => [$labourCost, $downTimeHours, $jobId]
            ],
            [
                'sql' => "UPDATE Vehicle SET status = 'Available' WHERE id = ?",
                'params' => [$job['vehicle_id']]
            ]
        ];

        executeTransaction($queries);

        $message = 'Maintenance job closed successfully. Total labour cost: $' . number_format($labourCost, 2) . ', Down time: ' . $downTimeHours . ' hours.';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Error: ' . htmlspecialchars($e->getMessage());
        $messageType = 'error';
    }
}

// Fetch open jobs for selection and listing
$openJobs = executeQuery(
    'SELECT mj.id, mj.vehicle_id, v.registration_number, vm.model_name, w.workshop_name, mj.date_opened
     FROM Maintenance_Jobs mj
     JOIN Vehicle v ON mj.vehicle_id = v.id
     JOIN Vehicle_Models vm ON v.model_id = vm.id
     JOIN Workshops w ON mj.workshop_id = w.id
     WHERE mj.date_closed IS NULL
     ORDER BY mj.date_opened DESC'
);

include __DIR__ . '/../includes/header.php';
?>
<div class="container" style="max-width: 700px; margin: 20px auto;">
    <h1>Close a Maintenance Job</h1>

    <?php if (!empty($message)): ?>
        <div style="padding: 15px; margin: 20px 0; border-radius: 5px; <?php echo $messageType === 'success' ? 'background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="post" style="border: 1px solid #ccc; padding: 20px; border-radius: 5px; margin-bottom: 30px;">
        <div style="margin-bottom: 20px;">
            <label for="job_id" style="display: block; font-weight: bold; margin-bottom: 8px;">Select Job to Close:</label>
            <select name="job_id" id="job_id" required style="width: 100%; padding: 8px; box-sizing: border-box;">
                <option value="">-- Choose a job --</option>
                <?php foreach ($openJobs as $job): ?>
                    <option value="<?php echo htmlspecialchars($job['id']); ?>">
                        <?php echo htmlspecialchars('Job #' . $job['id'] . ' - ' . $job['registration_number'] . ' (' . $job['model_name'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" style="padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">Close Job</button>
    </form>

    <h2>Open Maintenance Jobs</h2>
    <?php if (count($openJobs) > 0): ?>
        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
            <thead>
                <tr style="background-color: #f2f2f2;">
                    <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Job ID</th>
                    <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Vehicle</th>
                    <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Model</th>
                    <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Workshop</th>
                    <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Date Opened</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($openJobs as $job): ?>
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 12px;"><?php echo htmlspecialchars($job['id']); ?></td>
                        <td style="border: 1px solid #ddd; padding: 12px;"><?php echo htmlspecialchars($job['registration_number']); ?></td>
                        <td style="border: 1px solid #ddd; padding: 12px;"><?php echo htmlspecialchars($job['model_name']); ?></td>
                        <td style="border: 1px solid #ddd; padding: 12px;"><?php echo htmlspecialchars($job['workshop_name']); ?></td>
                        <td style="border: 1px solid #ddd; padding: 12px;"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($job['date_opened']))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No open maintenance jobs.</p>
    <?php endif; ?>

    <a href="<?php echo base_url(); ?>/index.php" style="display: block; margin-top: 20px; color: #007bff; text-decoration: none;">← Back to Dashboard</a>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
