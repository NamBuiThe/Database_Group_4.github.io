<?php
require_once dirname(__DIR__) . '/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_role(ROLE_ADMIN, ROLE_WORKSHOP);

$pageTitle = 'Add Part to Maintenance Activity';
$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }

    if (($_POST['action'] ?? '') === 'clear_history') {
        try {
            $pdo->beginTransaction();
            $pdo->exec("DELETE FROM Activity_Parts");
            $pdo->commit();
            $message = 'Parts history cleared successfully.';
            $msgType = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Error: ' . $e->getMessage();
            $msgType = 'error';
        }
    } else {
        $activityId = (int)$_POST['activity_id'];
        $partId = (int)$_POST['part_id'];
        $quantityUsed = (int)$_POST['quantity_used'];
        $unitPriceCharged = (float)$_POST['unit_price_charged'];

        try {
            if (!$activityId || !$partId || $quantityUsed <= 0) {
                throw new Exception('Please select activity, part and enter a valid quantity.');
            }

            $pdo->beginTransaction();

            // Verify activity exists and belongs to an open job
            $stmt = $pdo->prepare("
                SELECT ma.id 
                FROM Maintenance_Activities ma
                JOIN Maintenance_Jobs mj ON ma.job_id = mj.id
                WHERE ma.id = ? AND mj.date_closed IS NULL
            ");
            $stmt->execute([$activityId]);
            if (!$stmt->fetch()) {
                throw new Exception('Maintenance activity not found or job already closed.');
            }

            // Verify part exists
            $stmt = $pdo->prepare("SELECT part_id, description FROM Parts WHERE part_id = ?");
            $stmt->execute([$partId]);
            $part = $stmt->fetch();
            if (!$part) throw new Exception('Part not found.');

            // Insert part
            $stmt = $pdo->prepare("
                INSERT INTO Activity_Parts (activity_id, part_id, quantity_used, unit_price_charged) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$activityId, $partId, $quantityUsed, $unitPriceCharged]);

            $pdo->commit();
            $message = 'Part added successfully!';
            $msgType = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Error: ' . $e->getMessage();
            $msgType = 'error';
        }
    }
}

$activities = $pdo->query("
    SELECT ma.id, ma.activity_type, mj.id as job_id, v.registration_number
    FROM Maintenance_Activities ma
    JOIN Maintenance_Jobs mj ON ma.job_id = mj.id
    JOIN Vehicle v ON mj.vehicle_id = v.id
    WHERE mj.date_closed IS NULL
    ORDER BY mj.date_opened DESC
")->fetchAll();

$parts = $pdo->query("SELECT part_id, part_number, description, standard_unit_price FROM Parts ORDER BY description")->fetchAll();

// Recent parts history (most recently added first)
$partsHistory = $pdo->query("
    SELECT ap.id, ap.quantity_used, ap.unit_price_charged,
           p.part_number, p.description AS part_description,
           ma.activity_type, mj.id AS job_id, v.registration_number
    FROM Activity_Parts ap
    JOIN Parts p ON ap.part_id = p.part_id
    JOIN Maintenance_Activities ma ON ap.activity_id = ma.id
    JOIN Maintenance_Jobs mj ON ma.job_id = mj.id
    JOIN Vehicle v ON mj.vehicle_id = v.id
    ORDER BY ap.id DESC
    LIMIT 20
")->fetchAll();

require BASE_PATH . '/includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="card">
    <h1>Add Part to Maintenance Activity</h1>
    <form method="post" action="">
        <?php $_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32)); ?>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="form-group">
            <label>Select Maintenance Activity</label>
            <select name="activity_id" id="activity_id" required>
                <option value="">-- Choose an activity --</option>
                <?php foreach ($activities as $act): ?>
                    <option value="<?= $act['id'] ?>">
                        <?= htmlspecialchars('Job #' . $act['job_id'] . ' - ' . $act['registration_number'] . ' - ' . $act['activity_type']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Select Part</label>
            <select name="part_id" id="part_id" required>
                <option value="">-- Choose a part --</option>
                <?php foreach ($parts as $p): ?>
                    <option value="<?= $p['part_id'] ?>" data-price="<?= htmlspecialchars($p['standard_unit_price']) ?>">
                        <?= htmlspecialchars($p['part_number'] . ' - ' . $p['description'] . ' (Std: ' . number_format($p['standard_unit_price'], 0) . ' VND)') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex; gap:15px;">
            <div class="form-group" style="flex:1;">
                <label>Quantity Used</label>
                <input type="number" name="quantity_used" id="quantity_used" min="1" value="1" required>
            </div>
            <div class="form-group" style="flex:1;">
                <label>Unit Price Charged (VND)</label>
                <input type="number" name="unit_price_charged" id="unit_price_charged" step="0.01" min="0" value="0.00" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Add Part</button>
    </form>

    <script>
        function updatePrice() {
            var partSelect = document.getElementById('part_id');
            var selected = partSelect.options[partSelect.selectedIndex];
            var basePrice = parseFloat(selected.getAttribute('data-price'));
            var quantity = parseFloat(document.getElementById('quantity_used').value);

            if (!isNaN(basePrice) && !isNaN(quantity) && quantity > 0) {
                document.getElementById('unit_price_charged').value = (basePrice * quantity).toFixed(2);
            }
        }

        document.getElementById('part_id').addEventListener('change', function () {
            document.getElementById('quantity_used').value = 1;
            updatePrice();
        });
        document.getElementById('quantity_used').addEventListener('input', updatePrice);
    </script>
</div>

<div class="card">
    <h2>Recently Added Parts</h2>

    <form method="post" action="" onsubmit="return confirm('This will permanently delete ALL parts history. Are you sure?');" style="margin-bottom: 15px;">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="action" value="clear_history">
        <button type="submit" class="btn" style="background-color:#dc3545; color:#fff;">Clear History</button>
    </form>

    <?php if (count($partsHistory) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Job</th>
                    <th>Vehicle</th>
                    <th>Activity</th>
                    <th>Part</th>
                    <th style="text-align:right;">Qty</th>
                    <th style="text-align:right;">Unit Price (VND)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($partsHistory as $row): ?>
                    <tr>
                        <td>#<?= (int)$row['job_id'] ?></td>
                        <td><?= htmlspecialchars($row['registration_number']) ?></td>
                        <td><?= htmlspecialchars($row['activity_type']) ?></td>
                        <td><?= htmlspecialchars($row['part_number'] . ' - ' . $row['part_description']) ?></td>
                        <td style="text-align:right;"><?= (int)$row['quantity_used'] ?></td>
                        <td style="text-align:right;"><?= number_format($row['unit_price_charged'], 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty-state">No parts have been added yet.</div>
    <?php endif; ?>
</div>

<?php require BASE_PATH . '/includes/footer.php'; ?>
