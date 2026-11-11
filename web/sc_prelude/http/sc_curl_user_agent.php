<?PHP

/**
 * 組 HTTP User-Agent（HKAI/<版本>——版本取站台 VERSION 檔；讀唔到＝0.0.0）
 *
 * @return string
 */
function sc_curl_user_agent()
{
    // 站台根（優先已定義常數；否則由本檔位置推算：http → sc_prelude → web → 站台根）
    $repo_root = defined("SC_REPO_ROOT") ? SC_REPO_ROOT : dirname(__DIR__, 3);

    $version_file_path = $repo_root . "/VERSION";

    $version_text = "0.0.0";

    // 判定：版本檔存在 → 讀內容（去頭尾空白）
    if (file_exists($version_file_path) === true) {
        $version_text = trim((string)file_get_contents($version_file_path));
    }

    // 剝版本前綴 v（UA 規範 product/version——版本位只放數字版本）
    $version_text = ltrim($version_text, "vV");

    return "HKAI/" . $version_text;
}
