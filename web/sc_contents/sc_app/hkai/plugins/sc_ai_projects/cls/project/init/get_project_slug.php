<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\init;

// == sc_ai_projects project get_project_slug：取當前 project_slug（trait——掛入 project_Class；檔＝動作）
// == 職責：自行判斷情境——POST（request）＝payload；非 POST（頁面）＝$_GET 再 fallback URL 第一段
// == 上游：page view／render、fetch 端點；下游：$_POST／$_GET／sc_get_friendly()

trait get_project_slug
{
    // 取 project_slug（POST＝payload；頁面＝$_GET → URL 第一段）
    public function get_project_slug()
    {
        $is_post_request = ($_SERVER["REQUEST_METHOD"] === "POST");

        $project_slug = "";

        // 判定：POST（request）→ 讀 payload 嘅 project_slug
        if ($is_post_request === true) {
            $input_data = sc_request()->json();

            // 判定：表單欄位有值 → 直接採用
            if (isset($_POST["project_slug"])) {
                $project_slug = $_POST["project_slug"];
            } else if (isset($input_data["project_slug"])) {
                // JSON body（fetch 端點用 JSON——同一份 payload）
                $project_slug = $input_data["project_slug"];
            }
        } else if (isset($_GET["project_slug"])) {
            // 頁面情境：網址參數優先
            $project_slug = $_GET["project_slug"];
        } else {
            // 頁面情境：退回 URL 第一段
            $project_slug = sc_get_friendly(0);
        }

        // 判定：空字串 → 用預設專案
        if ($project_slug === "") {
            $project_slug = sc_get_config("hkai.default_project_slug");
        }

        return $project_slug;
    }
}
