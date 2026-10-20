// jsmods/chat/act/chat_list_refresh.js —— 對話列刷新（act 層——一檔一事；主動作＝檔名）
// 職責：依 health 報告（health_result）刷新對話列——三流程：更新（兩邊都有）／建立（報告有 DOM 冇）／刪除（DOM 有報告冇）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _chat_list_refresh {
    constructor() {}

    // 依 health_result 刷新對話列（另一 browser 嘅動態由呢條路入——每輪健康更新行一次）
    async chat_list_refresh(health_result) {
        // 報告（來源＝reports.chat——後端 chats 欄統計）
        const chat_reports = health_result.reports.chat || [];

        // 橋接駁（同域 act——建立／刪除／狀態刷共用）
        const chat_instance = await registry().sc_ai_chats().chat();

        // 更新：兩邊都有 → 對有變嘅更新時間落手
        this.update_existing_chat_items(chat_reports);

        // 刪除：DOM 有、報告冇 → 先移除（逐個 chat_sid 交 chat_item_remove）
        const stale_chat_sids = this.get_stale_chat_sids(chat_reports);
        const chat_item_remove_instance = await chat_instance.act().chat_item_remove();

        for (let stale_index = 0; stale_index < stale_chat_sids.length; stale_index++) {
            const stale_chat_sid = stale_chat_sids[stale_index];

            chat_item_remove_instance.chat_item_remove(stale_chat_sid);
        }

        // 建立：報告有、DOM 冇 → 後補缺（逐個 chat_sid 交 chat_item_insert）
        const missing_chat_sids = this.get_missing_chat_sids(chat_reports);

        if (missing_chat_sids.length) {
            const chat_item_insert_instance = await chat_instance.act().chat_item_insert();

            for (let missing_index = 0; missing_index < missing_chat_sids.length; missing_index++) {
                const missing_chat_sid = missing_chat_sids[missing_index];

                await chat_item_insert_instance.chat_item_insert(missing_chat_sid);
            }
        }

        // 狀態刷（每輪一律——polling 依報告直接刷：>0 加、=0 除；唔靠 attr 有無變）
        const chat_activity_instance = await chat_instance.act().chat_activity();

        chat_activity_instance.chat_list_status_refresh(chat_reports);
    }

    // 更新：只對有變嘅更新時間落手（同值唔白寫——唔白觸發觀察器）
    update_existing_chat_items(chat_reports) {
        for (let report_index = 0; report_index < chat_reports.length; report_index++) {
            const chat_report_item = chat_reports[report_index];

            const j_chat_item = jQuery(".sc_chat_list .sc_chat_card[data-chat_sid='" + chat_report_item.chat_segment + "']");

            // 判定：該對話唔喺清單 → 略過
            if (j_chat_item.length < 1) {
                continue;
            }

            // 判定：更新時間冇變 → 唔郁（Y-m-d H:i:s 字串直比——唔解析）
            if (j_chat_item.attr("data-update_time") === chat_report_item.last_update) {
                continue;
            }

            // 只更新 attr（內容／時間文交下一程序——attr 一變即觸發）
            j_chat_item.attr("data-update_time", chat_report_item.last_update);
        }
    }

    // 過期 sid 快查（DOM 有、報告冇——倒序掃描）
    get_stale_chat_sids(chat_reports) {
        // 報告 sid 集合（O(1) 查詢）
        const report_chat_sids = new Set();

        for (let report_index = 0; report_index < chat_reports.length; report_index++) {
            report_chat_sids.add(chat_reports[report_index].chat_segment);
        }

        // DOM 現存對話項（倒序遍歷——移除時唔影響後續 index）
        const stale_chat_sids = [];
        const j_chat_items = jQuery(".sc_chat_list .sc_chat_card");

        for (let item_index = j_chat_items.length - 1; item_index >= 0; item_index--) {
            const chat_item_element = j_chat_items[item_index];
            const item_chat_sid      = chat_item_element.getAttribute("data-chat_sid");

            // 判定：報告有該 sid → 保留
            if (report_chat_sids.has(item_chat_sid)) {
                continue;
            }

            stale_chat_sids.push(item_chat_sid);
        }

        return stale_chat_sids;
    }

    // 缺漏 sid 快查（DOM 單趟掃入陣列 → 逐筆 includes 比對）
    get_missing_chat_sids(chat_reports) {
        // DOM 現存 sid（一掃入陣列）
        const existing_chat_sids = [];

        const j_chat_items = jQuery(".sc_chat_list .sc_chat_card");

        for (let item_index = 0; item_index < j_chat_items.length; item_index++) {
            existing_chat_sids.push(j_chat_items[item_index].getAttribute("data-chat_sid"));
        }

        // 報告有、DOM 冇嘅 sid（陣列查重——報告重複唔出重複）
        const missing_chat_sids = [];

        for (let report_index = 0; report_index < chat_reports.length; report_index++) {
            const chat_report_item = chat_reports[report_index];

            // 判定：DOM 已有 → 唔缺
            if (existing_chat_sids.includes(chat_report_item.chat_segment)) {
                continue;
            }

            // 判定：已收過 → 唔重複
            if (missing_chat_sids.includes(chat_report_item.chat_segment)) {
                continue;
            }

            missing_chat_sids.push(chat_report_item.chat_segment);
        }

        return missing_chat_sids;
    }
}

export const chat_list_refresh = (function (instance) {
    return function () {
        return instance;
    };
})(new _chat_list_refresh());
