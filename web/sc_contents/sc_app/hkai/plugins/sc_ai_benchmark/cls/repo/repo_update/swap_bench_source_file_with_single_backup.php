<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\repo\repo_update;

// == sc_ai_benchmark repo swap_bench_source_file_with_single_backup：域動作（trait——掛入 repo_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat sc_ai_benchmark_main 分遷入（one file one action） ==
// == 2026-10-06 自 main 域分遷入 repo（one file one action） ==
// == 2026-10-07 單層備份（當日份） ==
// == 2026-10-08 移除 prune 呼叫——清除交獨立清掃程序（模組只做好下載／備份）；改名 swap_bench_source_file_with_backup → swap_bench_source_file_with_single_backup ==

trait swap_bench_source_file_with_single_backup
{
    // 交換上線（新檔已由呼叫端寫好——獨一名 _new_<uniqid>.php）：原檔存在 → rename 為單層 .bak；再 rename 新檔上線
    // 本方法零 exec、零 copy（唔做保留清理——清除交由獨立清掃程序）；錯誤路徑不改動任何既有檔
    public function swap_bench_source_file_with_single_backup($target_path, $new_file_path)
    {
        // 備份基底路徑（target 去 .php——備份檔名由此衍生）
        $backup_base = preg_replace("/\.php$/", "", $target_path);

        // 備份檔路徑（backup_file 回報用——單層）
        $backup_file = "";

        // 步驟 1：原檔存在 → 備份（rename _Ymd_His.bak——單層）
        if (file_exists($target_path)) {
            $backup_file = $backup_base . "_" . date("Ymd_His") . ".bak";

            // 備份改名失敗（如同秒 .bak 已存在）→ 中止（target 仍在）
            if (!rename($target_path, $backup_file)) {
                return ["ok" => false, "error" => "backup rename failed"];
            }
        }

        // 步驟 2：新檔 rename 上線（同目錄原子操作——target 位置已空出）
        if (!rename($new_file_path, $target_path)) {
            return ["ok" => false, "error" => "rename to target failed"];
        }

        return ["ok" => true, "backup_file" => $backup_file];
    }
}
