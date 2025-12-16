<?php
// batch_import_all_anilist.php
// AniList の全キャラクターをページングで取得して DB に保存します。
// 注意: データ量が非常に多いため時間がかかります。実行前に DB バックアップを推奨します。
// 使い方 (CLI): php batch_import_all_anilist.php [startPage=1] [maxPages=0] [perPage=50] [delay=1]
// 例: php batch_import_all_anilist.php 1 0 50 1

// DB 設定
$dbHost = '127.0.0.1';
$dbPort = 3306;
$dbName = 'snsdb'; // 必要に応じて変更
$dbUser = 'root';
$dbPass = '';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    echo "DB接続に失敗しました: " . $e->getMessage() . "\n";
    exit(1);
}

// CLI 引数
$startPage = isset($argv[1]) ? max(1, intval($argv[1])) : 1;
$maxPages = isset($argv[2]) ? intval($argv[2]) : 0; // 0 = 無制限
$perPage = isset($argv[3]) ? max(1, min(50, intval($argv[3]))) : 50; // AniList 上限は 50
$delay = isset($argv[4]) ? floatval($argv[4]) : 1.0; // ページ間の待機（秒）

echo "開始: startPage={$startPage}, maxPages={$maxPages}, perPage={$perPage}, delay={$delay}\n";

$insertSql = "INSERT INTO `Characters` (anilist_id, `Name`, `Title`, BirthMonth, BirthDay, Character_img, Gender, Age)
        VALUES (:anilist_id, :name, :title, :birthMonth, :birthDay, :img, :gender, :age)
        ON DUPLICATE KEY UPDATE
          `Name` = VALUES(`Name`),
          `Title` = VALUES(`Title`),
          BirthMonth = VALUES(BirthMonth),
          BirthDay = VALUES(BirthDay),
          Character_img = VALUES(Character_img),
          Gender = VALUES(Gender),
          Age = VALUES(Age)";

$stmt = $pdo->prepare($insertSql);

$page = $startPage;
$imported = 0;
$errors = 0;

// GraphQL クエリ: Page の characters を取得
$pageQuery = <<<'GQL'
query ($page: Int, $perPage: Int) {
  Page(page: $page, perPage: $perPage) {
    pageInfo { total currentPage lastPage hasNextPage perPage }
    characters { id name { full } image { large medium } gender age dateOfBirth { month day } media(perPage:1, sort:POPULARITY_DESC) { nodes { title { romaji english } } } }
  }
}
GQL;

// ページングで取得
while (true) {
    echo "ページ $page を取得中...\n";

    $payload = json_encode(['query' => $pageQuery, 'variables' => ['page' => $page, 'perPage' => $perPage]]);

    $ch = curl_init('https://graphql.anilist.co');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $res = curl_exec($ch);
    if ($res === false) {
        $err = curl_error($ch);
        echo "GraphQL リクエスト失敗: {$err}\n";
        $errors++;
        curl_close($ch);
        // 簡易リトライ
        sleep(5);
        continue;
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($res, true);
    if ($httpCode !== 200 || !$json || isset($json['errors'])) {
        echo "GraphQL エラーまたは不正なレスポンス (HTTP {$httpCode})\n";
        var_export($json ?: $res);
        $errors++;
        sleep(5);
        continue;
    }

    $pageData = $json['data']['Page'] ?? null;
    if (!$pageData) {
        echo "ページデータがありません\n";
        break;
    }

    $characters = $pageData['characters'] ?? [];
    if (!$characters) {
        echo "このページにキャラクターがありません。終了。\n";
        break;
    }

    foreach ($characters as $c) {
        $anilist_id = $c['id'] ?? null;
        $name_full = $c['name']['full'] ?? null;
        $img = $c['image']['large'] ?? $c['image']['medium'] ?? null;
        $gender = $c['gender'] ?? null;
        $age = $c['age'] ?? null;
        $birthMonth = isset($c['dateOfBirth']['month']) ? (int)$c['dateOfBirth']['month'] : null;
        $birthDay = isset($c['dateOfBirth']['day']) ? (int)$c['dateOfBirth']['day'] : null;
        $title = null;
        if (!empty($c['media']['nodes'][0]['title']['romaji'])) {
            $title = $c['media']['nodes'][0]['title']['romaji'];
        } elseif (!empty($c['media']['nodes'][0]['title']['english'])) {
            $title = $c['media']['nodes'][0]['title']['english'];
        }

        try {
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
            $imported++;
        } catch (PDOException $e) {
            echo "DB エラー（anilist_id={$anilist_id}）: " . $e->getMessage() . "\n";
            $errors++;
        }
    }

    echo "ページ {$page} 完了: このページの件数=" . count($characters) . ", 累計インポート={$imported}\n";

    // 終了判定
    $hasNext = $pageData['pageInfo']['hasNextPage'] ?? false;
    if (!$hasNext) {
        echo "最終ページに到達しました。終了。\n";
        break;
    }

    $page++;
    if ($maxPages > 0 && ($page - $startPage) >= $maxPages) {
        echo "最大ページ数に達しました。停止。\n";
        break;
    }

    // サーバー負荷対策
    sleep($delay);
}

echo "\n完了: インポート済み {$imported} 件、エラー {$errors} 件\n";

// 終了
?>
