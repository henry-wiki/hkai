<?php

namespace sc\app\hkai\plugins\sc_ai_projects\modal\view;

use function sc\app\hkai\plugins\sc_ai_projects\repo_select;
use function sc\app\hkai\plugins\sc_ai_projects\modal\tab_delete_project_view;
use function sc\app\hkai\plugins\sc_ai_projects\modal\tab_diagnostics_view;
use function sc\app\hkai\plugins\sc_ai_projects\modal\tab_environment_view;
use function sc\app\hkai\plugins\sc_ai_projects\modal\tab_general_form_view;
use function sc\app\hkai\plugins\sc_ai_projects\modal\tab_git_view;
use function sc\app\hkai\plugins\sc_ai_projects\modal\tab_rule_view;
use function sc\app\hkai\plugins\sc_ai_projects\modal\tabs;

// == sc_ai_projects modal fetch_project_form：modal 域動作（trait——掛入 modal_Class；檔＝動作） ==
// == 2026-09-27 自 modal/view.php 分遷入（one file one action） ==

trait fetch_project_form
{
    public function fetch_project_form()
    {
        $input_data = sc_request()->json();
        
        // 目標專案（只認 project_slug——前端帶；新增＝空 slug → row=null）
        $project_slug = trim($input_data["project_slug"] ?? "");

        // 查表用純 slug（歸檔語境形 archive@<project_slug> → @ 後；其餘 → 原值——歸檔專案照查到）
        $project_slug = sc_ai_projects()->project()->get_bare_project_slug($project_slug);

        // 新增語境（空 slug）→ 一律當新專案（唔可以查表——空 slug 會解析成 Home，變成預填 Home 資料）
        $project_row = null;

        // 判定：有 slug（編輯語境）→ 才查表
        if ($project_slug !== "") {
            $project_row = repo_select()->get_project_row($project_slug);
        }

        // 判定：帶咗 slug（編輯語境）但查無此專案 → 失敗（唔可以靜靜當新增——錯就係錯）
        if ($project_slug !== "") {
            if ($project_row === null) {
                // 錯誤內容照後端砌（共用空狀態塊——前端錯誤路插 modal body 顯示；唔淨止出面 toast）
                $error_html          = [];
                $error_html["slots"] = sc_empty_state(hkai__i18n()->get("error.project_not_found"), false);

                return sc_response()->json(["ok" => false, "error" => "project not found", "html" => $error_html], 404);
            }
        }
        
        // 編輯（有既有專案）→ Setup／Git tab＋儲存鈕
        $is_edit = $project_row ? true : false;

        // 歸檔狀態（tabs 顯示範圍用——歸檔專案＝只剩 表單／刪除 兩粒）
        $is_archived = ($project_row["project_status"] ?? "active") === "archived";
        
        // tab_id（空＝預設第一個 tab；有值＝照用；唔存在＝錯——唔兜底）
        $tab_id = trim($input_data["tab_id"] ?? "");
        
        $tab = tabs()->get_modal_tab($tab_id);
        
        // tab_id 唔存在 → 失敗（錯就係錯——唔兜底預設 tab）
        if ($tab === null) {
            return sc_response()->json(["ok" => false, "error" => "tab not found"], 400);
        }
        
        $active_tab_id = $tab["tab_id"];
        
        // 外框 footer 左側內容（預設＝共用重新整理鈕；規則／環境 tab＝各自註記字眼——規則頁重新整理移入右側）
        $foot_left_html = "<button class='project_modal_btn btn_item' data-btn='project_refresh'>重新整理</button>";

        // modal 外框內容（head／tabs／foot——head／foot 由當前 tab 的 view 獨立砌）
        $html = [];
        $html["tabs"] = $this->build_modal_tabs($is_edit, $active_tab_id, $is_archived);
        
        // 傳遞 slot 給前端（只傳當前 tab 的內容＋該 tab 自己的 head／foot——新建＝null 由各 builder 自守衛）
        $head_html = "";
        $body_html = "";
        $foot_html = "";
        if ($active_tab_id === "general_form") {
            $head_html = $this->build_modal_head($is_edit);
            $body_html = tab_general_form_view()->build_tab_general_form_body($project_row);
            
            // footer 按模式揀 builder（新增＝建立專案；編輯＝儲存——view 內唔再靠 $is_edit 分岔）
            if ($is_edit === true) {
                $foot_html = tab_general_form_view()->build_general_form_tab_foot_edit();
            } else {
                $foot_html = tab_general_form_view()->build_general_form_tab_foot_add();
            }

            // 歸檔專案 → 儲存／取消唔出（歸檔＝唯讀——唔可以改）
            if ($is_archived === true) {
                $foot_html = "";
            }
        } elseif ($active_tab_id === "git") {
            // Git tab → 直接出真 Git 面板（壳＋狀態一次完成——無二段 fetch）
            $head_html = tab_git_view()->build_tab_git_head($is_edit);
            $body_html = tab_git_view()->build_tab_git_body($project_row);
            $foot_html = tab_git_view()->build_tab_git_foot();
        } elseif ($active_tab_id === "rule") {
            // Rule tab → 直接出真規則面板（壳＋規則編輯器一次完成——無二段 fetch；footer 左側＝註記字眼——見外框組裝）
            $head_html = tab_rule_view()->build_tab_rule_head($is_edit);
            $body_html = tab_rule_view()->build_tab_rule_body($project_row);
            $foot_html = tab_rule_view()->build_tab_rule_foot();
        } elseif ($active_tab_id === "environment") {
            // Environment tab → 直接出環境面板（純檢視——零寫入；壳一次完成——無二段 fetch）
            $head_html = tab_environment_view()->build_tab_environment_head($is_edit);
            $body_html = tab_environment_view()->build_tab_environment_body($project_row);
            $foot_html = tab_environment_view()->build_tab_environment_foot();
        } elseif ($active_tab_id === "diagnostics") {
            // Diagnostics tab → 直接出診斷面板（左側 tab 化——每 tab 獨立：開面板只載首個 section）
            $head_html = tab_diagnostics_view()->build_tab_diagnostics_head($is_edit);

            // 判定：編輯（有專案）→ footer 入內容欄（左 nav 欄全高——跟 settings 形）
            if ($is_edit === true) {
                $diagnostics_foot_html = tab_diagnostics_view()->build_diagnostics_pane_footer();
            } else {
                $diagnostics_foot_html = "";
            }

            $body_html = tab_diagnostics_view()->build_tab_diagnostics_body($project_row, $diagnostics_foot_html);

            // 判定：編輯 → modal 級 footer 唔用（已入內容欄）；新建 → modal 級 footer 照舊
            if ($is_edit === true) {
                $foot_html = "";
            } else {
                $foot_html = tab_diagnostics_view()->build_tab_diagnostics_foot();
            }
        } elseif ($active_tab_id === "delete_project") {
            // 刪除 tab → 警示列＋紅色刪除鈕（動作本體＝tab_general_form 嘅 project_delete 鏈；footer 右側無掣）
            $head_html = tab_delete_project_view()->build_tab_delete_project_head($is_edit);
            $body_html = tab_delete_project_view()->build_tab_delete_project_body($project_row);
            $foot_html = "";
        }
        
        $html["head"]       = $head_html;
        // 外框 footer 左側內容（預設＝共用重新整理鈕；規則／環境 tab＝各自註記字眼——規則頁重新整理移入右側）
        $foot_left_html = "<button class='project_modal_btn btn_item' data-btn='project_refresh'>重新整理</button>";

        // 判定：規則 tab → 左側換成更新狀態字眼（規則頁專屬安排）
        if ($active_tab_id === "rule") {
            $foot_left_html = tab_rule_view()->build_tab_rule_foot_note($project_row);
        }

        // 判定：環境 tab → 左側換成環境檔路徑＋更新時間（環境頁專屬安排）
        if ($active_tab_id === "environment") {
            $foot_left_html = tab_environment_view()->build_tab_environment_foot_note($project_row);
        }

        // 外框 footer（左側＝上面嗰件；右側＝當前 tab 嘅按鈕清單——外殼 inline 組裝）
        $html["foot"]      = [];
        $html["foot"][] = "<div class='project_modal__footer'>";
            $html["foot"][] = "<div class='project_modal__footer_left'>";
                $html["foot"][] = $foot_left_html;
            $html["foot"][] = "</div>";
            $html["foot"][] = "<div class='project_modal__footer_right'>{$foot_html}</div>";
        $html["foot"][] = "</div>";
        $html["foot"] = implode("",$html["foot"]);

        // 判定：診斷 tab → modal 級 footer 唔用（編輯語境——已入內容欄；新建＝照舊）
        if ($active_tab_id === "diagnostics") {
            // 判定：編輯語境（新建＝modal 級 footer 照舊）
            if ($is_edit === true) {
                $html["foot"] = "";
            }
        }
        
        $html["slots"]      = [];
        $html["slots"][] = "<div class='modal__content'>";
            $html["slots"][] = $body_html;
        $html["slots"][] = "</div>";
        $html["slots"] = implode("",$html["slots"]);
        
        // 傳遞 active_tab_id 給前端（前端用此值作為 active tab 預設）
        $html["active_tab_id"] = $active_tab_id;
        
        // 傳遞 project_slug 供前端 modal().open() 內使用（空字串 = 新增；有值 = 編輯）
        $project_slug = $project_row ? $project_row["project_slug"] : "";
        
        // 傳遞歸檔狀態（前端 modal 主題——歸檔＝警示琥珀）
        return sc_response()->json(["ok" => true, "html" => $html, "project_slug" => $project_slug, "is_archived" => $is_archived]);
    }
}
