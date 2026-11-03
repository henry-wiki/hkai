<?php

namespace sc\app\hkai\plugins\sc_ai_status_bar\status_bar\render;

// == sc_ai_status_bar render render_workspace_region：底部狀態欄（trait——掛入 status_bar_Class；檔＝一個 region）
// == 職責：直接組 .sc_statusbar--workspace（左：git；右：conn／clock／copyright）——唔經 groups／items
// == 上游：sc_ai_chats message/view/render_message_body.php；下游：diagnostics 域（git item）、process 域（server 狀態）

trait render_workspace_region
{
    // 底部狀態欄 HTML（左＝git；右＝連線／時鐘／版權）
    public function render_workspace_region()
    {
        // 左：git item（診斷域提供完整 item HTML——唔經 item 表）
        $git_status_item = sc_ai_diagnostics()->git()->get_git_status_item();

        // 右：conn 首屏真值（plugin init 早過 index.php 起 server——init 時嘅值會過時）
        $server_status   = hkai()->process()->server()->get_server_status();
        $is_bridge_alive = ($server_status["is_alive"] === true);

        $conn_text  = hkai()->i18n()->get("status_bar.conn_offline");
        $conn_class = "bad";

        // 判定：bridge 在生 → 顯示已連線
        if ($is_bridge_alive === true) {
            $conn_text  = hkai()->i18n()->get("status_bar.conn_online");
            $conn_class = "ok";
        }

        // 右：時鐘（初值——前端每秒 tick）
        $clock_text = "--:--";

        // 右：版權（about 檔唯一來源）
        $about_data     = require sc_get_config("hkai.about_file");
        $copyright_text = $about_data["copyright"];

        $output_lines   = [];
        $output_lines[] = "<div class='sc_statusbar sc_statusbar--workspace'>";

        // 左欄
        $output_lines[] = "<div class='sc_statusbar__left'>";
        $output_lines[] = $git_status_item;
        $output_lines[] = "</div>";

        // 右欄
        $output_lines[] = "<div class='sc_statusbar__right'>";
        $output_lines[] = "<div class='sc_statusbar__item " . $conn_class . " sc_statusbar__item--clickable sc_statusbar__item--conn' data-click='settings' data-click-tab='server'>● <span class='sc-text'>" . $conn_text . "</span></div>";
        $output_lines[] = "<div class='sc_statusbar__item sc_statusbar__item--clock'><span class='sc-text'>" . $clock_text . "</span></div>";
        $output_lines[] = "<div class='sc_statusbar__item sc_statusbar__item--clickable sc_statusbar__item--copyright' data-click='about_copyright'><span class='sc-text'>" . $copyright_text . "</span></div>";
        $output_lines[] = "</div>";

        $output_lines[] = "</div>";

        return implode("\r\n", $output_lines);
    }
}
