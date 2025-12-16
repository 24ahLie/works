
<?php
header('Content-Type: application/json; charset=utf-8');

// DB 設定（必要に応じて編集）
$dbHost = '127.0.0.1';
$dbPort = 3306;
$dbName = 'sns';
$dbUser = 'root';
$dbPass = '';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        $options
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB接続失敗']);
    exit;
}

try {
    // RAND() でランダム1件取得
    $sql = "SELECT Character_id, anilist_id, `Name`, `Title`, BirthMonth, BirthDay, Character_img, Gender, Age
            FROM `Characters`
            ORDER BY RAND()
            LIMIT 1";
    $stmt = $pdo->query($sql);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'キャラクターが見つかりません']);
        exit;
    }

    // 整形して返す
    $character = [
        'Character_id' => (int)$row['Character_id'],
        'anilist_id' => $row['anilist_id'] !== null ? (int)$row['anilist_id'] : null,
        'Name' => $row['Name'],
        'Title' => $row['Title'],
        'BirthMonth' => $row['BirthMonth'] !== null ? (int)$row['BirthMonth'] : null,
        'BirthDay' => $row['BirthDay'] !== null ? (int)$row['BirthDay'] : null,
        'Character_img' => $row['Character_img'],
        'Gender' => $row['Gender'],
        'Age' => $row['Age'],
    ];

    echo json_encode(['status' => 'ok', 'character' => $character], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DBクエリ失敗']);
}