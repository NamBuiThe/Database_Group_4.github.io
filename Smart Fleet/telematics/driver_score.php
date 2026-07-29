<?php
require_once dirname(__DIR__) . '/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_role(ROLE_ADMIN, ROLE_FLEET_SAFETY);
require_once BASE_PATH . '/includes/header.php';

$pageTitle = 'Driver Safety Scores';

// Build the query with filters using our actual schema
$sql = "
    SELECT 
        d.id as driver_id,
        d.full_name,
        d.license_type,
        COUNT(tel.id) as total_events,
        SUM(CASE WHEN s.severity_type = 'Critical' THEN 1 ELSE 0 END) as critical_events,
        SUM(CASE WHEN s.severity_type = 'High' THEN 1 ELSE 0 END) as high_events,
        SUM(CASE WHEN s.severity_type = 'Medium' THEN 1 ELSE 0 END) as medium_events,
        SUM(CASE WHEN s.severity_type = 'Low' THEN 1 ELSE 0 END) as low_events,
        MAX(tel.event_timestamp) as last_event_date
    FROM Driver d
    LEFT JOIN Telematics_Event_Log tel ON d.id = tel.driver_id
    LEFT JOIN Event_Penalties ep ON tel.penalty_id = ep.id
    LEFT JOIN Severity s ON ep.severity_id = s.id
    WHERE 1=1
";

$params = [];
if (!empty($_GET['driver_id'])) {
    $sql .= " AND d.id = ?";
    $params[] = (int)$_GET['driver_id'];
}
if (!empty($_GET['date_from'])) {
    $sql .= " AND tel.event_timestamp >= ?";
    $params[] = $_GET['date_from'] . ' 00:00:00';
}
if (!empty($_GET['date_to'])) {
    $sql .= " AND tel.event_timestamp <= ?";
    $params[] = $_GET['date_to'] . ' 23:59:59';
}

$sql .= " GROUP BY d.id ORDER BY total_events DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$driverScores = $stmt->fetchAll();

$drivers = $pdo->query("SELECT id, full_name FROM Driver ORDER BY full_name")->fetchAll();
?>

<div class="card">
    <h1>Driver Safety Scores Dashboard</h1>
    <p class="subtitle">View driver behaviour event statistics</p>

    <!-- Filters -->
    <form method="GET" action="" style="display:flex; gap:12px; margin-bottom:20px; align-items:flex-end;">
        <div class="form-group" style="flex:1; margin-bottom:0;">
            <label>Driver</label>
            <select name="driver_id" class="form-control">
                <option value="">All Drivers</option>
                <?php foreach ($drivers as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= ($_GET['driver_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex:1; margin-bottom:0;">
            <label>Date From</label>
            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
        </div>
        <div class="form-group" style="flex:1; margin-bottom:0;">
            <label>Date To</label>
            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-primary">Apply Filters</button>
        <a href="driver_score.php" class="btn btn-secondary">Reset</a>
    </form>

    <!-- Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th>Driver</th>
                <th>License</th>
                <th>Total Events</th>
                <th>Critical</th>
                <th>High</th>
                <th>Medium</th>
                <th>Low</th>
                <th>Last Event</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($driverScores)): ?>
                <?php foreach ($driverScores as $driver): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($driver['full_name']) ?></strong></td>
                        <td><?= htmlspecialchars($driver['license_type']) ?></td>
                        <td><?= $driver['total_events'] ?></td>
                        <td><span class="badge badge-outservice"><?= $driver['critical_events'] ?></span></td>
                        <td><span class="badge badge-maintenance"><?= $driver['high_events'] ?></span></td>
                        <td><span class="badge badge-inspection"><?= $driver['medium_events'] ?></span></td>
                        <td><span class="badge badge-available"><?= $driver['low_events'] ?></span></td>
                        <td><?= $driver['last_event_date'] ? date('Y-m-d H:i', strtotime($driver['last_event_date'])) : 'No events' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8" class="empty-state">No driver data found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require BASE_PATH . '/includes/footer.php'; ?>