<?php
header('Content-Type: application/json; charset=utf-8');

// XAMPP に合わせた DB 設定
$dbHost = '127.0.0.1';
$dbPort = 3306;
$dbName = 'snsdb';
$dbUser = 'root';
$dbPass = ''; // XAMPP の MySQL 標準設定

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
    echo json_encode(['status' => 'error', 'message' => 'DB接続失敗', 'detail' => $e->getMessage()]);
    exit;
}

// 入力取得: JSON または form-data
$raw = file_get_contents('php://input');
if ($raw) {
    $data = json_decode($raw, true);
} else {
    $data = $_POST;
}

if (!is_array($data) || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit;
}

$sql = "INSERT INTO `Characters`
  (anilist_id, `Name`, `Title`, BirthMonth, BirthDay, Character_img, Gender, Age)
  VALUES
  (:anilist_id, :name, :title, :birthMonth, :birthDay, :img, :gender, :age)
  ON DUPLICATE KEY UPDATE
    `Name` = VALUES(`Name`),
    `Title` = VALUES(`Title`),
    BirthMonth = VALUES(BirthMonth),
    BirthDay = VALUES(BirthDay),
    Character_img = VALUES(Character_img),
    Gender = VALUES(Gender),
    Age = VALUES(Age)";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':anilist_id' => (int)$data['id'],
        ':name' => $data['name'] ?? null,
        ':title' => $data['anime_title'] ?? $data['title'] ?? null,
        ':birthMonth' => isset($data['birthMonth']) && $data['birthMonth'] !== '' ? (int)$data['birthMonth'] : null,
        ':birthDay' => isset($data['birthDay']) && $data['birthDay'] !== '' ? (int)$data['birthDay'] : null,
        ':img' => $data['image'] ?? $data['imageUrl'] ?? null,
        ':gender' => $data['gender'] ?? null,
        ':age' => $data['age'] ?? null,
    ]);
    echo json_encode(['status' => 'ok']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB 操作失敗', 'detail' => $e->getMessage()]);
}