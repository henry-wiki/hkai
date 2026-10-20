<?PHP

// https://github.com/symfony/polyfill/blob/1.x/src/Php80/Php80.php（行為 100% 對齊官方，含空 needle → true、byte-based 搜尋）

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        // null 傳入依官方 bootstrap.php 以空字串代替
        $_haystack = isset($haystack) ? $haystack : '';
        $_needle = isset($needle) ? $needle : '';
        // 空 needle 或完全相等（官方 Php80.php 短路條件）
        if ('' === $_needle || $_needle === $_haystack) {
            return true;
        }
        // 空 haystack 且 needle 非空
        if ('' === $_haystack) {
            return false;
        }
        $_needleLength = strlen($_needle);
        return $_needleLength <= strlen($_haystack) && 0 === substr_compare($_haystack, $_needle, -$_needleLength);
    }
}
