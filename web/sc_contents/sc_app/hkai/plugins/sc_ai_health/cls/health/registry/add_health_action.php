<?php

namespace sc\app\hkai\plugins\sc_ai_health\health\registry;

// == sc_ai_health health add_health_action：health 域動作（trait——掛入 health_Class；檔＝動作） ==
// == 2026-09-29 新設（＝ add_health_report 嘅對稱：報告＝讀（入 reports）、動作＝做（唔入回應）） ==

trait add_health_action
{
    // 登記 health 動作（name＋callback——一名一動作；每次 health 檢查即時執行，回傳值一律丟棄）
    // name＝登記身份（防重名用——動作唔輸出到 JSON）；重複登記即刻報錯
    // 用法：sc_ai_health()->health()->add_health_action("finalize_stale_job", function () { return …; })
    public function add_health_action($name, $callback)
    {
        // 判定：同名已登記 → 報錯（一名一來源——SSOT；防兩處各自覆寫）
        if (isset($this->external_health_actions[$name])) {
            throw new \InvalidArgumentException("Duplicate health action name: " . $name);
        }

        $this->external_health_actions[$name] = $callback;

        return $this;
    }
}
