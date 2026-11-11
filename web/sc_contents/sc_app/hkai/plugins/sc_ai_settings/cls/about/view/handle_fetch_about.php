<?php

namespace sc\app\hkai\plugins\sc_ai_settings\about\view;

// == sc_ai_settings about handle_fetch_about：about 域動作（trait——掛入 about_Class；檔＝動作） ==
// == 職責：關於 HKAI 分頁（後端渲染；回 {ok, html:{header, body}}——hero＋內層 tab＋資料卡＋快速連結＋footer） ==
// == 上游：request/about/fetch_about.php（route 動作） ==
// == 下游：sc_config/hkai/about_hkai.php（資料源）；check_latest_release（更新狀態）；i18n（標籤） ==

trait handle_fetch_about
{
    // GET fetch_about handler（About 分頁——右欄內容；左選單由 settings 體系維持）
    public function handle_fetch_about()
    {
        // about 資料源（sc_config/hkai/about_hkai.php——唯一來源）
        $about = require sc_get_config("hkai.about_file");

        // 轉義一次過（整個資料結構交 sc_html_encode——顯示層不再逐欄處理）
        $about = sc_html_encode($about);

        // 品牌 logo（app 資產——磁碟路徑正規化後轉 web URL）
        $brand_logo_src = sc_to_web_path(SC_DIR_APP . "/hkai/asset/hkai-logo.png");

        $about_title_label = hkai()->i18n()->get("settings.about.title");

        // === header ===
        $modal_header   = [];
        $modal_header[] = "<div class='modal__title'>" . $about_title_label . "</div>";

        // === body ===
        $modal_body   = [];
        $modal_body[] = "<div class='modal__content-main'>";
        $modal_body[] = "<div class='sc_about'>";

        $about_body_lines = $this->build_about_body($about, $brand_logo_src);
        $modal_body       = array_merge($modal_body, $about_body_lines);

        $modal_body[] = "</div>";
        $modal_body[] = "</div>";

        $html = [
            "header" => implode("\r\n", $modal_header),
            "body"   => implode("\r\n", $modal_body),
        ];

        return sc_response()->json(["ok" => true, "html" => $html]);
    }

    // 分頁內容組裝（hero → tab 列 → 各面板 → footer；順序＝畫面由上而下）
    private function build_about_body($about, $brand_logo_src)
    {
        $about_lines = [];

        $about_hero_lines = $this->build_about_hero($about, $brand_logo_src);
        $about_lines      = array_merge($about_lines, $about_hero_lines);

        $about_tab_lines = $this->build_about_tabs($about);
        $about_lines     = array_merge($about_lines, $about_tab_lines);

        $about_panel_lines = $this->build_about_panels($about);
        $about_lines       = array_merge($about_lines, $about_panel_lines);

        $about_footer_lines = $this->build_about_footer($about);
        $about_lines        = array_merge($about_lines, $about_footer_lines);

        return $about_lines;
    }

    // hero 頭（logo＋名稱＋版本徽章＋tagline＋狀態＋檢查更新鈕）
    private function build_about_hero($about, $brand_logo_src)
    {
        $about_name       = $about["name"];
        $about_version    = $about["version"];
        $about_update_url = $about["update_url"];

        $about_tagline            = hkai()->i18n()->get("settings.about.tagline");
        $about_check_update_label = hkai()->i18n()->get("settings.about.check_update");

        $about_version_badge = "v" . $about_version;

        // 更新狀態（GitHub releases——1 小時快取）
        $about_release_result = $this->check_latest_release($about_version);
        $about_release_status = $about_release_result["status"];
        $about_release_latest = $about_release_result["latest"];
        $about_update_status  = hkai()->i18n()->get("settings.about.up_to_date");
        $about_status_class   = "sc_about__status--ok";

        // 判定：有新版本 → 顯示版本號＋警告色
        if ($about_release_status === "update_available") {
            $about_update_available_text = hkai()->i18n()->get("settings.about.update_available");

            $about_update_status = sprintf($about_update_available_text, $about_release_latest);
            $about_status_class  = "sc_about__status--warn";
        }

        // 判定：查唔到 → 顯示未能檢查＋淡化
        if ($about_release_status === "unknown") {
            $about_update_status = hkai()->i18n()->get("settings.about.update_unknown");
            $about_status_class  = "sc_about__status--muted";
        }

        $about_lines   = [];
        $about_lines[] = "<div class='sc_about__hero'>";
        $about_lines[] = "<img class='sc_about__logo' src='{$brand_logo_src}' alt='{$about_name}'/>";
        $about_lines[] = "<div class='sc_about__hero_text'>";
        $about_lines[] = "<div class='sc_about__name_row'>";
        $about_lines[] = "<b class='sc_about__name'>{$about_name}</b>";
        $about_lines[] = "<span class='sc_about__version_badge'>{$about_version_badge}</span>";
        $about_lines[] = "</div>";
        $about_lines[] = "<span class='sc_about__tagline'>{$about_tagline}</span>";
        $about_lines[] = "<div class='sc_about__actions'>";
        $about_lines[] = "<span class='sc_about__status {$about_status_class}'>{$about_update_status}</span>";
        $about_lines[] = "<a class='sc_about__action' href='{$about_update_url}' target='_blank' rel='noopener noreferrer'>";
        $about_lines[] = "<i class='fa-solid fa-rotate'></i>";
        $about_lines[] = "<span>{$about_check_update_label}</span>";
        $about_lines[] = "</a>";
        $about_lines[] = "</div>";
        $about_lines[] = "</div>";
        $about_lines[] = "</div>";

        return $about_lines;
    }

