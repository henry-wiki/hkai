<?php

namespace sc\app\hkai\plugins\sc_ai_providers\repo\repo_select;

// == sc_ai_providers repo get_provider_instance：域動作（trait——掛入 repo_Class；檔＝動作） ==
// == 2026-10-06 新增（共用分派——caller 先經 cls/repo；解析入口＝main()->get_provider_instance） ==

trait get_provider_instance
{
    // provider 實例（共用分派——先經 cls/repo，解析入口喺 main 域）
    public function get_provider_instance($provider_id)
    {
        return sc_ai_providers()->main()->get_provider_instance($provider_id);
    }
}
