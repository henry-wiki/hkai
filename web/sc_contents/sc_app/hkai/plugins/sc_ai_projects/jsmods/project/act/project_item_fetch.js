// jsmods/project/act/project_item_fetch.js —— 單項專案內容換入（act 層——一檔一事）
// 職責：project_item_fetch（單項抓新內容 → 只換 children——節點身份保留、觀察器唔斷；狀態 class 交健康檢查鏈）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _project_item_fetch {
    constructor() {}

    // 單項專案內容換入（開單叫工作——連線交同域 req；成功＝依 project_slug 取節點換 children；callbacks.success 交呼叫端）
    async project_item_fetch(project_slug, callbacks) {
        callbacks = typeof callbacks !== "object" ? {} : callbacks;

        // 派發 load_project_item hook（before 相位——單項載入前；跨 plugin 用家各自嘅反應）
        registry().sc_ai_projects().execute_hook_before("load_project_item", []);

        // 橋接駁（同域 req 橋）
        const project_instance = await registry().sc_ai_projects().project();
        const project_item_fetch_req_instance = await project_instance.req().project_item_fetch();

        await project_item_fetch_req_instance.project_item_fetch(project_slug, {
            success: function (response_data) {
                // 目標項（依 project_slug 取現行節點——節點身份保留）
                const j_project_item = jQuery(".sc_project_list .sc_project_card[data-project_slug='" + project_slug + "']");

                // 判定：目標項喺清單（未被重繪拆走）→ 只換 children（節點身份保留——觀察器掛住嗰粒唔會斷）
                if (j_project_item.length > 0) {
                    // 新項（離線解析——先落容器再搬）
                    const j_fresh_item = jQuery(response_data.html.project_item);

                    j_project_item.empty();
                    j_project_item.append(j_fresh_item.children());
                }

                // 呼叫端回呼（交返 response——例：點擊轉向攞 link）
                if (typeof callbacks.success === "function") {
                    callbacks.success(response_data);
                }

                // 單項換入完成 → 派發 load_project_item hook（after 相位——跨 plugin 用家各自嘅反應）
                registry().sc_ai_projects().execute_hook_after("load_project_item", response_data);
            },
            error: function (response_data) {
                // 失敗 → 唔郁（維持現狀——等下一輪更新再抓）
            }
        });
    }

    // 切換專案（點擊／URL 換專案——清單先換高亮（舊 active 落、新 active 上），再攞返本項最新內容（換卡）＋狀態列／路徑列同步；換 URL 交呼叫端——點擊行卡 href）
    switch_project(project_slug) {
        // 派發 switch_project hook（before 相位——切換前；跨 plugin 用家各自嘅反應）
        registry().sc_ai_projects().execute_hook_before("switch_project", [project_slug]);

        // 清單高亮（清單唔重繪——舊 active 落、新 active 上）
        jQuery(".sc_project_list").children(".sc_project_card--active").removeClass("sc_project_card--active");

        const j_switched_project_item = jQuery(".sc_project_list").children(".sc_project_card[data-project_slug='" + project_slug + "']");

        j_switched_project_item.addClass("sc_project_card--active");

        return this.project_item_fetch(project_slug, {
            success: async function (response_data) {
                // 清單重繪後同步狀態列（sb_project 讀 .sc_project_list .sc_project_card--active）
                const status_bar = registry().sc_ai_status_bar();

                status_bar.sync_project();

                // topnav 路徑列同步（顯示切換後專案路徑——空＝placeholder）
                const topnav = await registry().sc_topnav();

                topnav.set_project_path(response_data.project_path);

                // 切換完成 → 派發 switch_project hook（after 相位——跨 plugin 用家各自嘅反應）
                registry().sc_ai_projects().execute_hook_after("switch_project", [project_slug]);
            }
        });
    }
}

export const project_item_fetch = (function (instance) {
    return function () {

        return instance;
    };
})(new _project_item_fetch());
