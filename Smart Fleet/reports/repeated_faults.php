<?php
require_once dirname(__DIR__) . '/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_role(ROLE_ADMIN, ROLE_WORKSHOP);
require_once BASE_PATH . '/includes/header.php';

$pageTitle = 'Repeated Faults Report';

// Because our schema tracks repeat faults via the repeat_fault boolean in Maintenance_Activities
$sql = "
    SELECT 
        ma.activity_type,
        ma.diagnostic_result,
        COUNT(ma.id) as occurrence_count,
        GROUP_CONCAT(DISTINCT v.registration_number SEPARATOR ', ') as affected_vehicles,
        MIN(mj.date_opened) as first_occurrence,
        MAX(mj.date_opened) as last_occurrence
    FROM Maintenance_Activities ma
    JOIN Maintenance_Jobs mj ON ma.job_id = mj.id
    JOIN Vehicle v ON mj.vehicle_id = v.id
    WHERE ma.repeat_fault = 1
";

$params = [];
if (!empty($_GET['date_from'])) {
    $sql .= " AND mj.date_opened >= ?";
    $params[] = $_GET['date_from'] . ' 00:00:00';
}
if (!empty($_GET['date_to'])) {
    $sql .= " AND mj.date_opened <= ?";
    $params[] = $_GET['date_to'] . ' 23:59:59';
}

$sql .= " GROUP BY ma.activity_type, ma.diagnostic_result ORDER BY occurrence_count DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$repeatedFaults = $stmt->fetchAll();
?>

<div class="card">
    <h1>Repeated Faults Report</h1>
    <p class="subtitle">Identify vehicles and components with recurring failures</p>

    <form method="GET" action="" style="display:flex; gap:12px; margin-bottom:20px; align-items:flex-end;">
        <div class="form-group" style="flex:1; margin-bottom:0;">
            <label>Date From</label>
            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
        </div>
        <div class="form-group" style="flex:1; margin-bottom:0;">
            <label>Date To</label>
            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-primary">Apply Filters</button>
        <a href="repeated_faults.php" class="btn btn-secondary">Reset</a>
    </form>

    <table class="data-table">
        <thead>
            <tr>
                <th>Activity Type</th>
                <th>Diagnostic Result</th>
                <th>Occurrences</th>
                <th>Affected Vehicles</th>
                <th>First Occurrence</th>
                <th>Last Occurrence</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($repeatedFaults)): ?>
                <?php foreach ($repeatedFaults as $fault): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($fault['activity_type']) ?></strong></td>
                        <td><?= htmlspecialchars($fault['diagnostic_result'] ?? 'N/A') ?></td>
                        <td><span class="badge badge-maintenance"><?= $fault['occurrence_count'] ?></span></td>
                        <td><?= htmlspecialchars($fault['affected_vehicles']) ?></td>
                        <td><?= htmlspecialchars($fault['first_occurrence']) ?></td>
                        <td><?= htmlspecialchars($fault['last_occurrence']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="empty-state">No repeated faults found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require BASE_PATH . '/includes/footer.php'; ?>