    // tab 列（四個 tab 恆在——關於／鳴謝名單／技術與開源／贊助名單）
    private function build_about_tabs($about)
    {
        $about_thanks_count   = count($about["thanks"]);
        $about_credits_count  = count($about["credits"]);
        $about_sponsors_count = count($about["sponsors"]);

        $about_lines   = [];
        $about_lines[] = "<div class='sc_about__tabs'>";

        $about_tab_lines = $this->build_about_tab_button("about", "fa-solid fa-circle-info", "settings.about.tab.about", 0, true);
        $about_lines     = array_merge($about_lines, $about_tab_lines);

        $about_tab_lines = $this->build_about_tab_button("thanks", "fa-solid fa-heart", "settings.about.tab.thanks", $about_thanks_count, false);
        $about_lines     = array_merge($about_lines, $about_tab_lines);

        $about_tab_lines = $this->build_about_tab_button("credits", "fa-solid fa-code", "settings.about.tab.credits", $about_credits_count, false);
        $about_lines     = array_merge($about_lines, $about_tab_lines);

        $about_tab_lines = $this->build_about_tab_button("sponsors", "fa-solid fa-handshake", "settings.about.tab.sponsors", $about_sponsors_count, false);
        $about_lines     = array_merge($about_lines, $about_tab_lines);

        $about_lines[] = "</div>";

        return $about_lines;
    }

    // 單個 tab 按鈕（計數 0 唔出徽章；active＝首個）
    private function build_about_tab_button($tab_key, $tab_icon, $tab_label_key, $tab_count, $tab_active)
    {
        $tab_label = hkai()->i18n()->get($tab_label_key);

        $tab_class = "sc_about__tab";
        // 判定：首個 tab → 加 active
        if ($tab_active) {
            $tab_class = "sc_about__tab active";
        }

        $about_lines   = [];
        $about_lines[] = "<button type='button' class='{$tab_class}' data-about_tab='{$tab_key}'>";
        $about_lines[] = "<i class='{$tab_icon}'></i>";
        $about_lines[] = "<span>{$tab_label}</span>";

        // 判定：有資料 → 出計數徽章
        if ($tab_count > 0) {
            $about_lines[] = "<span class='sc_about__tab_count'>{$tab_count}</span>";
        }

        $about_lines[] = "</button>";

        return $about_lines;
    }

    // 各面板（關於恆在；其餘名單空＝出空狀態）
    private function build_about_panels($about)
    {
        $about_lines   = [];
        $about_lines[] = "<div class='sc_about__panel' data-about_panel='about'>";

        $about_fact_lines = $this->build_about_facts($about["facts"]);
        $about_lines      = array_merge($about_lines, $about_fact_lines);

        $about_shortcut_lines = $this->build_about_quick_links($about["quick_links"]);
        $about_lines          = array_merge($about_lines, $about_shortcut_lines);

        $about_lines[] = "</div>";

        $about_thanks_lines = $this->build_about_list_panel("thanks", $about["thanks"]);
        $about_lines        = array_merge($about_lines, $about_thanks_lines);

        $about_credits_lines = $this->build_about_list_panel("credits", $about["credits"]);
        $about_lines         = array_merge($about_lines, $about_credits_lines);

        $about_sponsor_lines = $this->build_about_list_panel("sponsors", $about["sponsors"]);
        $about_lines         = array_merge($about_lines, $about_sponsor_lines);

        return $about_lines;
    }

