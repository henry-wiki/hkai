// jsmods/project/act/project_list_fetch.js —— 專案清單載入與刷新（act 層——一檔一事）
// 職責：project_list_fetch（清單載入——hook 連鎖：before 派發 → req → apply → after 派發；slug 由 req 讀 URL）／switch（切換專案——寫 URL 之後重取清單）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）


class _project_list_fetch {
    constructor() {}

    // 專案清單載入（本機側動作——URL 為唯一真相（slug 由 req 自己讀），成功先渲染：清單 HTML 注入；失敗不動作）
    async project_list_fetch(callbacks) {
        // registry 取值（靜態——直回單例）
        const projects = registry().sc_ai_projects();

        // 派發 load_project_list hook（before 相位——清單載入前；跨 plugin 用家各自嘅反應）
        projects.execute_hook_before("load_project_list", []);

        // 橋接駁（同域 req 橋——新方式）
        const project_instance = await registry().sc_ai_projects().project();
        const project_list_fetch_instance = await project_instance.req().project_list_fetch();


        await project_list_fetch_instance.project_list_fetch({
            success: async function (response_data) {
                jQuery(".sc_project_list").html(response_data.html);

                // 載入標記（data-loaded 0→1——元素「已載入」語義；用家待未來）
                jQuery(".sc_project_list").attr("data-loaded", "1");

                // 無效專案（後端查唔到）→ 自動回 home
                if (response_data.auto_home === true) {
                    sc_url.silent_redirect_url("/");
                    return;
                }

                // 清單歸檔語境 class（後台出旗標——歸檔視覺由 CSS 發揮；URL 變時重取跟隨更新）
                if (response_data.is_archive === true) {
                    jQuery(".sc_project_list").addClass("archive");
                } else {
                    jQuery(".sc_project_list").removeClass("archive");
                }

                // topnav 路徑列同步（顯示選取專案路徑——空＝placeholder）
                const topnav = await registry().sc_topnav();

                topnav.set_project_path(response_data.project_path);

                // 清單重繪完成 → 派發 load_project_list hook（after 相位——跨 plugin 用家各自嘅反應）
                projects.execute_hook_after("load_project_list",response_data);
            },
            error: function (response_data) {
                // 失敗 → 不更新（維持現狀——等下次操作再抓）
            }
        });
    }

    // 切換專案（由 project_add 呼叫——目標 slug 先寫入 URL（唯一真相），之後重取清單）
    async switch(project_slug, callbacks) {
        const project_instance = await registry().sc_ai_projects().project();

        sc_url.silent_replace_url(project_instance.build_project_url(project_slug));

        await this.project_list_fetch(callbacks);
    }
}

export const project_list_fetch = (function (instance) {
    return function () {

        return instance;
    };
})(new _project_list_fetch());
