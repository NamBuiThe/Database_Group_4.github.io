<?php
require_once dirname(__DIR__) . '/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_role(ROLE_ADMIN, ROLE_WORKSHOP);
require_once BASE_PATH . '/includes/header.php';

$pageTitle = 'Parts Cost by Vehicle Model';

$sql = "
    SELECT 
        vm.model_name,
        COUNT(DISTINCT mj.vehicle_id) as vehicle_count,
        COUNT(DISTINCT mj.id) as job_count,
        SUM(ap.quantity_used) as total_parts_quantity,
        SUM(ap.quantity_used * ap.unit_price_charged) as total_part_cost
    FROM Activity_Parts ap
    JOIN Maintenance_Activities ma ON ap.activity_id = ma.id
    JOIN Maintenance_Jobs mj ON ma.job_id = mj.id
    JOIN Vehicle v ON mj.vehicle_id = v.id
    JOIN Vehicle_Models vm ON v.model_id = vm.id
    WHERE 1=1
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

$sql .= " GROUP BY vm.model_name ORDER BY total_part_cost DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$partsCost = $stmt->fetchAll();
?>

<div class="card">
    <h1>Parts Cost Analysis by Vehicle Model</h1>
    <p class="subtitle">Compare maintenance costs across vehicle models</p>

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
        <a href="parts_cost_by_model.php" class="btn btn-secondary">Reset</a>
    </form>

    <table class="data-table">
        <thead>
            <tr>
                <th>Model</th>
                <th>Vehicles</th>
                <th>Jobs</th>
                <th>Total Parts</th>
                <th>Total Cost</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($partsCost)): ?>
                <?php foreach ($partsCost as $model): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($model['model_name']) ?></strong></td>
                        <td><?= $model['vehicle_count'] ?></td>
                        <td><?= $model['job_count'] ?></td>
                        <td><?= $model['total_parts_quantity'] ?></td>
                        <td><?= number_format($model['total_part_cost'], 0) ?> VND</td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" class="empty-state">No data available.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require BASE_PATH . '/includes/footer.php'; ?>