<?PHP

// JSON 編碼（自動帶 JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE）
function sc_json_encode($data)
{
    return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
