<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('Workshop Staff', 'Admin');
require_once __DIR__ . '/../includes/db_functions.php';

$pageTitle = 'Expired Certifications Report';

// Get filters
$certType = isset($_GET['cert_type']) ? sanitizeInput($_GET['cert_type']) : '';
$expiryThreshold = isset($_GET['expiry_threshold']) ? (int)$_GET['expiry_threshold'] : 30;

// Build query
$sql = "SELECT 
            c.certification_id,
            c.name as certification_name,
            c.description,
            c.validity_period_months,
            d.driver_id,
            d.first_name,
            d.last_name,
            d.email,
            d.phone,
            dc.issue_date,
            dc.expiry_date,
            DATEDIFF(dc.expiry_date, CURDATE()) as days_until_expiry,
            CASE 
                WHEN dc.expiry_date < CURDATE() THEN 'Expired'
                WHEN DATEDIFF(dc.expiry_date, CURDATE()) <= 7 THEN 'Expiring Soon'
                WHEN DATEDIFF(dc.expiry_date, CURDATE()) <= 30 THEN 'Expiring This Month'
                ELSE 'Valid'
            END as expiry_status
        FROM Certifications c
        JOIN Driver_Certifications dc ON c.certification_id = dc.certification_id
        JOIN Drivers d ON dc.driver_id = d.driver_id
        WHERE dc.expiry_date IS NOT NULL";

$params = [];

if ($certType) {
    $sql .= " AND c.certification_id = ?";
    $params[] = $certType;
}

if ($expiryThreshold) {
    $sql .= " AND DATEDIFF(dc.expiry_date, CURDATE()) <= ?";
    $params[] = $expiryThreshold;
}

$sql .= " ORDER BY dc.expiry_date ASC";

$certifications = executeQuery($sql, $params);

// Get certification types for filter
$certTypes = executeQuery(
    "SELECT certification_id, name FROM Certifications ORDER BY name"
);

// Get summary statistics
$summary = executeQuery(
    "SELECT 
        COUNT(*) as total_certs,
        SUM(CASE WHEN expiry_date < CURDATE() THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN DATEDIFF(expiry_date, CURDATE()) <= 7 AND expiry_date >= CURDATE() THEN 1 ELSE 0 END) as expiring_soon,
        SUM(CASE WHEN DATEDIFF(expiry_date, CURDATE()) BETWEEN 8 AND 30 AND expiry_date >= CURDATE() THEN 1 ELSE 0 END) as expiring_month
    FROM Driver_Certifications
    WHERE expiry_date IS NOT NULL",
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
        .status-expired { background: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; }
        .status-soon { background: #ffc107; color: black; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; }
        .status-month { background: #17a2b8; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; }
        .status-valid { background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; }
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
                            <a class="nav-link active" href="expired_certifications.php">Expired Certifications</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="repeated_faults.php">Repeated Faults</a>
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
                <h2>Driver Certifications Report</h2>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $summary['total_certs'] ?? 0; ?></div>
                    <div class="stat-label">Total Certifications</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: #dc3545;">
                    <div class="stat-number text-danger"><?php echo $summary['expired'] ?? 0; ?></div>
                    <div class="stat-label">Expired</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: #ffc107;">
                    <div class="stat-number text-warning"><?php echo $summary['expiring_soon'] ?? 0; ?></div>
                    <div class="stat-label">Expiring Soon (7 days)</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: #17a2b8;">
                    <div class="stat-number text-info"><?php echo $summary['expiring_month'] ?? 0; ?></div>
                    <div class="stat-label">Expiring This Month</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Filter Certifications</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label for="cert_type" class="form-label">Certification Type</label>
                        <select class="form-select" id="cert_type" name="cert_type">
                            <option value="">All Certifications</option>
                            <?php foreach ($certTypes as $type): ?>
                                <option value="<?php echo $type['certification_id']; ?>" 
                                    <?php echo $certType == $type['certification_id'] ? 'selected' : ''; ?>>
                                    <?php echo $type['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="expiry_threshold" class="form-label">Expiry Threshold (Days)</label>
                        <input type="number" class="form-control" id="expiry_threshold" name="expiry_threshold" 
                               value="<?php echo $expiryThreshold; ?>" min="1" max="365">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="expired_certifications.php" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Certifications Table -->
        <div class="card">
            <div class="card-header">
                <h5>Driver Certifications</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="certTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Driver</th>
                                <th>Certification</th>
                                <th>Issued</th>
                                <th>Expires</th>
                                <th>Days Left</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($certifications) > 0): ?>
                                <?php foreach ($certifications as $cert): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $cert['first_name'] . ' ' . $cert['last_name']; ?></strong><br>
                                            <small><?php echo $cert['email']; ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo $cert['certification_name']; ?></strong><br>
                                            <small><?php echo $cert['description']; ?></small>
                                        </td>
                                        <td><?php echo formatDate($cert['issue_date']); ?></td>
                                        <td><?php echo formatDate($cert['expiry_date']); ?></td>
                                        <td>
                                            <?php 
                                            $days = $cert['days_until_expiry'];
                                            if ($days < 0) {
                                                echo '<span class="text-danger">' . abs($days) . ' days overdue</span>';
                                            } else {
                                                echo $days . ' days';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $status = $cert['expiry_status'];
                                            if ($status === 'Expired') {
                                                echo '<span class="status-expired">Expired</span>';
                                            } elseif ($status === 'Expiring Soon') {
                                                echo '<span class="status-soon">Expiring Soon</span>';
                                            } elseif ($status === 'Expiring This Month') {
                                                echo '<span class="status-month">Expiring This Month</span>';
                                            } else {
                                                echo '<span class="status-valid">Valid</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No certifications found.</td>
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
            $('#certTable').DataTable({
                pageLength: 25,
                order: [[3, 'asc']]
            });
        });
    </script>
</body>
</html>