<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\view;

// == sc_ai_chats chat render_chat_item：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-28 自 render_chat_list 分拆（清單與單項同一渲染源——前端 replaceWith 用） ==

trait render_chat_item
{

    // 渲染單一對話清單項 HTML（清單逐項同用；結構：sc_chat_card > sc_chat_card__primary ＋ sc_chat_card__meta）
    // 傳入列已 sc_html_encode（呼叫端負責——轉義一次過）
    // $selected_project_row＝上下文專案列（連結第一段——歸檔形 archive@<project_slug> 由 build_chat_url 統一砌；只傳 slug 會漏歸檔形）
    public function render_chat_item($chat_row_item, $selected_project_row, $selected_chat_sid)
    {
        $active_class = "";

        $chat_id            = (isset($chat_row_item["chat_id"]) ? $chat_row_item["chat_id"] : null);
        $chat_sid           = (isset($chat_row_item["chat_sid"]) ? $chat_row_item["chat_sid"] : null);
        $chat_title         = (isset($chat_row_item["chat_title"]) ? $chat_row_item["chat_title"] : null);
        $chat_message_count = (isset($chat_row_item["chat_message_count"]) ? $chat_row_item["chat_message_count"] : null);
        $provider_id        = (isset($chat_row_item["provider_id"]) ? $chat_row_item["provider_id"] : null);
        $model_id           = (isset($chat_row_item["model_id"]) ? $chat_row_item["model_id"] : null);
        $chat_updated_at    = (isset($chat_row_item["chat_updated_at"]) ? $chat_row_item["chat_updated_at"] : null);
        $chat_status        = (isset($chat_row_item["chat_status"]) ? $chat_row_item["chat_status"] : null);

        // 上下文專案段（連結第一段來源——純 slug＋狀態；歸檔形由 build_chat_url 統一砌）
        $project_slug   = (isset($selected_project_row["project_slug"]) ? $selected_project_row["project_slug"] : null);
        $project_status = (isset($selected_project_row["project_status"]) ? $selected_project_row["project_status"] : null);

        // 模型顯示文（provider/model——兩者皆空＝空）
        $chat_model_text    = trim($provider_id . "/" . $model_id, "/");

        // 目前選取的對話加上高亮樣式——chat_sid 相同（URL 主鍵——string 比較）
        if ($chat_sid === $selected_chat_sid) {
            $active_class = " sc_chat_card--active";
        }

        $chat_link = $this->build_chat_url($project_slug, $project_status, $chat_sid, $chat_status);

        // 卡片身份段（data-chat_sid——同 URL 段同一語言：歸檔＝archive@{chat_sid}；各刷新程序比對用）
        $chat_segment = $this->build_chat_segment($chat_sid, $chat_status);

        // 顯示時間（相對時間階梯——app 級共用 sc_format_relative_time；系統計時＝data-update_time 原值）
        $updated_time = sc_format_relative_time($chat_updated_at);

        // 歸檔／還原／刪除文案（i18n——先落變數，後入 attr）
        $archive_title       = hkai__i18n()->get("chat.archive");
        $restore_title       = hkai__i18n()->get("chat.restore");
        $archive_confirm_msg = hkai__i18n()->get("chat.archive_confirm");
        $restore_confirm_msg = hkai__i18n()->get("chat.restore_confirm");
        $delete_title        = hkai__i18n()->get("chat.delete");
        $delete_confirm_msg  = hkai__i18n()->get("chat.delete_confirm");

        // 歸檔卡樣式 class（sc_chat_card--archived——琥珀標識；結構同正常卡不變）
        $archived_class = "";

        // 判定：已歸檔 → 加歸檔樣式 class
        if ($chat_status === "archived") {
            $archived_class = " sc_chat_card--archived";
        }

        $item_attr = [];
        $item_attr[] = "class='sc_chat_card{$active_class}{$archived_class}'";
        $item_attr[] = "href='{$chat_link}'";
        $item_attr[] = "data-chat_id='{$chat_id}'";
        $item_attr[] = "data-chat_sid='{$chat_segment}'";
        $item_attr[] = "data-update_time='{$chat_updated_at}'";
        $item_attr = implode(" ", $item_attr);

        $link_attr = [];
        $link_attr[] = "class='sc_chat_card__link'";
        $link_attr[] = "data-btn='chat_link'";
        $link_attr = implode(" ", $link_attr);

        $meta_link_attr = [];
        $meta_link_attr[] = "class='sc_chat_card__link sc_chat_card__meta'";
        $meta_link_attr[] = "data-btn='chat_link'";
        $meta_link_attr = implode(" ", $meta_link_attr);

        $content = [];
        $content[] = "<a {$item_attr}>";
        $content[] = "<div class='sc_chat_card__primary'>";
        $content[] = "<span {$link_attr}><span class='sc_chat_card__title' title='{$chat_title}'>{$chat_title}</span></span>";
        $content[] = "<span class='sc_chat_card__updated'>{$updated_time}</span>";
        // 判定：已歸檔 → 還原掣＋刪除掣（兩粒並列——歸檔態先有刪除）；未歸檔 → 只有歸檔掣（列面乾淨）
        if ($chat_status === "archived") {
            $content[] = "<button class='sc_chat_card__restore_btn' data-action='chat_restore' type='button' data-confirm_msg='{$restore_confirm_msg}' title='{$restore_title}'><i class='fa-solid fa-box-open'></i></button>";
            // 刪除掣（真正刪除——永久；hover 變紅；只喺歸檔態出現）
            $content[] = "<button class='sc_chat_card__delete_btn' data-action='chat_delete' type='button' data-confirm_msg='{$delete_confirm_msg}' title='{$delete_title}'><i class='fa-solid fa-trash'></i></button>";
        } else {
            $content[] = "<button class='sc_chat_card__archive_btn' data-action='chat_archive' type='button' data-confirm_msg='{$archive_confirm_msg}' title='{$archive_title}'><i class='fa-solid fa-box-archive'></i></button>";
        }
        $content[] = "</div>";
        $content[] = "<span {$meta_link_attr}>";
        // 判定：有模型資訊 → 出模型（bdi——provider/model——長名 truncate）
        if ($chat_model_text !== "") {
            $content[] = "<bdi class='sc_chat_card__model' title='{$chat_model_text}'>{$chat_model_text}</bdi>";
        }
        $content[] = "<span class='sc_chat_card__msg_count' data-count='{$chat_message_count}'>💬 <b>{$chat_message_count}</b></span>";
        $content[] = "</span>";
        $content[] = "</a>";

        return implode("\r\n", $content);
    }

}
