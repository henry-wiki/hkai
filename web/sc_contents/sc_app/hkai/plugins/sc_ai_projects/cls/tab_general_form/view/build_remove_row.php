<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_general_form\view;

// == sc_ai_projects tab_general_form build_remove_row：tab_general_form 域動作（trait——掛入 tab_general_form_Class；檔＝動作） ==
// == 2026-09-27 自 tab_general_form/view.php 分遷入（one file one action） ==

trait build_remove_row
{
    public function build_remove_row($project_row)
    {
        // 新增（無既有專案）→ 不顯示歸檔列
        if (!$project_row) {
            return "";
        }
        
        $project_status = $project_row["project_status"] ?? "active";

        // 歸檔／還原（同一格兩態——已歸檔＝還原態；label／desc／掣全換）
        // 判定：已歸檔 → 還原態；否則 → 歸檔態
        if ($project_status === "archived") {
            $restore_lines   = [];
            $restore_lines[] = "<div class='sc_btn_wrapper'>";
                $restore_lines[] = "<button type='button' class='project_modal_btn success btn_item' data-btn='project_restore' data-confirm_msg='確定還原專案 [[project_name]] 嗎？還原後會回到專案清單。'><i class='fa-solid fa-box-open'></i> 還原這個專案</button>";
            $restore_lines[] = "</div>";

            $row_html = $this->build_form_row("還原專案", "還原後專案會回到正常清單。", implode("\r\n", $restore_lines));
        } else {
            $archive_lines   = [];
            $archive_lines[] = "<div class='sc_btn_wrapper'>";
                $archive_lines[] = "<button type='button' class='project_modal_btn btn_item' data-btn='project_archive' data-confirm_msg='確定要歸檔專案 [[project_name]] 嗎？歸檔後會從專案清單隱藏，所有資料保留。'><i class='fa-solid fa-box-archive'></i> 歸檔這個專案</button>";
            $archive_lines[] = "</div>";

            $row_html = $this->build_form_row("歸檔專案", "歸檔後唔會刪除任何資料。", implode("\r\n", $archive_lines));
        }

        return $row_html;
    }
}
