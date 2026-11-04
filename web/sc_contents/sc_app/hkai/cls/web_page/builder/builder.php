<?php

namespace sc\app\hkai\cls\web_page\builder;

// == builder_Class：整頁 HTML 組裝（singleton——經 hkai()->web_page()->builder() 取得） ==
// == 職責：render_page_index($controller)——整頁（DOCTYPE～</html>——唯一出口）；render_page_head($controller)——<head> 內容（meta／CSRF／資源嵌入）；render_page_body($controller)——<body> 內容（最左功能列＋兩側欄＋訊息主區） ==

class builder_Class
{
    private static $instance = null;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }

    public function __construct()
    {}

    // <head> 內容（meta＋資源＋語境 island——資源註冊後由 get_embed_assets／get_embed_jsmods 統一輸出）
    public function render_page_head($controller)
    {
        // 資源註冊（css／CDN／ESM entry）
        sc_web_asset()->register_assets("hkai", [
            "main_css"       => $controller->get_web_path("/styles/css/main.css"),
            "sidebar_css"    => $controller->get_web_path("/jsmods/sc_sidebar/sc_sidebar.css"),
            "modal_css"      => $controller->get_web_path("/jsmods/sc_modal/sc_modal.css"),
            "loading_css"    => $controller->get_web_path("/jsmods/sc_loading/sc_loading.css"),

            // Markdown 渲染鏈（本地 vendor——同 jQuery/Bootstrap 同一出處；module 為 defer 語義，執行時全域已就緒）
            "marked_js"      => "/web/sc_contents/sc_resources/vendor/marked/18/marked.umd.js",
            "purify_js"      => "/web/sc_contents/sc_resources/vendor/dompurify/3/purify.min.js",
            "highlight_css"  => "/web/sc_contents/sc_resources/vendor/highlightjs/11/styles/github-dark.min.css",
            "highlight_js"   => "/web/sc_contents/sc_resources/vendor/highlightjs/11/highlight.min.js",
            "mermaid_js"     => "/web/sc_contents/sc_resources/vendor/mermaid/11/mermaid.min.js",
        ]);

        // js module（ESM entry——載入 registry，其靜態 import 全部模組）
        sc_web_asset()->register_jsmods("hkai", [
            "bootstrap" => $controller->get_web_path("/jsmods/bootstrap.js"),
        ]);

        // 各 enabled plugins 宣告的 assets 併入同一輸出組（css 保留；plugin js 由 manifest 動態載入）
        foreach ($controller->get_enabled_plugins() as $plugin) {
            sc_web_asset()->register_assets("hkai", $plugin->get_assets());
        }

        // favicon 基地網址（asset/favicon/——Logo 全套；由 Apache 直接服務）
        $favicon_base_url = $controller->get_web_path("/asset/favicon");

        $outputLines   = [];
        $outputLines[] = "<meta charset='UTF-8'/>";
        $outputLines[] = "<meta name='viewport' content='width=1430'/>";
        $outputLines[] = "<meta name='csrf-token' content='" . CSRF_get_token() . "'/>";

        // i18n 字典 island（同一份 JSON——前端同步讀取，零請求、零 race）
        $lang_data_json = json_encode(
            hkai()->i18n()->get_lang_data(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        $outputLines[]  = "<script type='application/json' id='i18n-dict'>" . $lang_data_json . "</script>";

        // 本頁 viewer 參照（SSE 更新時回寫此檔；每頁一個 uuidv7）
        $outputLines[] = "<meta name='view-id' content='" . sc_stream()->snapshot()->get_view_id() . "'/>";

        // SSE 串流設定（一律啟用；base_url 由 runtime server_settings 即時組——唔寫死）
        $stream_config      = ["enabled" => true];
        // SSE base_url（runtime server_settings 即時組）
        $runtime_configs = hkai()->runtime()->get_runtime_configs();
        $server_settings = $runtime_configs["server_settings"] ?? [];
        $server_host     = (string)($server_settings["host"] ?? "127.0.0.1");
        $server_port     = (int)($server_settings["port"] ?? 0);
        $stream_config["base_url"] = "http://" . $server_host . ":" . $server_port;
        $stream_config_json = json_encode($stream_config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $outputLines[]      = "<meta name='stream-config' content='" . sc_html_encode($stream_config_json) . "'/>";
        $outputLines[] = "<title>hkai agent console</title>";
        // favicon 全套（ico＋各尺寸 PNG＋apple-touch——asset/favicon/）
        $outputLines[] = "<link rel='icon' href='{$favicon_base_url}/favicon.ico' sizes='any'/>";
        $outputLines[] = "<link rel='icon' type='image/png' sizes='16x16' href='{$favicon_base_url}/favicon-16x16.png'/>";
        $outputLines[] = "<link rel='icon' type='image/png' sizes='32x32' href='{$favicon_base_url}/favicon-32x32.png'/>";
        $outputLines[] = "<link rel='icon' type='image/png' sizes='48x48' href='{$favicon_base_url}/favicon-48x48.png'/>";
        $outputLines[] = "<link rel='icon' type='image/png' sizes='96x96' href='{$favicon_base_url}/favicon-96x96.png'/>";
        $outputLines[] = "<link rel='apple-touch-icon' sizes='180x180' href='{$favicon_base_url}/apple-touch-icon.png'/>";

        // 資源（load_assets("hkai") 取出整組＋自動併入 global "*" 組；get_embed_assets 產 link/script——css 走傳統、js 已停用）
        $outputLines[] = sc_web_asset()->get_embed_assets(sc_web_asset()->load_assets("hkai"));

        // ESM module 入口（jQuery 以 CDN 傳統 script 先行載入——assets.func.php "*" 組；module 為 defer 語義——執行時 window.jQuery 已就緒）
        $outputLines[] = sc_web_asset()->get_embed_jsmods(sc_web_asset()->load_jsmods("hkai"));

        return implode("\r\n", $outputLines);
    }

    // <body> 內容（最左功能列＋專案側欄＋對話側欄＋訊息主區——全由各 plugin view 提供；內容由 JS 動態載入）
    // 狀態看守初始值（頁面唯一一次——polling 現值；之後只由 SSE 變化更新）
    public function render_state_watch_seed()
    {
        $polling_snapshot = sc_stream()->monitor()->polling()->get_live_snapshot();

        $outputLines   = [];
        $outputLines[] = "<div class='sc_state_watch' hidden>";

        // 判定：未有快照 → 只出空容器
        if ($polling_snapshot === null) {
            $outputLines[] = "</div>";

            return implode("\r\n", $outputLines);
        }

        $polling_state  = $polling_snapshot["state"] ?? [];
        $polling_entries = $polling_state["entries"] ?? [];
        $project_counts  = $polling_entries["project"] ?? [];
        $chat_counts     = $polling_entries["chat"] ?? [];

        $outputLines[] = "    <div data-target='polling'>";

        foreach ($project_counts as $project_slug => $project_count) {
            $project_count_text = (string)$project_count;

            $outputLines[] = "        <span data-unit='project' data-field='{$project_slug}'>{$project_count_text}</span>";
        }

        foreach ($chat_counts as $project_slug => $project_chat_counts) {
            foreach ($project_chat_counts as $chat_sid => $chat_count) {
                $chat_count_text = (string)$chat_count;

                $outputLines[] = "        <span data-unit='chat' data-field='{$chat_sid}'>{$chat_count_text}</span>";
            }
        }

        $outputLines[] = "    </div>";
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }

    public function render_page_body($controller)
    {
        $outputLines   = [];
        $outputLines[] = $this->render_state_watch_seed();
        $outputLines[] = $this->render_page_rail($controller);
        $outputLines[] = sc_ai_projects()->project()->render_project_sidebar();
        $outputLines[] = sc_ai_chats()->chat()->render_chat_sidebar();
        $outputLines[] = sc_ai_chats()->message()->view()->render_message_body();

        return implode("\r\n", $outputLines);
    }

    // 最左功能列（App Rail——永遠存在；logo 喺最頂、收合掣其後；最底＝設定＋說明；亮燈純 CSS 跟 body 收合旗標——清單顯示緊＝亮）
    public function render_page_rail($controller)
    {
        // 品牌 logo（app 資產——統一經 get_web_path 出 web URL）
        $brand_logo_src = $controller->get_web_path("/asset/hkai-logo.png");

        $outputLines   = [];
        $outputLines[] = "<nav class='sc_rail' aria-label='主要功能'>";
            $outputLines[] = "<a class='sc_rail__brand' href='/' title='" . hkai()->i18n()->get("common.back_to_workspace") . "'>";
                $outputLines[] = "<img class='sc_rail__brand_mark' src='{$brand_logo_src}' alt='hkai'/>";
            $outputLines[] = "</a>";
            $outputLines[] = "<button class='sc_rail__btn' type='button' data-action='toggle_all_sidebars' title='收合全部欄' aria-expanded='true'>";
                $outputLines[] = "<svg class='sc_rail__icon_close' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'>";
                    $outputLines[] = "<rect x='3.5' y='4' width='17' height='16' rx='2'/>";
                    $outputLines[] = "<path d='M9 4v16'/>";
                    $outputLines[] = "<path d='M15 9l-3 3 3 3'/>";
                $outputLines[] = "</svg>";
                $outputLines[] = "<svg class='sc_rail__icon_open' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'>";
                    $outputLines[] = "<rect x='3.5' y='4' width='17' height='16' rx='2'/>";
                    $outputLines[] = "<path d='M9 4v16'/>";
                    $outputLines[] = "<path d='M12 9l3 3-3 3'/>";
                $outputLines[] = "</svg>";
            $outputLines[] = "</button>";
            $outputLines[] = "<div class='sc_rail__rule'></div>";
            $outputLines[] = "<button class='sc_rail__btn sc_rail__btn--projects' type='button' data-action='toggle_projects_sidebar' title='專案'>";
                $outputLines[] = "<svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8'>";
                    $outputLines[] = "<rect x='4' y='4' width='6' height='6' rx='1'/>";
                    $outputLines[] = "<rect x='14' y='4' width='6' height='6' rx='1'/>";
                    $outputLines[] = "<rect x='4' y='14' width='6' height='6' rx='1'/>";
                    $outputLines[] = "<rect x='14' y='14' width='6' height='6' rx='1'/>";
                $outputLines[] = "</svg>";
            $outputLines[] = "</button>";
            $outputLines[] = "<button class='sc_rail__btn sc_rail__btn--chats' type='button' data-action='toggle_chats_sidebar' title='對話'>";
                $outputLines[] = "<svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7' stroke-linejoin='round'>";
                    $outputLines[] = "<path d='M5.7 18.8 4.2 20l.5-3.1A7.6 7.6 0 1 1 19.4 12a7.5 7.5 0 0 1-13.7 4.8Z'/>";
                    $outputLines[] = "<path d='M8 12h.01M12 12h.01M16 12h.01' stroke-width='2.3' stroke-linecap='round'/>";
                $outputLines[] = "</svg>";
            $outputLines[] = "</button>";
            $outputLines[] = "<button class='sc_rail__btn sc_rail__btn--ranking' type='button' title='排行榜'>";
                $outputLines[] = "<i class='fa-solid fa-ranking-star'></i>";
            $outputLines[] = "</button>";
            $outputLines[] = "<button class='sc_rail__btn' type='button' data-btn='modal_open_dbviewer' data-src='/dbviewer/' title='資料庫'>";
                $outputLines[] = "<i class='fa-solid fa-database'></i>";
            $outputLines[] = "</button>";
            $outputLines[] = "<div class='sc_rail__spacer'></div>";
            $outputLines[] = "<button class='sc_rail__btn' type='button' data-btn='modal_open_settings' title='設定'>";
                $outputLines[] = "<i class='fa-solid fa-gear'></i>";
            $outputLines[] = "</button>";
            $outputLines[] = "<button class='sc_rail__btn sc_rail__btn--help' type='button' title='關於 HKAI' data-btn='modal_open_settings' data-tab='about'>?</button>";
        $outputLines[] = "</nav>";

        return implode("\r\n", $outputLines);
    }

    // 整頁（DOCTYPE～</html>——index.php 唯一出口）
    public function render_page_index($controller)
    {
        $head_content = $this->render_page_head($controller);
        $body_content = $this->render_page_body($controller);

        // 側欄狀態（活動 sidebar_state——開機讀；兩欄開合隨 SSR 落 body 旗標——純 CSS 閘第一時間生效）
        $sidebar_state = hkai()->web_page()->activity()->get_activity("sidebar_state");

        $projects_collapsed_flag = $sidebar_state["projects_collapsed"] ?? "0";
        $chats_collapsed_flag    = $sidebar_state["chats_collapsed"] ?? "0";

        $outputLines           = [];
        $outputLines[]         = "<!DOCTYPE html>";
        $outputLines[]         = "<html lang='zh-Hant'>";
            $outputLines[]     = "<head>";
                $outputLines[] = $head_content;
            $outputLines[]     = "</head>";
            $outputLines[]     = "<body data-status='idle' data-loaded='0' data-projects-collapsed='{$projects_collapsed_flag}' data-chats-collapsed='{$chats_collapsed_flag}'>";
                $outputLines[] = $body_content;
            $outputLines[]     = "</body>";
        $outputLines[]         = "</html>";

        return implode("\r\n", $outputLines);
    }
}
