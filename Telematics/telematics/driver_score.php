<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('Workshop Staff', 'Admin');
require_once __DIR__ . '/../includes/db_functions.php';

$pageTitle = 'Driver Safety Scores';

// Get driver scores with filters
$filterDriver = isset($_GET['driver_id']) ? sanitizeInput($_GET['driver_id']) : '';
$filterDateFrom = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$filterDateTo = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Build the query with filters
$sql = "SELECT 
            d.driver_id,
            d.first_name,
            d.last_name,
            d.email,
            d.license_number,
            COUNT(te.event_id) as total_events,
            SUM(CASE WHEN te.severity = 'Critical' THEN 1 ELSE 0 END) as critical_events,
            SUM(CASE WHEN te.severity = 'High' THEN 1 ELSE 0 END) as high_events,
            SUM(CASE WHEN te.severity = 'Medium' THEN 1 ELSE 0 END) as medium_events,
            SUM(CASE WHEN te.severity = 'Low' THEN 1 ELSE 0 END) as low_events,
            AVG(CASE 
                WHEN te.severity = 'Critical' THEN 1
                WHEN te.severity = 'High' THEN 0.75
                WHEN te.severity = 'Medium' THEN 0.5
                WHEN te.severity = 'Low' THEN 0.25
                ELSE 0
            END) as severity_score,
            MAX(te.timestamp) as last_event_date,
            d.created_at as join_date
        FROM Drivers d
        LEFT JOIN Vehicles v ON d.driver_id = v.current_driver_id
        LEFT JOIN Telematics_Events te ON v.vehicle_id = te.vehicle_id
        WHERE 1=1";

$params = [];

if ($filterDriver) {
    $sql .= " AND d.driver_id = ?";
    $params[] = $filterDriver;
}

if ($filterDateFrom) {
    $sql .= " AND te.timestamp >= ?";
    $params[] = $filterDateFrom . ' 00:00:00';
}

if ($filterDateTo) {
    $sql .= " AND te.timestamp <= ?";
    $params[] = $filterDateTo . ' 23:59:59';
}

$sql .= " GROUP BY d.driver_id
          ORDER BY severity_score ASC, total_events DESC";

$driverScores = executeQuery($sql, $params);

// Get list of drivers for filter dropdown
$drivers = executeQuery(
    "SELECT driver_id, first_name, last_name FROM Drivers ORDER BY first_name"
);

// Get summary statistics
$summary = executeQuery(
    "SELECT 
        COUNT(DISTINCT d.driver_id) as total_drivers,
        AVG(CASE 
            WHEN te.severity = 'Critical' THEN 1
            WHEN te.severity = 'High' THEN 0.75
            WHEN te.severity = 'Medium' THEN 0.5
            WHEN te.severity = 'Low' THEN 0.25
            ELSE 0
        END) as avg_severity,
        SUM(CASE WHEN te.severity = 'Critical' THEN 1 ELSE 0 END) as total_critical
    FROM Drivers d
    LEFT JOIN Vehicles v ON d.driver_id = v.current_driver_id
    LEFT JOIN Telematics_Events te ON v.vehicle_id = te.vehicle_id",
    [],
    false
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - <?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .score-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }
        .score-excellent { background: #28a745; color: white; }
        .score-good { background: #17a2b8; color: white; }
        .score-average { background: #ffc107; color: black; }
        .score-poor { background: #fd7e14; color: white; }
        .score-critical { background: #dc3545; color: white; }
        .stat-card {
            border-left: 4px solid #0d6efd;
            padding: 10px 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
        }
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
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
                            <a class="nav-link" href="<?php echo base_url(); ?>/telematics/log_event.php">Log Event</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="driver_score.php">Driver Scores</a>
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

        <div class="row mb-4">
            <div class="col-md-12">
                <h2>Driver Safety Scores Dashboard</h2>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $summary['total_drivers'] ?? 0; ?></div>
                    <div class="stat-label">Total Active Drivers</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: #17a2b8;">
                    <div class="stat-number"><?php echo number_format($summary['avg_severity'] ?? 0, 2); ?></div>
                    <div class="stat-label">Average Severity Score (lower is better)</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: #dc3545;">
                    <div class="stat-number"><?php echo $summary['total_critical'] ?? 0; ?></div>
                    <div class="stat-label">Total Critical Events</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: #ffc107;">
                    <div class="stat-number"><?php echo count($driverScores); ?></div>
                    <div class="stat-label">Drivers with Events</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Filter Results</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="driver_id" class="form-label">Driver</label>
                        <select class="form-select" id="driver_id" name="driver_id">
                            <option value="">All Drivers</option>
                            <?php foreach ($drivers as $driver): ?>
                                <option value="<?php echo $driver['driver_id']; ?>" 
                                    <?php echo $filterDriver == $driver['driver_id'] ? 'selected' : ''; ?>>
                                    <?php echo $driver['first_name'] . ' ' . $driver['last_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" 
                               value="<?php echo $filterDateFrom; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" 
                               value="<?php echo $filterDateTo; ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="driver_score.php" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Driver Scores Table -->
        <div class="card">
            <div class="card-header">
                <h5>Driver Performance Scores</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="driverScoresTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Driver</th>
                                <th>License</th>
                                <th>Events</th>
                                <th>C</th>
                                <th>H</th>
                                <th>M</th>
                                <th>L</th>
                                <th>Severity Score</th>
                                <th>Rating</th>
                                <th>Last Event</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($driverScores) > 0): ?>
                                <?php foreach ($driverScores as $driver): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $driver['first_name'] . ' ' . $driver['last_name']; ?></strong>
                                        </td>
                                        <td><?php echo $driver['license_number']; ?></td>
                                        <td><?php echo $driver['total_events']; ?></td>
                                        <td class="text-danger"><?php echo $driver['critical_events']; ?></td>
                                        <td class="text-warning"><?php echo $driver['high_events']; ?></td>
                                        <td class="text-info"><?php echo $driver['medium_events']; ?></td>
                                        <td class="text-success"><?php echo $driver['low_events']; ?></td>
                                        <td>
                                            <?php 
                                            $score = number_format($driver['severity_score'] ?? 0, 2);
                                            echo $score;
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $score = $driver['severity_score'] ?? 0;
                                            if ($score == 0) {
                                                echo '<span class="score-badge score-excellent">Perfect</span>';
                                            } elseif ($score < 0.25) {
                                                echo '<span class="score-badge score-excellent">Excellent</span>';
                                            } elseif ($score < 0.50) {
                                                echo '<span class="score-badge score-good">Good</span>';
                                            } elseif ($score < 0.75) {
                                                echo '<span class="score-badge score-average">Average</span>';
                                            } elseif ($score < 1.0) {
                                                echo '<span class="score-badge score-poor">Poor</span>';
                                            } else {
                                                echo '<span class="score-badge score-critical">Critical</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo $driver['last_event_date'] ? formatDate($driver['last_event_date']) : 'No events'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center">No driver data found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#driverScoresTable').DataTable({
                pageLength: 25,
                order: [[6, 'asc']],
                columnDefs: [
                    { orderable: false, targets: [7] }
                ]
            });
        });
    </script>
</body>
</html>