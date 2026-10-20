<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\benchmark\llm_stats\build;

// == sc_ai_benchmark benchmark/llm_stats build build_bench_source_file_content：域動作（trait——掛入 bench_source_llm_stats_Class；檔＝動作） ==
// == 2026-10-07 自 benchmark/aa 複製後改為 LLM Stats 格式（原始 rows 直接 dump——欄位全保留、次序同 fetch 排序） ==
// == 2026-10-07 多 entry：逐 entry 一個 store 檔（檔路徑由呼叫端傳入） ==
// == 2026-10-07 Way 1：來源 URL 由呼叫端傳入（檔頭註解記實際來源——models／indexes 兩形） ==

trait build_bench_source_file_content
{
    // 組裝產檔字串並寫入新檔（_new_<uniqid>.php——原始 rows 直接 dump——欄位全保留）——回傳 ["ok"=>true, "new_file_path"=>...] 或 ["ok"=>false, "error"=>...]
    private function build_bench_source_file_content($bench_source_file, $entry_row_list, $fetch_url)
    {
        // 暫存檔路徑（正式檔去 .php + _new_<uniqid>.php——同目錄；每次獨一名）
        $new_file_path = preg_replace("/\.php$/", "_new_" . uniqid() . ".php", $bench_source_file);
        // 檔名（餵檔頭註解用）
        $file_basename = basename($bench_source_file);

        // 組頂層結構（bench_source_key → generated_at → llm_stats（rows））
        $file_structure                 = [];
        $file_structure["bench_source_key"] = "llm_stats";
        $file_structure["generated_at"] = date("Y-m-d H:i:s");
        $file_structure["llm_stats"]    = $entry_row_list;

        // 一次匯出（php_code_export——[] 語法、2 空格縮排）+ 檔頭註解包裝
        $exported_code = sc_ai_benchmark()->repo()->php_code_export($file_structure);
        $file_content  = "<?php\r\n"
        . "// " . $file_basename . "——LLM Stats（llm-stats.com）\r\n"
        . "// AUTO-GENERATED：sc_ai_benchmark（來源 " . $fetch_url . "）——勿手改\r\n"
        . "return " . $exported_code . ";\r\n";

        // 確保目標目錄存在（首次重生——目錄未建時自動建立）
        sc_mkdir(dirname($new_file_path));

        // 寫入新檔（file_put_contents 預設無 BOM——組裝內容轉為正式檔前的中繼）
        $write_bytes = file_put_contents($new_file_path, $file_content);

        // 判定：寫入失敗 → 回錯誤
        if ($write_bytes === false) {
            return ["ok" => false, "error" => "write failed"];
        }
        // 判定：寫入 0 位元組 → 回錯誤
        if ($write_bytes === 0) {
            return ["ok" => false, "error" => "write empty"];
        }

        return ["ok" => true, "new_file_path" => $new_file_path];
    }
}
