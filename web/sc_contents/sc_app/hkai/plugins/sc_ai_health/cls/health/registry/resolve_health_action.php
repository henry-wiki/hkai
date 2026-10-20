<?php

namespace sc\app\hkai\plugins\sc_ai_health\health\registry;

// == sc_ai_health health resolve_health_action：health 域動作（trait——掛入 health_Class；檔＝動作） ==
// == 2026-09-29 新設（＝ resolve_health_report 嘅對稱：動作只執行，回傳值丟棄） ==

trait resolve_health_action
{
    // 執行 health 動作（Closure → 當下執行；回傳值丟棄——動作唔入回應）
    private function resolve_health_action($health_action_callback)
    {
        // 判定：唔係 Closure → 唔執行（登記契約＝callback）
        if (!($health_action_callback instanceof \Closure)) {
            return;
        }

        $health_action_callback();
    }
}
