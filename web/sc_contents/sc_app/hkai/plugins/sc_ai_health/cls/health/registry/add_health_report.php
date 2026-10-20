<?php

namespace sc\app\hkai\plugins\sc_ai_health\health\registry;

// == sc_ai_health health add_health_report：health 域動作（trait——掛入 health_Class；檔＝動作） ==

trait add_health_report
{
    // 登記 health 報告（name＋callback——一名一報告；每次 health 檢查即時解析後輸出）
    // name＝輸出 JSON key（SSOT——登記一次、處處同名）；重複登記即刻報錯
    // 用法：sc_ai_health()->health()->add_health_report("bridge", function () { return …; })
    public function add_health_report($name, $callback)
    {
        // 判定：同名已登記 → 報錯（一名一來源——SSOT；防兩處各自覆寫）
        if (isset($this->external_health_reports[$name])) {
            throw new \InvalidArgumentException("Duplicate health report name: " . $name);
        }

        $this->external_health_reports[$name] = $callback;

        return $this;
    }
}
