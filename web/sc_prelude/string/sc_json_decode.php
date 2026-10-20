<?PHP

// JSON 解碼（一律回關聯陣列——json_decode 第二參數 true 自動帶）
function sc_json_decode($json)
{
    return json_decode($json, true);
}
