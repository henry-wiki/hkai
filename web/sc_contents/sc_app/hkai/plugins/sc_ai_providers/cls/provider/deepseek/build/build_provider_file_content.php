<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\deepseek\build;

// == sc_ai_providers provider/deepseek build build_provider_file_content：域動作（trait——掛入 provider_deepseek_Class；檔＝動作） ==
// == 2026-10-06 自 provider/deepseek/_.bridge.cls.php 分遷入（按函數工作內容分檔） ==

trait build_provider_file_content
{
    // 組產檔字串並寫入新檔（_new_<uniqid>.php——檔頭註解 + return 結構；php_code_export 一次匯出）
    // 回傳：["ok" => true, "new_file_path" => ...] ／ ["ok" => false, "error" => ...]
    private function build_provider_file_content($provider_models, $provider_dirty_models=[])
    {
        // 暫存檔路徑（正式檔去 .php + _new_<uniqid>.php——同目錄；每次獨一名）
        $new_file_path = preg_replace("/\.php$/", "_new_" . uniqid() . ".php", provider()->get_provider_file_path());
        
        // 組頂層結構（provider_key → generated_at → model）
        $file_structure                 = [];
        $file_structure["provider_key"] = "deepseek";
        $file_structure["generated_at"] = date("Y-m-d H:i:s");
        
        // 組 model 表（每 row 依輸出順序重建 key——description 尾列）
        $file_structure["model"] = $provider_models;
        $file_structure["dirty_model"] = $provider_dirty_models;
        
        // 一次匯出（php_code_export——[] 語法、2 空格縮排）+ 檔頭註解包裝
        $exported_code = sc_ai_providers()->repo()->php_code_export($file_structure);
        $file_content  = "<?php\r\n"
        . "// provider_deepseek.php——DeepSeek provider 元資料\r\n"
        . "// AUTO-GENERATED：sc_ai_providers Reset Providers（來源 https://api.deepseek.ai/api/gateway/models）——勿手改\r\n"
        . "return " . $exported_code . ";\r\n";
        
        // 確保目標目錄存在（首次重設——目錄未建時自動建立）
        sc_mkdir(dirname($new_file_path));

        // 寫入新檔（file_put_contents 預設無 BOM——組裝內容轉為正式檔前的中繼）
        $write_bytes = file_put_contents($new_file_path, $file_content);
        if ($write_bytes === false) {
            return ["ok" => false, "error" => "write failed"];
        }
        if ($write_bytes === 0) {
            return ["ok" => false, "error" => "write empty"];
        }
        
        return ["ok" => true, "new_file_path" => $new_file_path];
    }
}
