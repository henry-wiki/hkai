<?PHP

// 追加 JSONL 一行（緊湊格式——sc_json_encode ＋ 標準換行；檔案不存在＝自動建立）
function sc_append_jsonl($filepath, $array)
{
    return file_put_contents($filepath, sc_json_encode($array) . "\n", FILE_APPEND | LOCK_EX);
}
