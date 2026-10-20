<?PHP

// 寫入 JSON 檔（美化格式——sc_pretty_json ＋ file_put_contents）
function sc_write_json($filepath, $array)
{
    return file_put_contents($filepath, sc_pretty_json($array), LOCK_EX);
}
