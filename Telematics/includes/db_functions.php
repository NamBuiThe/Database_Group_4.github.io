<?php
require_once 'config.php';

/**
 * Execute a query and return results
 */
function executeQuery($sql, $params = [], $fetchAll = true) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    if ($fetchAll) {
        return $stmt->fetchAll();
    } else {
        return $stmt->fetch();
    }
}

/**
 * Execute a transaction (multiple queries as one unit)
 */
function executeTransaction($queries) {
    $pdo = getDBConnection();
    try {
        $pdo->beginTransaction();
        
        foreach ($queries as $query) {
            $stmt = $pdo->prepare($query['sql']);
            $stmt->execute($query['params']);
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Get the last inserted ID
 */
function getLastInsertId() {
    $pdo = getDBConnection();
    return $pdo->lastInsertId();
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Format date for display
 */
function formatDate($date) {
    return date('Y-m-d H:i', strtotime($date));
}

/**
 * Display success/error messages
 */
function displayMessage($type, $message) {
    $classes = [
        'success' => 'alert-success',
        'error' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info'
    ];
    
    $class = isset($classes[$type]) ? $classes[$type] : 'alert-info';
    return "<div class='alert {$class} alert-dismissible fade show' role='alert'>
                {$message}
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
}
?>