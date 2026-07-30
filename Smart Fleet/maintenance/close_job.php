<?php
require_once dirname(__DIR__) . '/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_role(ROLE_ADMIN, ROLE_WORKSHOP);

$pageTitle = 'Close Maintenance Job';
$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }
    $jobId = (int)$_POST['job_id'];
    try {
        if (!$jobId) throw new Exception('Please select a job to close.');

        $pdo->beginTransaction();

        // Verify job exists and is open
        $stmt = $pdo->prepare("
            SELECT mj.id, mj.vehicle_id, mj.date_opened, mj.date_closed, v.registration_number
            FROM Maintenance_Jobs mj
            JOIN Vehicle v ON mj.vehicle_id = v.id
            WHERE mj.id = ?
        ");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch();
        
        if (!$job) throw new Exception('Maintenance job not found.');
        if ($job['date_closed'] !== null) throw new Exception('This job has already been closed.');

        // Calculate labour cost
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(am.labour_hours * sl.hourly_rate), 0) AS labour_cost
            FROM Activity_Mechanics am
            JOIN Mechanics m ON am.mechanic_id = m.id
            JOIN Skill_Levels sl ON m.skill_id = sl.id
            JOIN Maintenance_Activities ma ON am.activity_id = ma.id
            WHERE ma.job_id = ?
        ");
        $stmt->execute([$jobId]);
        $labourCost = (float) $stmt->fetchColumn();

        // Calculate down time
        $dateOpened = new DateTime($job['date_opened']);
        $now = new DateTime();
        $downTimeHours = ($now->getTimestamp() - $dateOpened->getTimestamp()) / 3600;
        $downTimeHours = round($downTimeHours, 2);

        $stmt = $pdo->prepare("UPDATE Maintenance_Jobs SET date_closed = NOW(), total_cost = ?, down_time_hours = ? WHERE id = ?");
        $stmt->execute([$labourCost, $downTimeHours, $jobId]);

        $stmt = $pdo->prepare("UPDATE Vehicle SET status = 'Available' WHERE id = ?");
        $stmt->execute([$job['vehicle_id']]);

        $pdo->commit();
        $message = 'Job closed successfully. Labour Cost: ' . number_format($labourCost, 0) . ' VND, Down time: ' . $downTimeHours . ' hours.';
        $msgType = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Error: ' . $e->getMessage();
        $msgType = 'error';
    }
}

$openJobs = $pdo->query("
    SELECT mj.id, v.registration_number, vm.model_name, w.workshop_name, mj.date_opened
    FROM Maintenance_Jobs mj
    JOIN Vehicle v ON mj.vehicle_id = v.id
    JOIN Vehicle_Models vm ON v.model_id = vm.id
    JOIN Workshops w ON mj.workshop_id = w.id
    WHERE mj.date_closed IS NULL
    ORDER BY mj.date_opened DESC
")->fetchAll();

require BASE_PATH . '/includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="card">
    <h1>Close a Maintenance Job</h1>
    <form method="post" action="">
        <?php $_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32)); ?>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="form-group">
            <label>Select Job to Close</label>
            <select name="job_id" required>
                <option value="">-- Choose a job --</option>
                <?php foreach ($openJobs as $job): ?>
                    <option value="<?= $job['id'] ?>">
                        <?= htmlspecialchars('Job #' . $job['id'] . ' - ' . $job['registration_number'] . ' (' . $job['model_name'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-success">Close Job</button>
    </form>
</div>

<div class="card">
    <h2>Open Maintenance Jobs</h2>
    <?php if (!empty($openJobs)): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Job ID</th>
                    <th>Vehicle</th>
                    <th>Model</th>
                    <th>Workshop</th>
                    <th>Date Opened</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($openJobs as $job): ?>
                    <tr>
                        <td><?= htmlspecialchars($job['id']) ?></td>
                        <td><?= htmlspecialchars($job['registration_number']) ?></td>
                        <td><?= htmlspecialchars($job['model_name']) ?></td>
                        <td><?= htmlspecialchars($job['workshop_name']) ?></td>
                        <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($job['date_opened']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty-state">No open maintenance jobs.</div>
    <?php endif; ?>
</div>

<?php require BASE_PATH . '/includes/footer.php'; ?>