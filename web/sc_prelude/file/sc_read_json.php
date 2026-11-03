<?PHP

// 讀 JSON 檔（同 sc_write_json 一對——唯讀；配合原子寫入，讀到嘅永遠係完整內容）
// 職責：檔案唔存在／讀唔到／解析失敗 → null；成功 → array
function sc_read_json($filepath)
{
    // 判定：冇檔 → null
    if (is_file($filepath) === false) {
        return null;
    }

    $json_text = @file_get_contents($filepath);

    // 判定：讀唔到 → null
    if ($json_text === false) {
        return null;
    }

    $json_data = json_decode($json_text, true);

    // 判定：解析失敗 → null
    if (is_array($json_data) === false) {
        return null;
    }

    return $json_data;
}
