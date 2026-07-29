<?php
require_once dirname(__DIR__) . '/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_role(ROLE_ADMIN, ROLE_FLEET_SAFETY);
require_once BASE_PATH . '/includes/header.php';

$pageTitle = 'Expired Certifications Report';

$sql = "
    SELECT 
        c.certification_name,
        d.full_name,
        dc.expiry_date,
        DATEDIFF(dc.expiry_date, CURDATE()) as days_until_expiry,
        CASE 
            WHEN dc.expiry_date < CURDATE() THEN 'Expired'
            WHEN DATEDIFF(dc.expiry_date, CURDATE()) <= 30 THEN 'Expiring Soon'
            ELSE 'Valid'
        END as expiry_status
    FROM Certifications c
    JOIN Driver_Certifications dc ON c.id = dc.certification_id
    JOIN Driver d ON dc.driver_id = d.id
    WHERE dc.expiry_date IS NOT NULL
";

if (!empty($_GET['threshold'])) {
    $sql .= " AND DATEDIFF(dc.expiry_date, CURDATE()) <= ?";
    $params[] = (int)$_GET['threshold'];
} else {
    // Default to showing expired or expiring in 30 days
    $sql .= " AND DATEDIFF(dc.expiry_date, CURDATE()) <= 30";
    $params = [];
}

$sql .= " ORDER BY dc.expiry_date ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$certs = $stmt->fetchAll();
?>

<div class="card">
    <h1>Driver Certifications Report</h1>
    <p class="subtitle">Check for expired or soon-to-expire driver certifications</p>

    <form method="GET" action="" style="display:flex; gap:12px; margin-bottom:20px; align-items:flex-end;">
        <div class="form-group" style="flex:1; margin-bottom:0;">
            <label>Expiry Threshold (Days)</label>
            <input type="number" name="threshold" value="<?= htmlspecialchars($_GET['threshold'] ?? '30') ?>" min="1" max="365">
        </div>
        <button type="submit" class="btn btn-primary">Apply Filter</button>
        <a href="expired_certifications.php" class="btn btn-secondary">Reset</a>
    </form>

    <table class="data-table">
        <thead>
            <tr>
                <th>Driver</th>
                <th>Certification</th>
                <th>Expires</th>
                <th>Days Left</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($certs)): ?>
                <?php foreach ($certs as $cert): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($cert['full_name']) ?></strong></td>
                        <td><?= htmlspecialchars($cert['certification_name']) ?></td>
                        <td><?= htmlspecialchars($cert['expiry_date']) ?></td>
                        <td>
                            <?php 
                            $days = $cert['days_until_expiry'];
                            if ($days < 0) {
                                echo '<span style="color:#c62828;">' . abs($days) . ' days overdue</span>';
                            } else {
                                echo $days . ' days';
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            if ($cert['expiry_status'] === 'Expired') {
                                echo '<span class="badge badge-outservice">Expired</span>';
                            } else {
                                echo '<span class="badge badge-maintenance">' . htmlspecialchars($cert['expiry_status']) . '</span>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" class="empty-state">No certifications found in this range.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require BASE_PATH . '/includes/footer.php'; ?>