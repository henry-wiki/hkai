<?PHP

// 建立目錄（遞迴；已存在＝無動作；建立後 chmod 校正——防 umask／怪權限）
function sc_mkdir($dir_path, $mode = 0755)
{
    // 判定：mode 唔係整數（字串／浮點／null——PHP 數值強轉會出坑）→ 用安全底 0755
    if (!is_int($mode)) {
        $mode = 0755;
    }

    // 判定：mode 低於 0644（唔正常低位）→ 用安全底 0755
    if ($mode < 0644) {
        $mode = 0755;
    }

    // 判定：mode 高於 0777（含 setuid/sticky 等特殊位——唔可能）→ 用安全底 0755
    if ($mode > 0777) {
        $mode = 0755;
    }

    // 判定：目錄已存在 → 無動作
    if (is_dir($dir_path)) {
        return true;
    }

    // 建立（遞迴）
    $created = mkdir($dir_path, $mode, true);

    // 判定：建立失敗 → 回報
    if ($created === false) {
        return false;
    }

    // 校正權限（chmod 不受 umask 影響——確保傳入嘅 mode 真係生效）
    chmod($dir_path, $mode);

    return true;
}
