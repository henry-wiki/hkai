<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_setup\action;

// == sc_ai_projects tab_setup check_has_longpaths：tab_setup 域動作（trait——掛入 tab_setup_Class；檔＝動作） ==
// == 2026-10-04 新增：偵察 repo 有冇用到 core.longpaths（生效值——repo／global 任一層 true 即有） ==

trait check_has_longpaths
{
    public function check_has_longpaths($project_path, $description)
    {
        $command_output = [];
        $command_result = 0;

        // 讀 core.longpaths 生效值（repo＋global 合併後嘅實際值；未設定時 git 以非零退出）
        @exec("git -C " . escapeshellarg($project_path) . " config core.longpaths 2>&1", $command_output, $command_result);

        // 讀唔到設定（非零退出）→ 冇用到
        if ($command_result !== 0) {
            return false;
        }

        $config_value = trim(implode("", $command_output));

        // git 布林真值同義寫法（true／1／yes／on）→ 有用到
        $longpaths_true_values = ["true", "1", "yes", "on"];
        if (in_array($config_value, $longpaths_true_values, true)) {
            return true;
        }

        // 其餘值（false／0／no／off／空）→ 冇用到
        return false;
    }
}
