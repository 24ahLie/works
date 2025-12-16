<?php
// import_by_name_post.php
// HTML フォームから送られたキャラクター名を受け取り、AniList GraphQL で検索して DB に保存します。
// POST name=キャラ名

// DB 設定 - 必要に応じて編集
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

// レスポンス送信用ユーティリティ
function send_response($data, $status = 200) {
    // 判定: CLI / AJAX / ブラウザの普通アクセス
    $isCli = (php_sapi_name() === 'cli');
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $wantsHtml = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false;
    $shouldRedirect = !$isCli && !$isAjax && $wantsHtml;

    if ($shouldRedirect) {
        // ブラウザのフォーム送信なら表示しないでフォームページに戻す
        $location = '/import_by_name.php';
        header('Location: ' . $location, true, 303);
        exit;
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 入力取得
$name = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : null;
} else {
    $name = isset($_GET['name']) ? trim($_GET['name']) : null;
}

if (!$name) {
    send_response(['status' => 'error', 'message' => '名前が指定されていません'], 400);
}

if (mb_strlen($name) > 200) {
    send_response(['status' => 'error', 'message' => '名前が長すぎます'], 400);
}

// GraphQL クエリ（fetch single by search）
$query = <<<'GQL'
query ($search: String) {
  Character(search: $search) {
    id
    name { full }
    image { large medium }
    gender
    age
    dateOfBirth { month day }
    media(perPage:1, sort:POPULARITY_DESC) { nodes { title { romaji english } } }
  }
}
GQL;

$payload = json_encode(['query' => $query, 'variables' => ['search' => $name]]);

$ch = curl_init('https://graphql.anilist.co');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$res = curl_exec($ch);
if ($res === false) {
    $err = curl_error($ch);
    curl_close($ch);
    // ログ
    write_import_error('import_by_name_post', $name, $err);
    send_response(['status' => 'error', 'message' => 'AniList リクエスト失敗', 'detail' => $err], 500);
}
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$json = json_decode($res, true);
if ($httpCode !== 200 || !$json || isset($json['errors'])) {
    $detail = $json['errors'] ?? $res;
    write_import_error('import_by_name_post', $name, $detail);
    send_response(['status' => 'error', 'message' => 'AniList エラー', 'detail' => $detail], 500);
}

$character = $json['data']['Character'] ?? null;
if (!$character) {
    write_import_error('import_by_name_post', $name, 'Character not found');
    send_response(['status' => 'error', 'message' => 'キャラクターが見つかりません'], 404);
}

// DB 保存
try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    write_import_error('import_by_name_post', $name, 'DB connect: ' . $e->getMessage());
    send_response(['status' => 'error', 'message' => 'DB接続失敗', 'detail' => $e->getMessage()], 500);
}

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

    write_import_success('import_by_name_post', $name_full, $anilist_id);
    send_response(['status' => 'ok', 'anilist_id' => $anilist_id, 'name' => $name_full]);
} catch (PDOException $e) {
    write_import_error('import_by_name_post', $name, 'DB execute: ' . $e->getMessage());
    send_response(['status' => 'error', 'message' => 'DB 操作失敗', 'detail' => $e->getMessage()], 500);
}

?>
