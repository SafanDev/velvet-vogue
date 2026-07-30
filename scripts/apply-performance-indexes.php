<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../Config/db.php';

$database = (string) vv_env('DB_NAME', '');
if ($database === '') {
    fwrite(STDERR, "DB_NAME is not configured.\n");
    exit(1);
}

$targets = [
    'product' => [
        ['isActive', 'createdAt'],
        ['categoryID', 'isActive'],
        ['gender', 'isActive'],
    ],
    'productvariant' => [
        ['productID', 'isActive', 'size', 'color'],
    ],
    'productimage' => [
        ['productID', 'isPrimary', 'sortOrder', 'imageID'],
        ['productID', 'color', 'isPrimary'],
    ],
    'cart' => [
        ['userID'],
    ],
    'cartitem' => [
        ['cartID', 'variantID'],
    ],
    'wishlist' => [
        ['userID', 'productID'],
    ],
    'order' => [
        ['userID', 'orderDate'],
        ['orderStatus', 'orderDate'],
    ],
    'orderitem' => [
        ['orderID'],
        ['variantID'],
    ],
    'review' => [
        ['productID', 'isApproved', 'createdAt'],
        ['userID', 'productID'],
    ],
    'coupon' => [
        ['couponCode', 'isActive', 'startDate', 'endDate'],
    ],
    'inquiry' => [
        ['inquiryStatus', 'createdAt'],
    ],
];

$columnStmt = $pdo->prepare('
    SELECT COLUMN_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
');
$indexStmt = $pdo->prepare('
    SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
    ORDER BY INDEX_NAME, SEQ_IN_INDEX
');

$added = 0;
$skipped = 0;

foreach ($targets as $table => $indexDefinitions) {
    $columnStmt->execute([$database, $table]);
    $availableColumns = array_fill_keys($columnStmt->fetchAll(PDO::FETCH_COLUMN), true);
    if ($availableColumns === []) {
        echo "[SKIP] {$table}: table is not present.\n";
        continue;
    }

    $indexStmt->execute([$database, $table]);
    $existing = [];
    foreach ($indexStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[(string) $row['INDEX_NAME']][] = (string) $row['COLUMN_NAME'];
    }

    foreach ($indexDefinitions as $columns) {
        $missingColumns = array_values(array_filter($columns, static fn (string $column): bool => !isset($availableColumns[$column])));
        if ($missingColumns !== []) {
            echo '[SKIP] ' . $table . ': missing column(s) ' . implode(', ', $missingColumns) . ".\n";
            continue;
        }

        $covered = false;
        foreach ($existing as $existingColumns) {
            if (array_slice($existingColumns, 0, count($columns)) === $columns) {
                $covered = true;
                break;
            }
        }

        if ($covered) {
            echo '[OK]   ' . $table . ' (' . implode(', ', $columns) . ") is already indexed.\n";
            $skipped++;
            continue;
        }

        $indexName = 'idx_vv_' . substr(hash('sha256', $table . '|' . implode('|', $columns)), 0, 12);
        $quotedColumns = implode(', ', array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', $columns));
        $quotedTable = '`' . str_replace('`', '``', $table) . '`';
        $quotedIndex = '`' . $indexName . '`';

        try {
            $pdo->exec("ALTER TABLE {$quotedTable} ADD INDEX {$quotedIndex} ({$quotedColumns})");
            echo '[ADD]  ' . $table . ' (' . implode(', ', $columns) . ").\n";
            $existing[$indexName] = $columns;
            $added++;
        } catch (PDOException $exception) {
            fwrite(STDERR, '[FAIL] ' . $table . ' (' . implode(', ', $columns) . '): ' . $exception->getMessage() . "\n");
            exit(1);
        }
    }
}

echo "\nPerformance index pass complete: {$added} added, {$skipped} already covered.\n";
