<?PHP

// https://github.com/symfony/polyfill/blob/1.x/src/Php80/Php80.php（行為 100% 對齊官方，含空 needle → true、byte-based 搜尋）

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        // null 傳入依官方 bootstrap.php 以空字串代替
        $_haystack = isset($haystack) ? $haystack : '';
        $_needle = isset($needle) ? $needle : '';
        return 0 === strncmp($_haystack, $_needle, strlen($_needle));
    }
}
