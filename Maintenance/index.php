<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_functions.php';

require_login();

$pageTitle = 'Dashboard';

$vehicleCounts = executeQuery(
    'SELECT status, COUNT(*) AS total FROM Vehicle GROUP BY status'
);

$openJobs = executeQuery(
    'SELECT mj.id, v.registration_number, w.workshop_name, mj.date_opened
     FROM Maintenance_Jobs mj
     JOIN Vehicle v ON mj.vehicle_id = v.id
     JOIN Workshops w ON mj.workshop_id = w.id
     WHERE mj.date_closed IS NULL
     ORDER BY mj.date_opened DESC'
);

include __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width: 900px; margin: 20px auto; padding: 0 20px;">
    <h1>SmartFleet Dashboard</h1>

    <h2>Vehicle Status</h2>
    <?php if (count($vehicleCounts) > 0): ?>
        <ul>
            <?php foreach ($vehicleCounts as $row): ?>
                <li><?php echo htmlspecialchars($row['status']); ?>: <?php echo (int) $row['total']; ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No vehicles found.</p>
    <?php endif; ?>

    <h2>Open Maintenance Jobs</h2>
    <?php if (count($openJobs) > 0): ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background-color: #f2f2f2;">
                    <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Job ID</th>
                    <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Vehicle</th>
                    <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Workshop</th>
                    <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Date Opened</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($openJobs as $job): ?>
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 10px;"><?php echo (int) $job['id']; ?></td>
                        <td style="border: 1px solid #ddd; padding: 10px;"><?php echo htmlspecialchars($job['registration_number']); ?></td>
                        <td style="border: 1px solid #ddd; padding: 10px;"><?php echo htmlspecialchars($job['workshop_name']); ?></td>
                        <td style="border: 1px solid #ddd; padding: 10px;"><?php echo htmlspecialchars(formatDate($job['date_opened'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No open maintenance jobs.</p>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
