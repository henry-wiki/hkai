<?php

namespace sc\app\hkai\plugins\sc_ai_settings\about\action;

// == sc_ai_settings about check_latest_release：查 GitHub 最新 release（1 小時快取）
// == 上游：handle_fetch_about（About 分頁更新狀態）
// == 下游：GitHub REST API（releases/latest）→ data/cache/github_latest_release.json

trait check_latest_release
{
    // 回 ["status" => up_to_date|update_available|unknown, "latest" => tag]
    public function check_latest_release($current_version)
    {
        $cache_file = $this->get_release_cache_file();
        $cache_data = $this->read_release_cache($cache_file);

        // 判定：快取未過期 → 直接採用
        if ($cache_data !== null) {
            return $this->build_release_result($current_version, $cache_data["latest"]);
        }

        $latest_version = $this->fetch_latest_release_tag();

        // 判定：查唔到（離線／rate limit）→ unknown，唔寫快取（下次再試）
        if ($latest_version === "") {
            return ["status" => "unknown", "latest" => ""];
        }

        $this->write_release_cache($cache_file, $latest_version);

        return $this->build_release_result($current_version, $latest_version);
    }

    // 快取檔位置（data/cache/github_latest_release.json）
    private function get_release_cache_file()
    {
        $database_file = sc_get_config("storage.database_file");
        $cache_dir     = dirname($database_file, 2) . "/cache";

        // 判定：快取目錄唔存在 → 建立
        if (is_dir($cache_dir) === false) {
            @mkdir($cache_dir, 0777, true);
        }

        return $cache_dir . "/github_latest_release.json";
    }

    // 讀快取（過期／壞檔 → null）
    private function read_release_cache($cache_file)
    {
        // 判定：快取唔存在 → null
        if (is_file($cache_file) === false) {
            return null;
        }

        $cache_json = @file_get_contents($cache_file);
        $cache_data = json_decode((string)$cache_json, true);

        // 判定：解析失敗 → null
        if (is_array($cache_data) === false) {
            return null;
        }

        // 判定：欄位唔齊 → null
        if (isset($cache_data["checked_at"], $cache_data["latest"]) === false) {
            return null;
        }

        $cache_age_sec = time() - (int)$cache_data["checked_at"];

        // 判定：超過 1 小時 → null（重新查）
        if ($cache_age_sec > 3600) {
            return null;
        }

        return $cache_data;
    }

    // 寫快取
    private function write_release_cache($cache_file, $latest_version)
    {
        $cache_data = [
            "checked_at" => time(),
            "latest"     => $latest_version,
        ];

        @file_put_contents($cache_file, json_encode($cache_data));
    }

    // 查 GitHub API（2 秒 timeout；失敗回空字串）
    private function fetch_latest_release_tag()
    {
        $api_url = "https://api.github.com/repos/henry-wiki/hkai/releases/latest";

        $http_context = stream_context_create([
            "http" => [
                "method"  => "GET",
                "timeout" => 2,
                "header"  => "User-Agent: HKAI-About\r\nAccept: application/vnd.github+json\r\n",
            ],
        ]);

        $response_text = @file_get_contents($api_url, false, $http_context);

        // 判定：請求失敗 → 空字串
        if ($response_text === false) {
            return "";
        }

        $response_data = json_decode($response_text, true);

        // 判定：解析失敗 → 空字串
        if (is_array($response_data) === false) {
            return "";
        }

        $latest_version = trim((string)($response_data["tag_name"] ?? ""));

        return $latest_version;
    }

    // 比較版本（回 status ＋ latest）
    private function build_release_result($current_version, $latest_version)
    {
        // 判定：冇最新版本 → unknown
        if ($latest_version === "") {
            return ["status" => "unknown", "latest" => ""];
        }

        $current_version_clean = ltrim($current_version, "vV");
        $latest_version_clean  = ltrim($latest_version, "vV");

        // 判定：GitHub 版本較新 → update_available
        if (version_compare($latest_version_clean, $current_version_clean, ">") === true) {
            return ["status" => "update_available", "latest" => $latest_version];
        }

        return ["status" => "up_to_date", "latest" => $latest_version];
    }
}
