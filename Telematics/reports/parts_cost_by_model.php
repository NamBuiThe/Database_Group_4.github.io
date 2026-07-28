<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('Workshop Staff', 'Admin');
require_once __DIR__ . '/../includes/db_functions.php';

$pageTitle = 'Parts Cost by Vehicle Model';

// Get filters
$dateFrom = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';
$sortBy = isset($_GET['sort_by']) ? sanitizeInput($_GET['sort_by']) : 'total_cost';
$sortOrder = isset($_GET['sort_order']) ? sanitizeInput($_GET['sort_order']) : 'DESC';

// Build the query
$sql = "SELECT 
            v.model,
            COUNT(DISTINCT v.vehicle_id) as vehicle_count,
            COUNT(DISTINCT mj.maintenance_id) as job_count,
            COUNT(DISTINCT p.part_id) as unique_parts_used,
            SUM(mp.quantity) as total_parts_quantity,
            SUM(mp.quantity * p.unit_price) as total_part_cost,
            AVG(mp.quantity * p.unit_price) as avg_cost_per_job,
            MIN(mp.quantity * p.unit_price) as min_job_cost,
            MAX(mp.quantity * p.unit_price) as max_job_cost,
            SUM(mp.quantity * p.unit_price) / COUNT(DISTINCT v.vehicle_id) as avg_cost_per_vehicle
        FROM Vehicles v
        JOIN Maintenance_Jobs mj ON v.vehicle_id = mj.vehicle_id
        JOIN Maintenance_Parts mp ON mj.maintenance_id = mp.maintenance_id
        JOIN Parts p ON mp.part_id = p.part_id
        WHERE 1=1";

$params = [];

if ($dateFrom) {
    $sql .= " AND mj.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if ($dateTo) {
    $sql .= " AND mj.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

$sql .= " GROUP BY v.model
          ORDER BY $sortBy $sortOrder";

$partsCostByModel = executeQuery($sql, $params);

// Get total summary
$totalSummary = executeQuery(
    "SELECT 
        SUM(mp.quantity * p.unit_price) as total_cost,
        SUM(mp.quantity) as total_parts,
        COUNT(DISTINCT p.part_id) as unique_parts
    FROM Maintenance_Parts mp
    JOIN Parts p ON mp.part_id = p.part_id
    JOIN Maintenance_Jobs mj ON mp.maintenance_id = mj.maintenance_id",
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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
        .cost-positive { color: #28a745; }
        .cost-negative { color: #dc3545; }
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
                            <a class="nav-link" href="repeated_faults.php">Repeated Faults</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="parts_cost_by_model.php">Parts Cost by Model</a>
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
                <h2>Parts Cost Analysis by Vehicle Model</h2>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number">$<?php echo number_format($totalSummary['total_cost'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Parts Cost</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="border-left-color: #17a2b8;">
                    <div class="stat-number"><?php echo number_format($totalSummary['total_parts'] ?? 0); ?></div>
                    <div class="stat-label">Total Parts Used</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="border-left-color: #ffc107;">
                    <div class="stat-number"><?php echo $totalSummary['unique_parts'] ?? 0; ?></div>
                    <div class="stat-label">Unique Parts Used</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Filter Report</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" 
                               value="<?php echo $dateFrom; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" 
                               value="<?php echo $dateTo; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="sort_by" class="form-label">Sort By</label>
                        <select class="form-select" id="sort_by" name="sort_by">
                            <option value="total_cost" <?php echo $sortBy == 'total_cost' ? 'selected' : ''; ?>>Total Cost</option>
                            <option value="avg_cost_per_job" <?php echo $sortBy == 'avg_cost_per_job' ? 'selected' : ''; ?>>Avg Cost per Job</option>
                            <option value="job_count" <?php echo $sortBy == 'job_count' ? 'selected' : ''; ?>>Number of Jobs</option>
                            <option value="vehicle_count" <?php echo $sortBy == 'vehicle_count' ? 'selected' : ''; ?>>Vehicle Count</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="parts_cost_by_model.php" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <!-- Chart -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Cost Distribution by Model</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="costChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Data Table -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Detailed Breakdown</h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Model</th>
                                    <th>Vehicles</th>
                                    <th>Jobs</th>
                                    <th>Total Cost</th>
                                    <th>Avg Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($partsCostByModel) > 0): ?>
                                    <?php foreach ($partsCostByModel as $model): ?>
                                        <tr>
                                            <td><strong><?php echo $model['model']; ?></strong></td>
                                            <td><?php echo $model['vehicle_count']; ?></td>
                                            <td><?php echo $model['job_count']; ?></td>
                                            <td class="cost-positive">$<?php echo number_format($model['total_part_cost'], 2); ?></td>
                                            <td>$<?php echo number_format($model['avg_cost_per_job'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No data available.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Table -->
        <div class="card mt-4">
            <div class="card-header">
                <h5>Full Details by Model</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="partsTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Model</th>
                                <th>Vehicles</th>
                                <th>Jobs</th>
                                <th>Unique Parts</th>
                                <th>Total Parts</th>
                                <th>Total Cost</th>
                                <th>Avg/Job</th>
                                <th>Avg/Vehicle</th>
                                <th>Min Job</th>
                                <th>Max Job</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($partsCostByModel) > 0): ?>
                                <?php foreach ($partsCostByModel as $model): ?>
                                    <tr>
                                        <td><strong><?php echo $model['model']; ?></strong></td>
                                        <td><?php echo $model['vehicle_count']; ?></td>
                                        <td><?php echo $model['job_count']; ?></td>
                                        <td><?php echo $model['unique_parts_used']; ?></td>
                                        <td><?php echo $model['total_parts_quantity']; ?></td>
                                        <td class="cost-positive">$<?php echo number_format($model['total_part_cost'], 2); ?></td>
                                        <td>$<?php echo number_format($model['avg_cost_per_job'], 2); ?></td>
                                        <td>$<?php echo number_format($model['avg_cost_per_vehicle'], 2); ?></td>
                                        <td>$<?php echo number_format($model['min_job_cost'], 2); ?></td>
                                        <td>$<?php echo number_format($model['max_job_cost'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center">No data available.</td>
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
            $('#partsTable').DataTable({
                pageLength: 25,
                order: [[5, 'desc']]
            });
        });

        // Chart
        const ctx = document.getElementById('costChart').getContext('2d');
        const chartData = <?php 
            $labels = array_column($partsCostByModel, 'model');
            $costs = array_column($partsCostByModel, 'total_part_cost');
            echo json_encode(['labels' => $labels, 'costs' => $costs]);
        ?>;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Total Parts Cost ($)',
                    data: chartData.costs,
                    backgroundColor: 'rgba(13, 110, 253, 0.7)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '$' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toFixed(0);
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>