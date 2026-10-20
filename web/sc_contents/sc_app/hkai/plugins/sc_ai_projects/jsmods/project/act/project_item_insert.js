// jsmods/project/act/project_item_insert.js —— 新專案項插入（act 層——一檔一事；主動作＝檔名）
// 職責：project_item_insert（按 project_slug 後端載入該專案項 HTML → append 入 .sc_project_list 尾——由 project_list_refresh 迴圈逐個呼叫）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _project_item_insert {
    constructor() {}

    // 按 project_slug 載入並插入單一專案項
    async project_item_insert(project_slug) {
        // 判定：已存在 → 唔重複補
        if (jQuery(".sc_project_list .sc_project_card[data-project_slug='" + project_slug + "']").length > 0) {
            return;
        }

        // 橋接駁（同域 req 橋）
        const project_instance = await registry().sc_ai_projects().project();
        const project_item_fetch_req_instance = await project_instance.req().project_item_fetch();

        // 橋接駁（同域 obs 橋——成功回呼要同步用佢、預先攞定；新項唔掛觀察器＝時間／count 永遠唔更新）
        const project_item_observer_instance = await project_instance.obs().project_item_observer();

        await project_item_fetch_req_instance.project_item_fetch(project_slug, {
            success: function (response_data) {
                // 新項（離線解析——先落容器再搬）
                const j_new_item = jQuery(response_data.html.project_item);

                // 判定：已存在（等待期間已由別路補上）→ 唔再加入
                if (jQuery(".sc_project_list .sc_project_card[data-project_slug='" + project_slug + "']").length > 0) {
                    return;
                }

                // 落清單尾（次序交後端——報告次序即顯示次序）
                jQuery(".sc_project_list").append(j_new_item);

                // 補掛觀察器（新項即納入觀察——data-update_time 一變 → 派 obs:project_item_updated → 抓新內容換入）
                project_item_observer_instance.observe(j_new_item[0]);
            },
            error: function (response_data) {
                // 失敗 → 唔補（維持現狀——等下一輪再試）
            }
        });
    }
}

export const project_item_insert = (function (instance) {
    return function () {
        return instance;
    };
})(new _project_item_insert());
