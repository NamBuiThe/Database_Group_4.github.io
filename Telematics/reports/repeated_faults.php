<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('Workshop Staff', 'Admin');
require_once __DIR__ . '/../includes/db_functions.php';

$pageTitle = 'Repeated Faults Report';

// Get filters
$faultType = isset($_GET['fault_type']) ? sanitizeInput($_GET['fault_type']) : '';
$minOccurrences = isset($_GET['min_occurrences']) ? (int)$_GET['min_occurrences'] : 3;
$dateFrom = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Build query for repeated faults
$sql = "SELECT 
            f.fault_id,
            f.fault_code,
            f.description as fault_description,
            f.severity,
            COUNT(mf.maintenance_id) as occurrence_count,
            GROUP_CONCAT(DISTINCT v.registration_number ORDER BY v.registration_number SEPARATOR ', ') as affected_vehicles,
            MIN(mf.created_at) as first_occurrence,
            MAX(mf.created_at) as last_occurrence,
            (SELECT COUNT(DISTINCT v2.vehicle_id) 
             FROM Maintenance_Faults mf2
             JOIN Maintenance_Jobs mj2 ON mf2.maintenance_id = mj2.maintenance_id
             JOIN Vehicles v2 ON mj2.vehicle_id = v2.vehicle_id
             WHERE mf2.fault_id = f.fault_id) as total_vehicles_affected,
            AVG(CASE 
                WHEN mj2.status = 'Completed' THEN 
                    (SELECT AVG(days_to_complete) FROM (
                        SELECT DATEDIFF(mj3.completion_date, mj3.created_at) as days_to_complete
                        FROM Maintenance_Jobs mj3
                        WHERE mj3.maintenance_id = mf3.maintenance_id
                    ) sub
                END)
            END) as avg_fix_time
        FROM Faults f
        JOIN Maintenance_Faults mf ON f.fault_id = mf.fault_id
        JOIN Maintenance_Jobs mj ON mf.maintenance_id = mj.maintenance_id
        JOIN Vehicles v ON mj.vehicle_id = v.vehicle_id
        WHERE 1=1";

$params = [];

if ($faultType) {
    $sql .= " AND f.fault_id = ?";
    $params[] = $faultType;
}

if ($dateFrom) {
    $sql .= " AND mf.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if ($dateTo) {
    $sql .= " AND mf.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

$sql .= " GROUP BY f.fault_id
          HAVING COUNT(mf.maintenance_id) >= ?
          ORDER BY occurrence_count DESC, f.severity DESC";

$params[] = $minOccurrences;

$repeatedFaults = executeQuery($sql, $params);

// Get fault types for filter
$faultTypes = executeQuery(
    "SELECT fault_id, fault_code, description FROM Faults ORDER BY fault_code"
);

// Get summary statistics
$summary = executeQuery(
    "SELECT 
        COUNT(DISTINCT f.fault_id) as total_faults,
        SUM(CASE WHEN f.severity = 'Critical' THEN 1 ELSE 0 END) as critical_faults,
        AVG(fault_counts.occurrence_count) as avg_occurrences
    FROM (
        SELECT f.fault_id, f.severity, COUNT(mf.maintenance_id) as occurrence_count
        FROM Faults f
        JOIN Maintenance_Faults mf ON f.fault_id = mf.fault_id
        GROUP BY f.fault_id
        HAVING COUNT(mf.maintenance_id) >= 2
    ) fault_counts
    JOIN Faults f ON fault_counts.fault_id = f.fault_id",
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
        .severity-critical { color: #dc3545; font-weight: bold; }
        .severity-high { color: #fd7e14; font-weight: bold; }
        .severity-medium { color: #ffc107; }
        .severity-low { color: #17a2b8; }
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
                <a class="navbar-brand" href="#">SmartFleet</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" href="../telematics/log_event.php">Log Event</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../telematics/driver_score.php">Driver Scores</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="expired_certifications.php">Expired Certifications</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="repeated_faults.php">Repeated Faults</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="parts_cost_by_model.php">Parts Cost by Model</a>
                        </li>
                    </ul>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <span class="nav-link">Welcome, <?php echo $_SESSION['username'] ?? 'User'; ?></span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="row mb-4">
            <div class="col-md-12">
                <h2>Repeated Faults Report</h2>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $summary['total_faults'] ?? 0; ?></div>
                    <div class="stat-label">Faults with 2+ Occurrences</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: #dc3545;">
                    <div class="stat-number text-danger"><?php echo $summary['critical_faults'] ?? 0; ?></div>
                    <div class="stat-label">Critical Faults</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: #17a2b8;">
                    <div class="stat-number"><?php echo number_format($summary['avg_occurrences'] ?? 0, 1); ?></div>
                    <div class="stat-label">Average Occurrences per Fault</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: #ffc107;">
                    <div class="stat-number"><?php echo count($repeatedFaults); ?></div>
                    <div class="stat-label">Faults Meeting Threshold</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Filter Faults</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="fault_type" class="form-label">Fault Type</label>
                        <select class="form-select" id="fault_type" name="fault_type">
                            <option value="">All Faults</option>
                            <?php foreach ($faultTypes as $type): ?>
                                <option value="<?php echo $type['fault_id']; ?>" 
                                    <?php echo $faultType == $type['fault_id'] ? 'selected' : ''; ?>>
                                    <?php echo $type['fault_code']; ?> - <?php echo substr($type['description'], 0, 30); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="min_occurrences" class="form-label">Min Occurrences</label>
                        <input type="number" class="form-control" id="min_occurrences" name="min_occurrences" 
                               value="<?php echo $minOccurrences; ?>" min="2">
                    </div>
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" 
                               value="<?php echo $dateFrom; ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" 
                               value="<?php echo $dateTo; ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="repeated_faults.php" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Faults Table -->
        <div class="card">
            <div class="card-header">
                <h5>Repeated Faults</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="faultsTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Fault Code</th>
                                <th>Description</th>
                                <th>Severity</th>
                                <th>Occurrences</th>
                                <th>Vehicles Affected</th>
                                <th>First</th>
                                <th>Last</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($repeatedFaults) > 0): ?>
                                <?php foreach ($repeatedFaults as $fault): ?>
                                    <tr>
                                        <td><strong><?php echo $fault['fault_code']; ?></strong></td>
                                        <td><?php echo $fault['fault_description']; ?></td>
                                        <td>
                                            <span class="severity-<?php echo strtolower($fault['severity']); ?>">
                                                <?php echo $fault['severity']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $fault['occurrence_count'] >= 5 ? 'danger' : 
                                                    ($fault['occurrence_count'] >= 3 ? 'warning' : 'info'); 
                                            ?>">
                                                <?php echo $fault['occurrence_count']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $fault['affected_vehicles']; ?></td>
                                        <td><?php echo formatDate($fault['first_occurrence']); ?></td>
                                        <td><?php echo formatDate($fault['last_occurrence']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No repeated faults found.</td>
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
            $('#faultsTable').DataTable({
                pageLength: 25,
                order: [[3, 'desc']]
            });
        });
    </script>
</body>
</html>