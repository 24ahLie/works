<?php
// display_random_character.php
// ブラウザでランダムなキャラクターを見やすく表示します。

// DB 設定（必要に応じて編集してください）
$dbHost = '127.0.0.1';
$dbPort = 3306;
$dbName = 'sns'; // sns または snsdb 等、実際の DB 名に合わせてください
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
    echo "<h1>DB接続失敗</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

try {
    $sql = "SELECT Character_id, anilist_id, `Name`, `Title`, BirthMonth, BirthDay, Character_img, Gender, Age
            FROM `Characters`
            ORDER BY RAND()
            LIMIT 1";
    $stmt = $pdo->query($sql);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo "<h1>キャラクターが見つかりません</h1>";
        exit;
    }

    // フィールド整形
    $name = $row['Name'];
    $title = $row['Title'];
    $img = $row['Character_img'];
    $gender = $row['Gender'];
    $age = $row['Age'];
    $birthMonth = $row['BirthMonth'];
    $birthDay = $row['BirthDay'];
    $anilist_id = $row['anilist_id'];

    // HTML 出力
    ?>
    <!doctype html>
    <html lang="ja">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <title>ランダムキャラクター</title>
      <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Hiragino Kaku Gothic ProN", "Osaka", "Meiryo", sans-serif; padding:20px; background:#f7f7f7 }
        .card { background:#fff; padding:16px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.06); max-width:720px; margin:0 auto }
        .row { display:flex; gap:16px; align-items:flex-start }
        img.avatar { width:220px; height:auto; border-radius:6px; object-fit:cover }
        .meta { flex:1 }
        .meta h1 { margin:0 0 8px 0; font-size:1.6rem }
        .meta p { margin:4px 0; color:#333 }
        .small { color:#666; font-size:0.9rem }
        .btn { display:inline-block; margin-top:10px; padding:8px 12px; background:#007bff; color:#fff; text-decoration:none; border-radius:6px; font-size:0.95rem }
        .btn:hover { background:#0056b3 }
      </style>
    </head>
    <body>
      <div class="card">
        <div class="row">
          <?php if ($img): ?>
            <img class="avatar" src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($name); ?>">
          <?php else: ?>
            <div style="width:220px;height:220px;background:#eee;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#999">画像なし</div>
          <?php endif; ?>

          <div class="meta">
            <h1><?php echo htmlspecialchars($name); ?></h1>
            <?php if ($title): ?>
              <p class="small">作品: <?php echo htmlspecialchars($title); ?></p>
            <?php endif; ?>

            <p>性別: <?php echo $gender ? htmlspecialchars($gender) : '<span class="small">不明</span>'; ?></p>
            <p>年齢: <?php echo $age ? htmlspecialchars($age) : '<span class="small">不明</span>'; ?></p>
            <p>誕生日: <?php
                if ($birthMonth || $birthDay) {
                    echo ($birthMonth ? intval($birthMonth) . '月' : '') . ($birthDay ? intval($birthDay) . '日' : '');
                } else {
                    echo '<span class="small">不明</span>';
                }
            ?></p>

            <?php if ($anilist_id): ?>
              <a class="btn" href="https://anilist.co/character/<?php echo intval($anilist_id); ?>" target="_blank" rel="noopener">AniList で開く</a>
            <?php endif; ?>

            <div style="margin-top:12px">
              <a href="display_random_character.php">別のキャラを表示</a>
            </div>
          </div>
        </div>
      </div>
    </body>
    </html>
    <?php

} catch (PDOException $e) {
    http_response_code(500);
    echo "<h1>DBエラー</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}
