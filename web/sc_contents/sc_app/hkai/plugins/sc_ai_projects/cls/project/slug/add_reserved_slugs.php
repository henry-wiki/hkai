<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\slug;

// == sc_ai_projects project add_reserved_slugs：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-09-27 自 sc_ai_projects_slug.php 分遷入（one file one action） ==

trait add_reserved_slugs
{
    public function add_reserved_slugs($reserved_slugs)
    {
        // 逐個登記（小寫去空白）
        foreach ($reserved_slugs as $reserved_slug_item) {
            $reserved_slug_item = strtolower(trim($reserved_slug_item));

            // 空字串 → 不登記
            if ($reserved_slug_item === "") {
                continue;
            }

            // 未登記過 → 加入（避免重複）
            if (!in_array($reserved_slug_item, $this->reserved_slugs, true)) {
                $this->reserved_slugs[] = $reserved_slug_item;
            }
        }

        return $this;
    }
}
