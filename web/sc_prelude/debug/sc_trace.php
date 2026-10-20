<?PHP

/**
 * 印出 caller backtrace（檔案路徑 + 行數）
 *
 * @param int  $depth  回溯層數（預設 6）
 * @param bool $return true=return 字串, false=直接 echo
 * @return string|null 若 $return=true 則返回字串，否則返回 null
 *
 * 使用方式:
 *   sc_trace();           // 預設回溯 6 層，直接 echo
 *   sc_trace(9);          // 回溯 9 層
 *   $str = sc_trace(6, true); // 取得字串不回顯
 */
function sc_trace($depth = 6, $return = false)
{
    $dbt = debug_backtrace();

    // 移除 sc_trace 自身（index 0）
    array_shift($dbt);

    // 取指定層數
    $dbt = array_slice($dbt, 0, $depth);

    $lines = array();
    $index = 0;

    foreach ($dbt as $item) {
        $index++;
        $file = isset($item["file"]) ? $item["file"] : "(unknown)";
        $line = isset($item["line"]) ? $item["line"] : "?";
        $func = isset($item["function"]) ? $item["function"] : "";
        $class = isset($item["class"]) ? $item["class"] : "";
        $type = isset($item["type"]) ? $item["type"] : "";

        $caller = "";
        if ($class) {
            $caller = $class . $type . $func . "()";
        } else {
            $caller = $func . "()";
        }

        //$lines[] = sprintf("#%d %s:%d  %s", $index, $file, $line, $caller);
        $lines[] = sprintf("#%d %s:%d", $index, basename($file), $line);
    }

    $output = implode("\r\n", $lines);

    if ($return) {
        return $output;
    }

    echo "<pre style='background:#1e1e1e;color:#d4d4d4;padding:10px;font-size:12px;line-height:1.6;'>";
    echo htmlspecialchars($output, ENT_QUOTES, "UTF-8");
    echo "</pre>";

    return null;
}
