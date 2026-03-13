<?php
// CLI utility for scheduled backups with retention and backup_runs registry.
require_once __DIR__ . '/../../app/Config/config.php';
require_once __DIR__ . '/../../app/Core/Database.php';

$db = (new Database())->getConnection();
$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

$timestamp = date('Ymd_His');
$fileName = 'scheduled_backup_' . $timestamp . '.sql';
$filePath = $backupDir . '/' . $fileName;

try {
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM);
    $sql = "-- Scheduled backup generated at " . date('Y-m-d H:i:s') . "\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $t) {
        $table = $t[0];
        $create = $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
        $createSql = $create['Create Table'] ?? array_values($create)[1] ?? null;
        if (!$createSql) continue;

        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql .= $createSql . ";\n\n";

        $rows = $db->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $cols = array_map(function($c){ return '`' . $c . '`'; }, array_keys($rows[0]));
            $sql .= "INSERT INTO `{$table}` (" . implode(',', $cols) . ") VALUES\n";
            $valLines = [];
            foreach ($rows as $row) {
                $vals = [];
                foreach ($row as $v) {
                    $vals[] = ($v === null) ? 'NULL' : $db->quote($v);
                }
                $valLines[] = '(' . implode(',', $vals) . ')';
            }
            $sql .= implode(",\n", $valLines) . ";\n\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    file_put_contents($filePath, $sql);

    $size = filesize($filePath);
    $checksum = hash_file('sha256', $filePath);
    $ins = $db->prepare("INSERT INTO backup_runs (file_name, file_size, checksum_sha256, run_status, notes) VALUES (:file_name, :file_size, :checksum, 'SUCCESS', 'Scheduled backup')");
    $ins->bindValue(':file_name', $fileName);
    $ins->bindValue(':file_size', (int)$size, PDO::PARAM_INT);
    $ins->bindValue(':checksum', $checksum);
    $ins->execute();

    // Retention: keep last 30 files
    $files = glob($backupDir . '/scheduled_backup_*.sql');
    rsort($files);
    $toDelete = array_slice($files, 30);
    foreach ($toDelete as $f) {
        @unlink($f);
    }

    echo "Backup created: {$fileName}\n";
} catch (Exception $e) {
    $ins = $db->prepare("INSERT INTO backup_runs (file_name, file_size, checksum_sha256, run_status, notes) VALUES (:file_name, 0, NULL, 'FAILED', :notes)");
    $ins->bindValue(':file_name', $fileName);
    $ins->bindValue(':notes', $e->getMessage());
    $ins->execute();

    fwrite(STDERR, "Backup failed: " . $e->getMessage() . "\n");
    exit(1);
}
