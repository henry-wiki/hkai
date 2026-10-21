<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\view;

// == sc_ai_projects project render_project_item：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-09-29 自 render_project_list 分拆（清單與單項同一渲染源——前端換 children 用） ==

trait render_project_item
{

    // 渲染單一專案清單項 HTML（清單逐項同用；結構：sc_project_card > primary ＋ path ＋ meta；meta 固定三行）
    // 傳入列已 sc_html_encode（呼叫端負責——轉義一次過）
    public function render_project_item($project_item, $selected_project_slug, $is_home_card = false)
    {
        $project_id   = (isset($project_item["project_id"]) ? $project_item["project_id"] : null);
        $project_slug = (isset($project_item["project_slug"]) ? $project_item["project_slug"] : null);
        $project_name = (isset($project_item["project_name"]) ? $project_item["project_name"] : null);
        $project_status = (isset($project_item["project_status"]) ? $project_item["project_status"] : null);

        // 目前選取的專案加上高亮樣式——slug 相同（URL 主鍵）
        $active_class = "";
        if ($project_slug === $selected_project_slug) {
            $active_class = " sc_project_card--active";
        }

        // 已歸檔卡加上歸檔樣式——project_status＝archived（還原掣同行——歸檔清單語境）
        $archived_class = "";
        if ($project_status === "archived") {
            $archived_class = " sc_project_card--archived";
        }

        // 專案連結（含歸檔語境形——URL 知識住 build_project_url；歸檔＝/archive@<project_slug>/）
        $project_link = $this->build_project_url($project_slug, $project_status);

        // 判定：Home 卡 → 連結固定 "/"
        if ($is_home_card === true) {
            $project_link = "/";
        }

        // 卡片身份段（同 URL 段同一語言：歸檔＝archive@{project_slug}；各刷新程序比對用——對照 chat 側 data-chat_sid）
        $project_segment = $this->build_project_segment($project_slug, $project_status);

        // 統計（對話域對外方法——對話數＋等待列行數）
        $project_chat_stats  = sc_ai_chats()->chat()->get_project_chat_stats($project_id);
        $project_chat_count  = $project_chat_stats["chat_count"];
        $project_queue_count = $project_chat_stats["queue_count"];

        // 排隊色階（>0 亮色、0 淡色——純文字分層）
        $project_queue_class = "sc_project_card__sync sc_project_card__sync--off";
        // 判定：有排隊 → 亮色
        if ($project_queue_count > 0) {
            $project_queue_class = "sc_project_card__sync sc_project_card__sync--on";
        }

        // 最近對話（模型＋時間——對話域對外方法）
        $latest_chat_row           = sc_ai_chats()->chat()->get_latest_chat_row_by_project($project_id);
        $project_latest_model_text = "";
        $project_updated_time      = "";
        $project_update_time       = "";

        // 判定：有對話 → 取模型＋最近更新時間（顯示＝相對時間階梯；attr＝原值——觀察器比對用）
        if ($latest_chat_row !== null) {
            $project_latest_model_text = trim($latest_chat_row["provider_id"] . "/" . $latest_chat_row["model_id"], "/");
            $project_updated_time      = sc_format_relative_time($latest_chat_row["chat_updated_at"]);
            $project_update_time       = $latest_chat_row["chat_updated_at"];
        }

        // 判定：冇對話時間 → 退用專案自身更新時間
        if ($project_updated_time === "") {
            $project_updated_at = (isset($project_item["project_updated_at"]) ? $project_item["project_updated_at"] : null);

            // 判定：有專案更新時間 → 顯示＋落 attr
            if ($project_updated_at !== "") {
                $project_updated_time = sc_format_relative_time($project_updated_at);
                $project_update_time  = $project_updated_at;
            }
        }

        // Home 卡樣式（房子 icon／專屬 class）
        $home_class = "";
        $project_name_html = $project_name;

        // 判定：Home 卡 → 加 class＋房子 icon
        if ($is_home_card === true) {
            $home_class = " sc_project_card--home";
            $project_name_html = "<i class='fa-solid fa-house'></i> " . $project_name;
        }

        $item_attr = [];
        $item_attr[] = "class='sc_project_card{$active_class}{$archived_class}{$home_class}'";
        $item_attr[] = "href='{$project_link}'";
        $item_attr[] = "data-project_slug='{$project_segment}'";
        $item_attr[] = "data-project_id='{$project_id}'";
        $item_attr[] = "data-update_time='{$project_update_time}'";
        $item_attr = implode(" ", $item_attr);

        $edit_btn_attr = [];
        $edit_btn_attr[] = "class='sc_project_card__menu_btn'";
        $edit_btn_attr[] = "data-btn='modal_open_edit'";
        $edit_btn_attr[] = "title='編輯'";
        $edit_btn_attr = implode(" ", $edit_btn_attr);

        $content = [];
        $content[] = "<a {$item_attr}>";
        $content[] = "<div class='sc_project_card__primary'>";
        $content[] = "<span class='sc_project_card__name' title='" . $project_name . "'>" . $project_name_html . "</span>";
        $content[] = "<span class='sc_project_card__updated'>" . $project_updated_time . "</span>";
        // 判定：非 Home 卡 → 出編輯掣（齒輪——卡上單一入口；歸檔同未歸檔一致——還原唔喺卡面）
        if ($is_home_card === false) {
            $content[] = "<button {$edit_btn_attr}><i class='fa-solid fa-gear'></i></button>";
        }
        $content[] = "</div>";
        $content[] = "<div class='sc_project_card__meta'>";
        // 固定三行——冇模型都出行（空行保高度，行高穩定）
        $content[] = "<bdi class='sc_project_card__model' title='" . $project_latest_model_text . "'>" . $project_latest_model_text . "</bdi>";
        $content[] = "<span class='" . $project_queue_class . "' data-queue='" . $project_queue_count . "'><i class='fa-solid fa-arrow-right-arrow-left'></i> " . $project_queue_count . "</span>";
        $content[] = "<span class='sc_project_card__chat_count' data-count='" . $project_chat_count . "'>💬 <b>" . $project_chat_count . "</b></span>";
        $content[] = "</div>";
        $content[] = "</a>";

        return implode("\r\n", $content);
    }

}
