<?php
// == web/sc_prelude/file/sc_normalize_path.php：路徑正規化（單一入口 helper）
// == 用法：sc_normalize_path($path) → 正斜線＋剝 //./ ／ //?/ 裝置前綴
// == 原因：Apache＋FastCGI 喺 Windows 交 //./G:/…（裝置形）；路徑一旦流出 PHP
// ==       （chdir／exec／git -C／字串比對）就會靜默失敗；喺邊界洗一次即免疫
// == 性質：idempotent（洗過再洗一樣）；URL（含 ://）原樣回；唔檢查檔案存在

function sc_normalize_path($path)
{
    // 判定：非字串（null／陣列）→ 原樣回
    if (!is_string($path)) {
        return $path;
    }

    // 判定：空字串 → 原樣回
    if ($path === "") {
        return $path;
    }

    // 判定：URL（http://…）→ 唔係磁碟路徑，原樣回
    if (strpos($path, "://") !== false) {
        return $path;
    }

    $normalized_path = str_replace("\\", "/", $path);

    // 判定：//./ 裝置前綴 → 剝走（Win32 裝置命名空間寫法）
    if (strpos($normalized_path, "//./") === 0) {
        $normalized_path = substr($normalized_path, 4);
    }

    // 判定：//?/ 裝置前綴（長路徑形）→ 剝走
    if (strpos($normalized_path, "//?/") === 0) {
        $normalized_path = substr($normalized_path, 4);
    }

    return $normalized_path;
}
