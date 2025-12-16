<?php
// batch_import_anilist_direct.php
// HTTP を経由せず直接 fetch_and_import_anilist.php を実行します

// キャラクター ID のリスト
$characterIds = [
    1,    // クラリス
    2,    // タマキ
    11,   // ナルト
    3,    // アスナ
    37,   // レムリア
    95,   // エミリア
    29,   // 武
    39,   // ラム
    8,    // ハルヒ
    46,   // 水卜麻美
];

// fetch_and_import_anilist.php の内容を直接読み込む
$script = file_get_contents(__DIR__ . '/fetch_and_import_anilist.php');

$count = 0;
$errors = [];

echo "インポート開始...\n\n";

foreach ($characterIds as $id) {
    echo "ID $id をインポート中...\n";
    
    // $_REQUEST をセットして実行
    $_REQUEST = ['id' => $id];
    $_GET = ['id' => $id];
    
    // output をキャプチャ
    ob_start();
    eval('?>' . $script);
    $output = ob_get_clean();
    
    if ($output) {
        $json = json_decode($output, true);
        if ($json && $json['status'] === 'ok') {
            echo "[OK] " . ($json['name'] ?? 'Unknown') . "\n";
            $count++;
        } else {
            $msg = $json['message'] ?? $output;
            echo "[ERROR] $msg\n";
            $errors[] = "ID $id: $msg";
        }
    } else {
        echo "[ERROR] 空のレスポンス\n";
        $errors[] = "ID $id: Empty response";
    }
    
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
