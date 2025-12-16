<?php
// migrate_create_boards.php
// create_boards_table.sql を読み込んで実行する簡易マイグレーションスクリプト
// 使用方法 (PowerShell): php migrate_create_boards.php

// --- DB設定: 環境に合わせて編集してください ---
$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'snsdb'; // ここを実際のデータベース名に変更してください（例: sns や snsdb）
// ------------------------------------------

$sqlFile = __DIR__ . '/create_boards_table.sql';
if (!file_exists($sqlFile)) {
    echo "SQLファイルが見つかりません: $sqlFile\n";
    exit(1);
}

try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $sql = file_get_contents($sqlFile);
    // 単一のファイルに複数ステートメントがある場合もあるため、PDO::exec で実行。
    $pdo->exec($sql);

    echo "Boards テーブルの作成が完了しました（または既に存在します）。\n";
    exit(0);
} catch (PDOException $e) {
    echo "DB エラー: " . $e->getMessage() . "\n";
    exit(1);
}
