// jsmods/project/act/project_list_refresh.js —— 專案列刷新（act 層——一檔一事；主動作＝檔名）
// 職責：依 health 報告（health_result）刷新專案列——三流程：更新（兩邊都有）／建立（報告有 DOM 冇）／刪除（DOM 有報告冇）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _project_list_refresh {
    constructor() {}

    // 依 health_result 刷新專案列（另一 browser 嘅動態由呢條路入——每輪健康更新行一次）
    async project_list_refresh(health_result) {
        // 報告（來源＝reports.project——後端 projects／chats 欄統計）
        const project_reports = health_result.reports.project || [];

        // 橋接駁（同域 act——建立／刪除／狀態刷共用）
        const project_instance = await registry().sc_ai_projects().project();

        // 更新：兩邊都有 → 對有變嘅更新時間落手
        this.update_existing_project_items(project_reports);

        // 刪除：DOM 有、報告冇 → 先移除（逐個 project_slug 交 project_item_remove）
        const stale_project_slugs = this.get_stale_project_slugs(project_reports);
        const project_item_remove_instance = await project_instance.act().project_item_remove();

        for (let stale_index = 0; stale_index < stale_project_slugs.length; stale_index++) {
            const stale_project_slug = stale_project_slugs[stale_index];

            project_item_remove_instance.project_item_remove(stale_project_slug);
        }

        // 建立：報告有、DOM 冇 → 後補缺（逐個 project_slug 交 project_item_insert）
        const missing_project_slugs = this.get_missing_project_slugs(project_reports);

        if (missing_project_slugs.length) {
            const project_item_insert_instance = await project_instance.act().project_item_insert();

            for (let missing_index = 0; missing_index < missing_project_slugs.length; missing_index++) {
                const missing_project_slug = missing_project_slugs[missing_index];

                await project_item_insert_instance.project_item_insert(missing_project_slug);
            }
        }

        // 狀態刷（每輪一律——polling 依報告直接刷：>0 加、=0 除；唔靠 attr 有無變）
        const project_list_status_refresh_instance = await project_instance.act().project_list_status_refresh();

        project_list_status_refresh_instance.project_list_status_refresh(project_reports);

        // 狀態更新完 → 順手重排（自動調位；次序無變＝佢自己會 no-op）
        const project_list_rank_refresh_instance = await project_instance.act().project_list_rank_refresh();

        project_list_rank_refresh_instance.project_list_rank_refresh();
    }

    // 更新：只對有變嘅更新時間落手（同值唔白寫——唔白觸發觀察器）
    update_existing_project_items(project_reports) {
        for (let report_index = 0; report_index < project_reports.length; report_index++) {
            const project_report_item = project_reports[report_index];

            const j_project_item = jQuery(".sc_project_list .sc_project_card[data-project_slug='" + project_report_item.project_segment + "']");

            // 判定：該專案唔喺清單 → 略過
            if (j_project_item.length < 1) {
                continue;
            }

            // 判定：更新時間冇變 → 唔郁（Y-m-d H:i:s 字串直比——唔解析）
            if (j_project_item.attr("data-update_time") === project_report_item.last_update) {
                continue;
            }

            // 只更新 attr（內容／時間文交下一程序——attr 一變即觸發）
            j_project_item.attr("data-update_time", project_report_item.last_update);
        }
    }

    // 過期 slug 快查（DOM 有、報告冇——倒序掃描）
    get_stale_project_slugs(project_reports) {
        // 報告 slug 集合（O(1) 查詢）
        const report_project_slugs = new Set();

        for (let report_index = 0; report_index < project_reports.length; report_index++) {
            report_project_slugs.add(project_reports[report_index].project_segment);
        }

        // DOM 現存專案項（倒序遍歷——移除時唔影響後續 index）
        const stale_project_slugs = [];
        const j_project_items = jQuery(".sc_project_list .sc_project_card");

        for (let item_index = j_project_items.length - 1; item_index >= 0; item_index--) {
            const project_item_element = j_project_items[item_index];
            const item_project_slug    = project_item_element.getAttribute("data-project_slug");

            // 判定：報告有該 slug → 保留
            if (report_project_slugs.has(item_project_slug)) {
                continue;
            }

            stale_project_slugs.push(item_project_slug);
        }

        return stale_project_slugs;
    }

    // 缺漏 slug 快查（DOM 單趟掃入陣列 → 逐筆 includes 比對）
    get_missing_project_slugs(project_reports) {
        // DOM 現存 slug（一掃入陣列）
        const existing_project_slugs = [];

        const j_project_items = jQuery(".sc_project_list .sc_project_card");

        for (let item_index = 0; item_index < j_project_items.length; item_index++) {
            existing_project_slugs.push(j_project_items[item_index].getAttribute("data-project_slug"));
        }

        // 報告有、DOM 冇嘅 slug（陣列查重——報告重複唔出重複）
        const missing_project_slugs = [];

        for (let report_index = 0; report_index < project_reports.length; report_index++) {
            const project_report_item = project_reports[report_index];

            // 判定：DOM 已有 → 唔缺
            if (existing_project_slugs.includes(project_report_item.project_segment)) {
                continue;
            }

            // 判定：已收過 → 唔重複
            if (missing_project_slugs.includes(project_report_item.project_segment)) {
                continue;
            }

            missing_project_slugs.push(project_report_item.project_segment);
        }

        return missing_project_slugs;
    }
}

export const project_list_refresh = (function (instance) {
    return function () {
        return instance;
    };
})(new _project_list_refresh());
