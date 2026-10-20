// jsmods/project/act/project_list_status_refresh.js —— 專案列活動狀態刷新（act 層——一檔一事；主動作＝檔名）
// 職責：project_list_status_refresh（依 project 報告 polling_count 刷 project_item 嘅 polling class——純 CSS 據此亮 loading 底線）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _project_list_status_refresh {
    constructor() {}

    // 依 project 報告逐個專案刷狀態 class（polling_count > 0 → 加 polling class；0 → 移除——時間唔喺呢度掂）
    project_list_status_refresh(project_reports) {
        const j_project_items = jQuery(".sc_project_list .sc_project_card");

        // 全清先（重套唔會殘留）
        j_project_items.removeClass("polling");

        // 逐筆報告（工作中數 > 0 → 對應 item 加 polling class）
        for (let report_index = 0; report_index < project_reports.length; report_index++) {
            const project_report_item = project_reports[report_index];

            // 判定：冇工作中訊息 → 唔落
            if (project_report_item.polling_count < 1) {
                continue;
            }

            const j_active_item = jQuery(".sc_project_list .sc_project_card[data-project_id='" + project_report_item.project_id + "']");

            j_active_item.addClass("polling");
        }
    }
}

export const project_list_status_refresh = (function (instance) {
    return function () {

        return instance;
    };
})(new _project_list_status_refresh());
