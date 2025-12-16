<?php
// batch_import_anilist.php
// 複数のキャラクターを一括インポートします
// 使用方法: php batch_import_anilist.php < character_ids.txt

// キャラクター ID のリスト（ここに追加してください）
$characterIds = [
    8,    // ハルヒ
    46,   // 水卜麻美
];

// または標準入力から ID を読み込む場合（未使用、デフォルトは上記のリストを使用）
// パイプでファイルを渡す場合: php batch_import_anilist.php < ids.txt

// fetch_and_import_anilist.php を呼び出します
$baseUrl = 'http://localhost/fetch_and_import_anilist.php';
$count = 0;
$errors = [];

echo "インポート開始...\n";
echo "URL: $baseUrl\n\n";

$maxRetries = 3; // 最大再試行回数
$initialDelay = 1; // 再試行の初期待機秒数（指数バックオフ）

foreach ($characterIds as $id) {
    $url = $baseUrl . '?id=' . intval($id);
    echo "リクエスト: $url\n";

    $attempt = 0;
    $success = false;
    $delay = $initialDelay;

    while ($attempt < $maxRetries && !$success) {
        $attempt++;
        echo "  試行 $attempt/$maxRetries ...\n";

        $response = @file_get_contents($url);

        if ($response === false) {
            echo "  [WARN] HTTP リクエスト失敗（file_get_contents が false を返しました）\n";
            if ($attempt < $maxRetries) {
                echo "    再試行まで {$delay} 秒待機...\n";
                sleep($delay);
                $delay *= 2;
                continue;
            } else {
                echo "  [ERROR] ID $id - 最大試行回数に達しました\n";
                $errors[] = "ID $id: HTTP request failed";
                break;
            }
        } elseif ($response === '') {
            echo "  [WARN] 空のレスポンス\n";
            if ($attempt < $maxRetries) {
                echo "    再試行まで {$delay} 秒待機...\n";
                sleep($delay);
                $delay *= 2;
                continue;
            }
            echo "  [ERROR] ID $id - 空のレスポンス\n";
            $errors[] = "ID $id: Empty response";
            break;
        } else {
            $json = json_decode($response, true);
            if ($json === null) {
                echo "  [WARN] JSON デコード失敗\n";
                echo "    生のレスポンス: $response\n";
                if ($attempt < $maxRetries) {
                    echo "    再試行まで {$delay} 秒待機...\n";
                    sleep($delay);
                    $delay *= 2;
                    continue;
                }
                $errors[] = "ID $id: JSON decode failed";
                break;
            } elseif ($json && $json['status'] === 'ok') {
                echo "  [OK] ID $id - " . ($json['name'] ?? 'Unknown') . "\n";
                $count++;
                $success = true;
                break;
            } else {
                $msg = $json['message'] ?? 'Unknown error';
                echo "  [ERROR] ID $id - $msg\n";
                $errors[] = "ID $id: $msg";
                break;
            }
        }
    }

    // 次の ID へ（サーバーに負荷をかけないため短い待機）
    sleep(1);
}

echo "\n完了: $count 件をインポートしました。\n";
if ($errors) {
    echo "エラー: " . count($errors) . " 件\n";
    foreach ($errors as $err) {
        echo "  - $err\n";
    }
}
?>
