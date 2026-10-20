// jsmods/modal/act/modal_open.js —— modal 開啟（唯一入口）（action 層——一 action 一檔）
// 職責：modal_open（request_data → 外框重用／建立 → fetch_project_form → 派發：外框＋body render → 交返 callbacks）
// 結構同 evt 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）


class _modal_open {
    constructor() {}

    // 開啟專案表單（project_slug="" 新專案；tab_id=停留嘅 tab；callbacks=呼叫端回呼，成功=外框+body 載完；已開＝唔重建外框、行同一支 fetch 全 render——第 3 方可直接 call，唔收 event／按鈕）
    async modal_open(project_slug, tab_id, callbacks) {
        // 動態載入（registry——用先載）
        const modal = registry().sc_modal();

        // 準備資料（project_slug""=0 新專案；tab_id=停留嘅 tab）
        const thisObject = this;

        const request_data = {};
        request_data.project_slug = project_slug;
        request_data.tab_id     = tab_id;

        // 搵現有 modal（DOM＝狀態 SSOT——查同搵係同一動作；modal_close 係移除式）
        let j_modal = jQuery('[data-modal="project_modal"]');

        // 未開（搵唔到）：開空 modal（header／body／footer 內容一律由後端供應——前端唔砌、亦唔知道有咩）
        if (j_modal.length === 0) {
            j_modal = await modal.create_modal({
                modal_id: "project_modal",
            });

            j_modal.addClass("visible");
        }


        // 橋接駁（同域 req 橋——新方式）
        const modal_instance = await registry().sc_ai_projects().modal();
        const fetch_project_form_instance = await modal_instance.req().fetch_project_form();

        // 取 modal 內容（params 原樣交出去——後端據 project_slug／tab_id 判斷回咩）
        // 已開／未開行同一條路：同一支 fetch＋同一 success 派發（外框由 PHP；render 交本機側 modal_render）
        await fetch_project_form_instance.fetch_project_form(request_data, {
            before_load: async function () {
                // 動態載入（registry——用先載）
                const loading = registry().sc_loading();

                const j_modal__body = j_modal.find(".modal__body");

                loading.start_panel_loading(j_modal__body, { "mask": { opacity: 0 } });
            },
            success: async function (result) {
                // 動態載入（registry——用先載）
                const loading = registry().sc_loading();

                const j_modal__body = j_modal.find(".modal__body");

                // 設定本次 modal 專案 slug（DOM attr——供 evt 直接讀取）
                j_modal.attr("data-project_slug", result.project_slug || "");

                // 歸檔專案 → 視窗警示主題（琥珀——同歸檔語境一致）；否則 → 還原預設
                if (result.is_archived === true) {
                    j_modal.find(".modal__window").addClass("modal__window--warning");
                } else {
                    j_modal.find(".modal__window").removeClass("modal__window--warning");
                }

                loading.stop_loading(j_modal__body);

                // 外框＋body render（modal 層本機側——slots 直接渲染，body 隨外框已載——唔涉按鈕）
                thisObject.modal_render_slots({ j_modal: j_modal }, result);

                // 派發 hook（內容 render 完成——訂閱者自行判斷；例：規則 tab 掛編輯器）
                registry().sc_ai_projects().execute_hook_after("load_modal_form", { j_modal: j_modal });

                // 交返呼叫端成功回呼（外框＋body 已載完）
                if (
                    callbacks
                    &&  callbacks.success
                ) {
                    callbacks.success({ j_modal: j_modal }, result);
                }
            },
            error: function (result) {
                // 動態載入（registry——用先載）
                const loading = registry().sc_loading();

                const j_modal__body = j_modal.find(".modal__body");

                // 載入相位收結（錯誤都要停——唔留住 loading 相位）
                loading.stop_loading(j_modal__body);

                // 交返呼叫端錯誤回呼（錯誤顯示由呼叫端負責——唔另出 toast）
                if (
                    callbacks
                    &&  callbacks.error
                ) {
                    callbacks.error({ j_modal: j_modal }, result);
                }
            }
        });

        return j_modal;
    }

// 成功回呼：外框 slots（header／tabs／footer）＋body 渲染上去（body 依後端 active_tab_id）
    modal_render_slots(ui_context, result) {
        const j_modal = ui_context.j_modal;

        j_modal.find(".modal__title").text(result["html"]["head"]);
        j_modal.find(".modal__tabs").html(result["html"]["tabs"]);
        j_modal.find(".modal__footer").html(result["html"]["foot"]).show();

        j_modal.find(".modal__body").html(result["html"]["slots"]);

    }
}

export const modal_open = (function (instance) {
    return function () {
        return instance;
    };
})(new _modal_open());
