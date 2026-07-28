<?php
// Use Person 1's auth system
require_once __DIR__ . '/../includes/auth.php';
require_role('Workshop Staff', 'Admin');
require_once __DIR__ . '/../includes/db_functions.php';

// Page variables
$pageTitle = 'Log Telematics Event';
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get and sanitize form data
        $vehicleId = sanitizeInput($_POST['vehicle_id']);
        $eventType = sanitizeInput($_POST['event_type']);
        $severity = sanitizeInput($_POST['severity']);
        $description = sanitizeInput($_POST['description']);
        $location = sanitizeInput($_POST['location']);
        $timestamp = sanitizeInput($_POST['timestamp']);
        
        // Validate inputs
        if (empty($vehicleId) || empty($eventType) || empty($severity) || empty($description)) {
            throw new Exception('Please fill in all required fields.');
        }
        
        // Check if vehicle exists
        $vehicleCheck = executeQuery(
            "SELECT vehicle_id, status FROM Vehicles WHERE vehicle_id = ?",
            [$vehicleId],
            false
        );
        
        if (!$vehicleCheck) {
            throw new Exception('Vehicle not found.');
        }
        
        // Prepare for transaction
        $queries = [];
        
        // 1. Insert the telematics event
        $queries[] = [
            'sql' => "INSERT INTO Telematics_Events 
                      (vehicle_id, event_type, severity, description, location, timestamp) 
                      VALUES (?, ?, ?, ?, ?, ?)",
            'params' => [$vehicleId, $eventType, $severity, $description, $location, $timestamp]
        ];
        
        // 2. If severity is High or Critical, create a safety review entry
        if (in_array($severity, ['High', 'Critical'])) {
            $queries[] = [
                'sql' => "INSERT INTO Safety_Reviews 
                          (event_id, status, created_at, severity) 
                          VALUES (?, ?, NOW(), ?)",
                'params' => [null, 'Pending Review', $severity]
            ];
            
            // 3. If severity is Critical, temporarily suspend the vehicle
            if ($severity === 'Critical') {
                $queries[] = [
                    'sql' => "UPDATE Vehicles 
                              SET status = 'Suspended', 
                                  suspension_reason = ?,
                                  suspension_date = NOW()
                              WHERE vehicle_id = ?",
                    'params' => ['Critical event logged - ' . $eventType, $vehicleId]
                ];
            }
        }
        
        // Execute the transaction
        executeTransaction($queries);
        
        // Get the event ID for the safety review
        if (in_array($severity, ['High', 'Critical'])) {
            $eventId = getLastInsertId();
            // Update the safety review with the event ID
            $updateReview = executeQuery(
                "UPDATE Safety_Reviews SET event_id = ? WHERE event_id IS NULL ORDER BY review_id DESC LIMIT 1",
                [$eventId]
            );
        }
        
        $message = 'Event logged successfully!';
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get list of vehicles for dropdown
$vehicles = executeQuery(
    "SELECT vehicle_id, registration_number, model, status 
     FROM Vehicles 
     ORDER BY registration_number"
);

// Get recent events for display
$recentEvents = executeQuery(
    "SELECT te.*, v.registration_number 
     FROM Telematics_Events te
     JOIN Vehicles v ON te.vehicle_id = v.vehicle_id
     ORDER BY te.timestamp DESC 
     LIMIT 20"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - <?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <div class="container-fluid">
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
            <div class="container-fluid">
                <a class="navbar-brand" href="<?php echo base_url(); ?>/index.php">SmartFleet</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link active" href="log_event.php">Log Event</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="driver_score.php">Driver Scores</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo base_url(); ?>/reports/expired_certifications.php">Expired Certifications</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo base_url(); ?>/reports/repeated_faults.php">Repeated Faults</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo base_url(); ?>/reports/parts_cost_by_model.php">Parts Cost by Model</a>
                        </li>
                    </ul>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <span class="nav-link">Welcome, <?php echo $_SESSION['username'] ?? 'User'; ?></span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo base_url(); ?>/logout.php">Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Log New Telematics Event</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <?php echo displayMessage($messageType, $message); ?>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="vehicle_id" class="form-label">Vehicle *</label>
                                <select class="form-select" id="vehicle_id" name="vehicle_id" required>
                                    <option value="">Select Vehicle</option>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <option value="<?php echo $vehicle['vehicle_id']; ?>">
                                            <?php echo $vehicle['registration_number']; ?> 
                                            (<?php echo $vehicle['model']; ?>) 
                                            - <?php echo $vehicle['status']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="event_type" class="form-label">Event Type *</label>
                                <select class="form-select" id="event_type" name="event_type" required>
                                    <option value="">Select Event Type</option>
                                    <option value="Hard Braking">Hard Braking</option>
                                    <option value="Rapid Acceleration">Rapid Acceleration</option>
                                    <option value="Speeding">Speeding</option>
                                    <option value="Lane Departure">Lane Departure</option>
                                    <option value="Collision Warning">Collision Warning</option>
                                    <option value="Driver Distraction">Driver Distraction</option>
                                    <option value="Fatigue Detection">Fatigue Detection</option>
                                    <option value="Vehicle Malfunction">Vehicle Malfunction</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="severity" class="form-label">Severity *</label>
                                <select class="form-select" id="severity" name="severity" required>
                                    <option value="">Select Severity</option>
                                    <option value="Low">Low</option>
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                    <option value="Critical">Critical</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description *</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" placeholder="e.g., Highway 401, Toronto">
                            </div>
                            
                            <div class="mb-3">
                                <label for="timestamp" class="form-label">Event Time</label>
                                <input type="datetime-local" class="form-control" id="timestamp" name="timestamp">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Log Event</button>
                            <button type="reset" class="btn btn-secondary">Reset</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Events</h3>
                    </div>
                    <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                        <?php if (count($recentEvents) > 0): ?>
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Vehicle</th>
                                        <th>Type</th>
                                        <th>Severity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentEvents as $event): ?>
                                        <tr>
                                            <td><?php echo formatDate($event['timestamp']); ?></td>
                                            <td><?php echo $event['registration_number']; ?></td>
                                            <td><?php echo $event['event_type']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $event['severity'] === 'Critical' ? 'danger' : 
                                                        ($event['severity'] === 'High' ? 'warning' : 
                                                        ($event['severity'] === 'Medium' ? 'info' : 'success')); 
                                                ?>">
                                                    <?php echo $event['severity']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No events logged yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>