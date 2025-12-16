<?php
// log_util.php
// 簡易ログユーティリティ（JSONL 形式で追記）

function write_import_log(array $entry)
{
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . '/import.log';
    $record = array_merge([
        'ts' => date('c'),
    ], $entry);
    $line = json_encode($record, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

// ヘルパー: エラーログ
function write_import_error($script, $name = null, $detail = null)
{
    write_import_log([
        'script' => $script,
        'name' => $name,
        'status' => 'error',
        'detail' => $detail,
    ]);
}

// ヘルパー: 成功ログ
function write_import_success($script, $name = null, $anilist_id = null, $extra = [])
{
    write_import_log(array_merge([
        'script' => $script,
        'name' => $name,
        'status' => 'ok',
        'anilist_id' => $anilist_id,
    ], $extra));
}
