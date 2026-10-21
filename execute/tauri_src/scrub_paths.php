<?php
// == build 後處理：把 build 機路徑字面值同長度遮蔽（~）——避免公開 exe 洩露目錄結構 ==
// 用法：php scrub_paths.php <exe> <root>

$exe_path = $argv[1];
$root_raw = rtrim($argv[2], "\/");

// 候選寫法（反斜線／正斜線）
$needles = array_unique([
    $root_raw,
    str_replace("\\", "/", $root_raw),
    str_replace("/", "\\", $root_raw),
]);

$exe_bytes = file_get_contents($exe_path);
$total     = 0;

// 逐個寫法：同長度 '~' 取代
foreach ($needles as $needle_item) {
    $count = substr_count($exe_bytes, $needle_item);
    if ($count > 0) {
        $exe_bytes = str_replace($needle_item, str_repeat("~", strlen($needle_item)), $exe_bytes);
        $total    += $count;
    }
}

file_put_contents($exe_path, $exe_bytes);
echo "scrubbed {$total} occurrence(s)\n";
