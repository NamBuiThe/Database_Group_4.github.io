<?php
require_once __DIR__ . '/config.php';

/**
 * executeQuery: prepares, executes and fetches results.
 * $fetchAll = true returns fetchAll(); false returns fetch().
 */
function executeQuery(string $sql, array $params = [], bool $fetchAll = true)
{
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($params));
    return $fetchAll ? $stmt->fetchAll() : $stmt->fetch();
}

/**
 * executeTransaction: accepts array of ['sql'=>..., 'params'=>[...] ] and runs as a single transaction.
 * Returns an array: ['success'=>true, 'lastInsertId'=> id|null] or throws Exception on failure.
 */
function executeTransaction(array $queries): array
{
    $pdo = getDBConnection();
    try {
        $pdo->beginTransaction();
        $lastId = null;
        foreach ($queries as $q) {
            $stmt = $pdo->prepare($q['sql']);
            $stmt->execute(array_values($q['params'] ?? []));
            $lastId = $pdo->lastInsertId() ?: $lastId;
        }
        $pdo->commit();
        return ['success' => true, 'lastInsertId' => $lastId];
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function sanitizeInput($v) {
    return htmlspecialchars(trim((string)$v), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatDate($d) {
    if (empty($d)) return '';
    return date('Y-m-d H:i', strtotime($d));
}
