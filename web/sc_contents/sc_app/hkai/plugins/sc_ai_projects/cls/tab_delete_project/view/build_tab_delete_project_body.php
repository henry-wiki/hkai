<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_delete_project\view;

// == sc_ai_projects tab_delete_project build_tab_delete_project_body：tab_delete_project 域動作（trait——掛入 tab_delete_project_Class；檔＝動作） ==
// == 內容結構 100% 鏡像 tab_general_form（modal__content-main modal-form＋project-section＋heading＋row 同款——區塊標題走通用 build_section_heading） ==
// == 列序：對話現況／訊息現況 → 確認刪除（先見狀態，後撳掣） ==

trait build_tab_delete_project_body
{
    public function build_tab_delete_project_body($project_row)
    {
        // 顯示層：先落同名變數（由 array 取值）
        $project_name = $project_row["project_name"];
        $project_id   = $project_row["project_id"];

        // 轉義一次過（attribute 語境——ENT_QUOTES；desc 同 attr 共用同一個值）
        $project_name_encoded = sc_html_encode($project_name, ENT_QUOTES);

        // 刪除前現況（對話域統計——對話總數／已歸檔數／訊息總數）
        $delete_stats        = sc_ai_chats()->chat()->get_project_delete_stats($project_id);
        $chat_count          = $delete_stats["chat_count"];
        $archived_chat_count = $delete_stats["archived_chat_count"];
        $message_count       = $delete_stats["message_count"];

        // 對話列字眼（三態——順序：冇對話 → 有已歸檔 → 全部未歸檔）
        if ($chat_count === 0) {
            $chat_summary_text = "目前沒有對話。";
        } elseif ($archived_chat_count > 0) {
            $chat_summary_text = "共 " . $chat_count . " 個——其中 " . $archived_chat_count . " 個已歸檔。";
        } else {
            $chat_summary_text = "共 " . $chat_count . " 個——全部未歸檔。";
        }

        $message_summary_text = "共 " . $message_count . " 條訊息。";

        $description_text = "刪除後無法復原——「" . $project_name_encoded . "」連同以上所有資料會一併刪除。";

        $delete_button_attr   = [];
        $delete_button_attr[] = "type='button'";
        $delete_button_attr[] = "class='project_modal_btn danger btn_item'";
        $delete_button_attr[] = "data-btn='project_delete'";
        $delete_button_attr[] = "data-project_name='{$project_name_encoded}'";
        $delete_button_attr[] = "data-confirm_msg='確定刪除專案 [[project_name]] 嗎？所有對話與資料會一併刪除，無法復原。'";
        $delete_button_attr   = implode(" ", $delete_button_attr);

        $outputLines   = [];
        $outputLines[] = "<div class='modal_pane on' data-pane='delete_project'>";
        $outputLines[] = "<div class='modal__content-main modal-form'>";

            // 區塊：刪除專案（標題形同表單 tab 嘅區塊——通用 builder）
            $outputLines[] = "<div class='project-section'>";
            $outputLines[] = sc_ai_projects()->tab_general_form()->build_section_heading("刪除專案", "危險操作——執行前請確認。");

                // 現況列：對話（總數＋已歸檔數）
                $outputLines[] = "<div class='modal-form__row form_row'>";
                $outputLines[] = "<div class='modal-form__icon'><i class='fa-solid fa-comments'></i></div>";
                $outputLines[] = "<div class='modal-form__info'>";
                $outputLines[] = "<div class='modal-form__label'>對話紀錄</div>";
                $outputLines[] = "<div class='modal-form__desc'>" . $chat_summary_text . "</div>";
                $outputLines[] = "</div>";
                $outputLines[] = "</div>";

                // 現況列：訊息（總數）
                $outputLines[] = "<div class='modal-form__row form_row'>";
                $outputLines[] = "<div class='modal-form__icon'><i class='fa-solid fa-message'></i></div>";
                $outputLines[] = "<div class='modal-form__info'>";
                $outputLines[] = "<div class='modal-form__label'>訊息紀錄</div>";
                $outputLines[] = "<div class='modal-form__desc'>" . $message_summary_text . "</div>";
                $outputLines[] = "</div>";
                $outputLines[] = "</div>";

                // 刪除列（形同表單列——icon／label／desc／content）
                $outputLines[] = "<div class='modal-form__row form_row'>";
                $outputLines[] = "<div class='modal-form__icon modal-form__icon--error'><i class='fa-solid fa-trash'></i></div>";
                $outputLines[] = "<div class='modal-form__info'>";
                $outputLines[] = "<div class='modal-form__label'>確認刪除</div>";
                $outputLines[] = "<div class='modal-form__desc'>" . $description_text . "</div>";
                $outputLines[] = "</div>";
                $outputLines[] = "<div class='modal-form__content'>";
                $outputLines[] = "<div class='sc_btn_wrapper'>";
                $outputLines[] = "<button {$delete_button_attr}>刪除這個專案</button>";
                $outputLines[] = "</div>";
                $outputLines[] = "</div>";
                $outputLines[] = "</div>";

            $outputLines[] = "</div>";

        $outputLines[] = "</div>";
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }
}
