<?php
// == sc_framework/function/debug/print_array.func.php：全域 debug 工具 ==
// == print_array()：印出變數/陣列內容（debug 用——CLI 直接輸出；web 情境包 <pre> 防 HTML 混淆） ==
// == 由 load(sc_framework/function) 遞迴自動載入——放進即生效，無需註冊 ==

function print_array($data)
{
    $output = print_r($data, true);
    
    // CLI 情境：直接輸出
    if (PHP_SAPI === "cli") {
        echo $output . PHP_EOL;
        return;
    }
    
    // web 情境：包 <pre> 並跳脫 HTML 特殊字元（避免結構被瀏覽器當標籤吃掉）
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
}
