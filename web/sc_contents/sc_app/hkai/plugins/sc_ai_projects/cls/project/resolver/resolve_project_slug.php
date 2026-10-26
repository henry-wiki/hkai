<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\resolver;

// == sc_ai_projects project resolve_project_slug：project 域解析（trait——掛入 project_Class；檔＝動作） ==
// == 職責：專案段 → ["archive" => 1／0, "project_slug" => 純 slug]（語境形 archive@<slug>）
// == 上游：各 request／view／init（查表一律用純 slug）

trait resolve_project_slug
{

    // 解析專案段（explode "@"——index 0＝archive 即標記；index 1＝project_slug）
    public function resolve_project_slug($raw_project_slug)
    {
        $key_parts = explode("@", $raw_project_slug);

        $resolved = [];

        // 判定：index 0 係 archive（純 archive／archive@slug）→ 標記 1；否則 0
        if ($key_parts[0] === "archive") {
            $resolved["archive"] = 1;
        } else {
            $resolved["archive"] = 0;
        }

        // 判定：有語境段（@ 後有值）→ slug 取 index 1；否則原值
        if (count($key_parts) > 1) {
            $parsed_project_slug = $key_parts[1];
        } else {
            $parsed_project_slug = $key_parts[0];
        }

        // 判定：空白 → reset 做預設專案（只有呢個情況 reset；其餘原樣）
        if ($parsed_project_slug === "") {
            $resolved["project_slug"] = sc_get_config("hkai.default_project_slug");
        } else {
            $resolved["project_slug"] = $parsed_project_slug;
        }

        return $resolved;
    }

}
