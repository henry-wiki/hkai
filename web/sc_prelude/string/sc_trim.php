<?PHP

// 修剪字串（null 當空字串——跨版本 5.4→8.4 通用；直接 trim(null) 喺 8.1+ 出 deprecation、9.0 變 TypeError）
function sc_trim($str, $characters = " \n\r\t\v\x00")
{
    return trim(isset($str) ? $str : "", $characters);
}
