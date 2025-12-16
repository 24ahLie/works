<?php
// fetch_and_import_anilist.php
// Usage (CLI): php fetch_and_import_anilist.php id=12345
// Usage (web): fetch_and_import_anilist.php?id=12345 or ?name=Naruto

// Configuration - adjust for your XAMPP MySQL if needed
$dbHost = '127.0.0.1';
$dbPort = 3306;
$dbName = 'snsdb';
$dbUser = 'root';
$dbPass = '';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
];

// ログユーティリティ
require_once __DIR__ . '/log_util.php';

try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    write_import_error('fetch_and_import_anilist', isset($name) ? $name : ($id ?? null), 'DB connect: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB接続失敗', 'detail' => $e->getMessage()]);
    exit(1);
}

// Input handling: support CLI and web
$input = [];
if (php_sapi_name() === 'cli') {
    // parse argv like id=123 name=Naruto
    foreach ($argv as $i => $arg) {
        if ($i === 0) continue;
        if (strpos($arg, '=') !== false) {
            [$k, $v] = explode('=', $arg, 2);
            $input[$k] = $v;
        }
    }
} else {
    // web
    $input = $_REQUEST;
}

$id = isset($input['id']) ? (int)$input['id'] : null;
$name = isset($input['name']) ? trim($input['name']) : null;

if (!$id && !$name) {
    $msg = "Specify id=<Anilist character id> or name=<search string>";
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

// Build GraphQL query
$query = <<<'GQL'
query ($id: Int, $search: String) {
  Character(id: $id, search: $search) {
    id
    name {
      full
      native
      first
      last
    }
    image {
      large
      medium
    }
    gender
    age
    dateOfBirth { month day }
    media(perPage: 1, sort: POPULARITY_DESC) { nodes { title { romaji english native } } }
  }
}
GQL;

$variables = [];
if ($id) $variables['id'] = $id;
if ($name) $variables['search'] = $name;

$payload = json_encode(['query' => $query, 'variables' => $variables]);

$ch = curl_init('https://graphql.anilist.co');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

$res = curl_exec($ch);
if ($res === false) {
    $err = curl_error($ch);
    curl_close($ch);
    write_import_error('fetch_and_import_anilist', $name ?? $id, 'AniList request failed: ' . $err);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'AniList request failed', 'detail' => $err]);
    exit(1);
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$json = json_decode($res, true);
if ($httpCode !== 200 || !$json || isset($json['errors'])) {
    $detail = $json['errors'] ?? $res;
    write_import_error('fetch_and_import_anilist', $name ?? $id, $detail);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'AniList error', 'detail' => $detail]);
    exit(1);
}

$character = $json['data']['Character'] ?? null;
if (!$character) {
    write_import_error('fetch_and_import_anilist', $name ?? $id, 'Character not found');
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Character not found']);
    exit;
}

// Map fields
$anilist_id = $character['id'];
$name_full = $character['name']['full'] ?? null;
$img = $character['image']['large'] ?? $character['image']['medium'] ?? null;
$gender = $character['gender'] ?? null;
$age = $character['age'] ?? null;
$birthMonth = isset($character['dateOfBirth']['month']) ? (int)$character['dateOfBirth']['month'] : null;
$birthDay = isset($character['dateOfBirth']['day']) ? (int)$character['dateOfBirth']['day'] : null;
$title = null;
if (!empty($character['media']['nodes'][0]['title']['romaji'])) {
    $title = $character['media']['nodes'][0]['title']['romaji'];
} elseif (!empty($character['media']['nodes'][0]['title']['english'])) {
    $title = $character['media']['nodes'][0]['title']['english'];
}

// Insert/update into DB
$sql = "INSERT INTO `Characters` (anilist_id, `Name`, `Title`, BirthMonth, BirthDay, Character_img, Gender, Age)
        VALUES (:anilist_id, :name, :title, :birthMonth, :birthDay, :img, :gender, :age)
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
        ':anilist_id' => $anilist_id,
        ':name' => $name_full,
        ':title' => $title,
        ':birthMonth' => $birthMonth !== 0 ? $birthMonth : null,
        ':birthDay' => $birthDay !== 0 ? $birthDay : null,
        ':img' => $img,
        ':gender' => $gender,
        ':age' => $age,
    ]);
} catch (PDOException $e) {
    write_import_error('fetch_and_import_anilist', $name_full ?? ($name ?? $id), 'DB insert failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB 操作失敗', 'detail' => $e->getMessage()]);
    exit(1);
}

$result = ['status' => 'ok', 'anilist_id' => $anilist_id, 'name' => $name_full];
write_import_success('fetch_and_import_anilist', $name_full, $anilist_id);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_UNICODE);
?>
