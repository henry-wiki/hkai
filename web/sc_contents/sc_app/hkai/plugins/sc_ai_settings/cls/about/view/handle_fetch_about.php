<?php

namespace sc\app\hkai\plugins\sc_ai_settings\about\view;

// == sc_ai_settings about handle_fetch_about：about 域動作（trait——掛入 about_Class；檔＝動作） ==
// == 職責：關於 HKAI 分頁（後端渲染；回 {ok, html:{header, body}}——加減欄位＝改 sc_config/hkai/about_hkai.php） ==

trait handle_fetch_about
{
    // GET fetch_about handler（About 分頁——右欄內容；左選單由 settings 體系維持）
    public function handle_fetch_about()
    {
        // about 資料源（sc_config/hkai/about_hkai.php——唯一來源；靜態＋動態皆喺檔內產生）
        $about = require hkai__core()->get_about_file();

        // 從 array 取值先落同名變數
        $about_name          = $about["name"];
        $about_version       = $about["version"];
        $about_build         = $about["build"];
        $about_tech          = $about["tech"];
        $about_update_status = $about["update_status"];
        $about_copyright     = $about["copyright"];
        $about_author        = $about["author"];
        $about_supported_by  = $about["supported_by"];

        // 顯示文字（i18n——跟有效設定語言走）
        $about_title_label        = hkai__i18n()->get("settings.about.title");
        $about_version_label      = hkai__i18n()->get("settings.about.version");
        $about_build_label        = hkai__i18n()->get("settings.about.build");
        $about_tech_label         = hkai__i18n()->get("settings.about.tech");
        $about_updated_label      = hkai__i18n()->get("settings.about.updated");
        $about_tagline            = hkai__i18n()->get("settings.about.tagline");
        $about_check_update_label = hkai__i18n()->get("settings.about.check_update");
        $about_author_label       = hkai__i18n()->get("settings.about.author");
        $about_supported_by_label = hkai__i18n()->get("settings.about.supported_by");

        // 品牌 logo（app 資產——磁碟路徑正規化後轉 web URL）
        $brand_logo_src = sc_to_web_path(SC_DIR_APP . "/hkai/asset/hkai-logo.png");

        // === header ===
        $modal_header   = [];
        $modal_header[] = "<div class='modal__title'>" . $about_title_label . "</div>";

        // === body ===
        $modal_body   = [];
        $modal_body[] = "<div class='modal__content-main'>";
            $modal_body[] = "<div class='sc_about'>";
                $modal_body[] = "<div class='sc_about__head'>";
                    $modal_body[] = "<img class='sc_about__logo' src='{$brand_logo_src}' alt='{$about_name}'/>";
                    $modal_body[] = "<div class='sc_about__title'>";
                        $modal_body[] = "<b class='sc_about__name'>{$about_name}</b>";
                        $modal_body[] = "<span class='sc_about__tagline'>{$about_tagline}</span>";
                    $modal_body[] = "</div>";
                $modal_body[] = "</div>";
                $modal_body[] = "<div class='sc_about__rows'>";
                    $modal_body[] = "<div class='sc_about__row'>";
                        $modal_body[] = "<span class='sc_about__label'>" . $about_version_label . "</span>";
                        $modal_body[] = "<span class='sc_about__value'>{$about_version}</span>";
                    $modal_body[] = "</div>";
                    $modal_body[] = "<div class='sc_about__row'>";
                        $modal_body[] = "<span class='sc_about__label'>" . $about_build_label . "</span>";
                        $modal_body[] = "<span class='sc_about__value'>{$about_build}</span>";
                    $modal_body[] = "</div>";
                    $modal_body[] = "<div class='sc_about__row'>";
                        $modal_body[] = "<span class='sc_about__label'>" . $about_tech_label . "</span>";
                        $modal_body[] = "<span class='sc_about__value'>{$about_tech}</span>";
                    $modal_body[] = "</div>";
                    $modal_body[] = "<div class='sc_about__row'>";
                        $modal_body[] = "<span class='sc_about__label'>" . $about_updated_label . "</span>";
                        $modal_body[] = "<span class='sc_about__value'>{$about_update_status}</span>";
                        $modal_body[] = "<button class='sc_about__check' type='button' data-cmd='coming_soon' title='" . $about_check_update_label . "'>" . $about_check_update_label . "</button>";
                    $modal_body[] = "</div>";
                    $modal_body[] = "<div class='sc_about__row'>";
                        $modal_body[] = "<span class='sc_about__label'>" . $about_author_label . "</span>";
                        $modal_body[] = "<span class='sc_about__value'>{$about_author}</span>";
                    $modal_body[] = "</div>";
                    $modal_body[] = "<div class='sc_about__row'>";
                        $modal_body[] = "<span class='sc_about__label'>" . $about_supported_by_label . "</span>";
                        $modal_body[] = "<span class='sc_about__value'>{$about_supported_by}</span>";
                    $modal_body[] = "</div>";
                $modal_body[] = "</div>";
                $modal_body[] = "<div class='sc_about__foot'>";
                    $modal_body[] = "<span class='sc_about__copyright'>{$about_copyright}</span>";
                $modal_body[] = "</div>";
            $modal_body[] = "</div>";
        $modal_body[] = "</div>";

        $html = [
            "header" => implode("\r\n", $modal_header),
            "body"   => implode("\r\n", $modal_body),
        ];

        return sc_response()->json(["ok" => true, "html" => $html]);
    }
}