    // 資料卡格（兩欄；每張＝label＋value）
    private function build_about_facts($about_facts)
    {
        $about_lines   = [];
        $about_lines[] = "<div class='sc_about__facts'>";

        // 逐張資料卡
        foreach ($about_facts as $about_fact_item) {
            $about_fact_label_key = $about_fact_item["label_key"];
            $about_fact_label     = hkai()->i18n()->get($about_fact_label_key);
            $about_fact_value     = $about_fact_item["value"];

            $about_lines[] = "<div class='sc_about__fact'>";
            $about_lines[] = "<span class='sc_about__fact_label'>{$about_fact_label}</span>";
            $about_lines[] = "<span class='sc_about__fact_value'>{$about_fact_value}</span>";
            $about_lines[] = "</div>";
        }

        $about_lines[] = "</div>";

        return $about_lines;
    }

    // 快速連結列（每粒＝icon＋label；新視窗開）
    private function build_about_quick_links($about_quick_links)
    {
        $about_quick_links_label = hkai()->i18n()->get("settings.about.quick_links");

        $about_lines   = [];
        $about_lines[] = "<div class='sc_about__subtitle'>{$about_quick_links_label}</div>";
        $about_lines[] = "<div class='sc_about__shortcuts'>";

        // 逐粒快速連結
        foreach ($about_quick_links as $about_link_item) {
            $about_link_label_key = $about_link_item["label_key"];
            $about_link_label     = hkai()->i18n()->get($about_link_label_key);
            $about_link_icon      = $about_link_item["icon"];
            $about_link_url       = $about_link_item["url"];

            $about_lines[] = "<a class='sc_about__shortcut' href='{$about_link_url}' target='_blank' rel='noopener noreferrer'>";
            $about_lines[] = "<i class='{$about_link_icon}'></i>";
            $about_lines[] = "<span>{$about_link_label}</span>";
            $about_lines[] = "</a>";
        }

        $about_lines[] = "</div>";

        return $about_lines;
    }

    // 名單面板（鳴謝／開源／贊助共用——空 array＝面板出空狀態）
    private function build_about_list_panel($panel_key, $panel_items)
    {
        $panel_count = count($panel_items);

        // 判定：無資料 → 只出空狀態（面板照出——tab 恆在）
        if ($panel_count === 0) {
            $panel_empty_text = hkai()->i18n()->get("settings.about.empty_state");

            $about_lines   = [];
            $about_lines[] = "<div class='sc_about__panel' data-about_panel='{$panel_key}' hidden>";
            $about_lines[] = sc_empty_state($panel_empty_text, false);
            $about_lines[] = "</div>";

            return $about_lines;
        }

        $about_lines   = [];
        $about_lines[] = "<div class='sc_about__panel' data-about_panel='{$panel_key}' hidden>";
        $about_lines[] = "<div class='sc_about__list'>";

        // 逐個名單項目
        foreach ($panel_items as $panel_item) {
            $panel_item_name = $panel_item["name"];
            $panel_item_note = $panel_item["note"];
            $panel_item_url  = $panel_item["url"];

            $panel_item_lines = $this->build_about_list_item($panel_item_name, $panel_item_note, $panel_item_url);
            $about_lines      = array_merge($about_lines, $panel_item_lines);
        }

        $about_lines[] = "</div>";
        $about_lines[] = "</div>";

        return $about_lines;
    }

    // 單個名單項目（有 url → a；無 url → div）
    private function build_about_list_item($panel_item_name, $panel_item_note, $panel_item_url)
    {
        // 判定：有連結 → 輸出 a 標籤
        if ($panel_item_url !== "") {
            return [
                "<a class='sc_about__list_item' href='{$panel_item_url}' target='_blank' rel='noopener noreferrer'>",
                "<span class='sc_about__list_name'>{$panel_item_name}</span>",
                "<span class='sc_about__list_note'>{$panel_item_note}</span>",
                "</a>",
            ];
        }

        return [
            "<div class='sc_about__list_item'>",
            "<span class='sc_about__list_name'>{$panel_item_name}</span>",
            "<span class='sc_about__list_note'>{$panel_item_note}</span>",
            "</div>",
        ];
    }

    // footer（版權＋完整鳴謝與授權頁連結）
    private function build_about_footer($about)
    {
        $about_copyright   = $about["copyright"];
        $about_credits_url = $about["credits_url"];

        $about_footer_link_label = hkai()->i18n()->get("settings.about.footer_link");

        $about_lines   = [];
        $about_lines[] = "<div class='sc_about__foot'>";
        $about_lines[] = "<span class='sc_about__copyright'>{$about_copyright}</span>";
        $about_lines[] = "<a class='sc_about__foot_link' href='{$about_credits_url}' target='_blank' rel='noopener noreferrer'>";
        $about_lines[] = "<i class='fa-solid fa-arrow-up-right-from-square'></i>";
        $about_lines[] = "<span>{$about_footer_link_label}</span>";
        $about_lines[] = "</a>";
        $about_lines[] = "</div>";

        return $about_lines;
    }
}
