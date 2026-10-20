<?php

namespace sc\app\hkai\plugins\sc_ai_settings\settings\config;

// == sc_ai_settings settings get_initial_data：settings 域動作（trait——掛入 settings_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat main_Class 分遷入（one file one action） ==

trait get_initial_data
{
    // 取得 initial data（第一次呼叫才執行 initial；$idx null → 全部）
    public function get_initial_data($idx = null)
    {
        if (!$this->is_initial) {
            $this->is_initial = 1;
            $this->initial();
        }

        if ($idx === null) {
            return $this->initial_data;
        }

        return $this->initial_data[$idx];
    }
}
