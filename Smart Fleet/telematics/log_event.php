<?php
require_once dirname(__DIR__) . '/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_role(ROLE_ADMIN, ROLE_FLEET_SAFETY);

$pageTitle = 'Log Telematics Event';
$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $vehicleId = (int)$_POST['vehicle_id'];
        $driverId = (int)$_POST['driver_id'];
        $penaltyId = (int)$_POST['penalty_id'];
        $odometer = (int)$_POST['odometer'];
        
        if (!$vehicleId || !$driverId || !$penaltyId) {
            throw new Exception('Please fill in all required fields.');
        }

        // Get vehicle depot_id
        $stmt = $pdo->prepare("SELECT depot_id FROM Vehicle WHERE id = ?");
        $stmt->execute([$vehicleId]);
        $vehicle = $stmt->fetch();
        if (!$vehicle) throw new Exception('Vehicle not found.');
        $depotId = $vehicle['depot_id'];

        $pdo->beginTransaction();

        // Insert telematics event
        $stmt = $pdo->prepare("
            INSERT INTO Telematics_Event_Log (vehicle_id, driver_id, depot_id, penalty_id, event_timestamp, odometer) 
            VALUES (?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([$vehicleId, $driverId, $depotId, $penaltyId, $odometer]);
        $eventId = $pdo->lastInsertId();

        // Check severity for Safety Review
        $stmt = $pdo->prepare("SELECT s.severity_type FROM Event_Penalties ep JOIN Severity s ON ep.severity_id = s.id WHERE ep.id = ?");
        $stmt->execute([$penaltyId]);
        $severity = $stmt->fetchColumn();

        if (in_array($severity, ['High', 'Critical'])) {
            $stmt = $pdo->prepare("
                INSERT INTO Safety_Event_Reviews (event_id, staff_comments, recommendation, review_date) 
                VALUES (?, 'Pending review', 'Pending', CURDATE())
            ");
            $stmt->execute([$eventId]);
        }

        $pdo->commit();
        $message = 'Event logged successfully!';
        $msgType = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Error: ' . $e->getMessage();
        $msgType = 'error';
    }
}

$vehicles = $pdo->query("SELECT id, registration_number FROM Vehicle ORDER BY registration_number")->fetchAll();
$drivers = $pdo->query("SELECT id, full_name FROM Driver ORDER BY full_name")->fetchAll();
$penalties = $pdo->query("SELECT ep.id, ep.event_type, s.severity_type FROM Event_Penalties ep JOIN Severity s ON ep.severity_id = s.id ORDER BY ep.event_type")->fetchAll();
$recentEvents = $pdo->query("
    SELECT tel.id, tel.event_timestamp, v.registration_number, ep.event_type, s.severity_type 
    FROM Telematics_Event_Log tel
    JOIN Vehicle v ON tel.vehicle_id = v.id
    JOIN Event_Penalties ep ON tel.penalty_id = ep.id
    JOIN Severity s ON ep.severity_id = s.id
    ORDER BY tel.event_timestamp DESC LIMIT 10
")->fetchAll();

require BASE_PATH . '/includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="card">
    <h1>Log New Telematics Event</h1>
    <form method="POST" action="">
        <div class="form-group">
            <label>Vehicle *</label>
            <select name="vehicle_id" required>
                <option value="">Select Vehicle</option>
                <?php foreach ($vehicles as $v): ?>
                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['registration_number']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Driver *</label>
            <select name="driver_id" required>
                <option value="">Select Driver</option>
                <?php foreach ($drivers as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Event & Severity *</label>
            <select name="penalty_id" required>
                <option value="">Select Event Type</option>
                <?php foreach ($penalties as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['event_type'] . ' (' . $p['severity_type'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Odometer Reading</label>
            <input type="number" name="odometer" required min="0">
        </div>
        <button type="submit" class="btn btn-primary">Log Event</button>
    </form>
</div>

<div class="card">
    <h2>Recent Events</h2>
    <table class="data-table">
        <thead>
            <tr><th>Time</th><th>Vehicle</th><th>Event Type</th><th>Severity</th></tr>
        </thead>
        <tbody>
            <?php foreach ($recentEvents as $ev): ?>
                <tr>
                    <td><?= date('Y-m-d H:i', strtotime($ev['event_timestamp'])) ?></td>
                    <td><?= htmlspecialchars($ev['registration_number']) ?></td>
                    <td><?= htmlspecialchars($ev['event_type']) ?></td>
                    <td><?= htmlspecialchars($ev['severity_type']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require BASE_PATH . '/includes/footer.php'; ?>