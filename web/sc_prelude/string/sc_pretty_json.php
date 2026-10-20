<?PHP

// JSON 編碼（美化——自動帶 JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ＋ 縮排）
function sc_pretty_json($data)
{
    return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
