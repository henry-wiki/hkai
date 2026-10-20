<?PHP

// https://github.com/symfony/polyfill/blob/1.x/src/Php80/Php80.php（行為 100% 對齊官方，含空 needle → true、byte-based 搜尋）

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        // null 傳入依官方 bootstrap.php 以空字串代替；官方為 byte-based strpos（非 mb_）
        $_haystack = isset($haystack) ? $haystack : '';
        $_needle = isset($needle) ? $needle : '';
        return '' === $_needle || false !== strpos($_haystack, $_needle);
    }
}
