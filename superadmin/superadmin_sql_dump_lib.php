<?php

/**
 * Stream a full MySQL logical backup (schema + data) for the active database.
 * Uses PDO only (no mysqldump binary).
 */
function superadmin_stream_mysql_dump(PDO $pdo): void
{
    $pdo->exec('SET NAMES utf8mb4');
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($dbName === false || $dbName === null || $dbName === '') {
        echo "-- Error: no database selected.\n";
        return;
    }

    echo "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n";
    echo "SET NAMES utf8mb4;\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n";
    echo "SET UNIQUE_CHECKS=0;\n";
    echo '-- Database: `' . str_replace('`', '``', (string) $dbName) . "`\n\n";

    $tables = [];
    try {
        $ts = $pdo->query("SHOW FULL TABLES FROM `" . str_replace('`', '``', (string) $dbName) . "` WHERE Table_type = 'BASE TABLE'");
        while ($row = $ts->fetch(PDO::FETCH_NUM)) {
            if (!empty($row[0])) {
                $tables[] = (string) $row[0];
            }
        }
    } catch (Throwable $e) {
        $ts = $pdo->query('SHOW TABLES');
        while ($row = $ts->fetch(PDO::FETCH_NUM)) {
            if (!empty($row[0])) {
                $tables[] = (string) $row[0];
            }
        }
    }

    foreach ($tables as $table) {
        $t = str_replace('`', '``', $table);
        echo "\n-- ----------------------------\n";
        echo "-- Table structure for `{$t}`\n";
        echo "-- ----------------------------\n";

        $createStmt = $pdo->query('SHOW CREATE TABLE `' . $t . '`');
        $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
        if (!$createRow || empty($createRow['Create Table'])) {
            continue;
        }
        echo 'DROP TABLE IF EXISTS `' . $t . "`;\n";
        echo $createRow['Create Table'] . ";\n\n";

        $dataStmt = $pdo->query('SELECT * FROM `' . $t . '`');
        $cols = null;
        while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
            if ($cols === null) {
                $cols = array_keys($row);
            }
            $vals = [];
            foreach ($row as $v) {
                if ($v === null) {
                    $vals[] = 'NULL';
                } else {
                    $q = $pdo->quote((string) $v);
                    $vals[] = $q !== false ? $q : "''";
                }
            }
            $colList = [];
            foreach ($cols as $c) {
                $colList[] = '`' . str_replace('`', '``', $c) . '`';
            }
            echo 'INSERT INTO `' . $t . '` (' . implode(',', $colList) . ') VALUES (' . implode(',', $vals) . ");\n";
        }
    }

    echo "\nSET FOREIGN_KEY_CHECKS=1;\n";
    echo "SET UNIQUE_CHECKS=1;\n";
}
