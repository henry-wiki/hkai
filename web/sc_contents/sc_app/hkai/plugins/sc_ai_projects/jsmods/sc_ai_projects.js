// jsmods/sc_ai_projects.js —— projects plugin 根入口（唯一入口——出入口掛喺 prototype；對齊 sc_ai_chats.js）
// 用途：registry().sc_ai_projects()（單例）；域接駁：(await registry().sc_ai_projects().project()).act().<動作>() 咁叫
// 對外 API（跨 plugin／bootstrap）：init；register_hook／execute_hook 系（obs:page_loaded／load_project_list／load_project_item／switch_project 派發——機制住 _common/_domain_hooks.js；get_project_slug 歸 project 域）
// 契約：7 域（project／modal／tab_general_form／tab_git／tab_rule／tab_environment／tab_diagnostics）；域動作住各域 <域>.js 檔尾（bridge class——每條自載）

import { _domain_hooks } from "./_common/_domain_hooks.js";

class _sc_ai_projects {
    constructor() {
        this.inited = false;
        this.health_hook_registered = false;
        this.hooks = {};
    }

    // 啟動 plugin（bootstrap 取例項後呼叫——域召集＋hook 表；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 域召集（_common 桶——事件綁定）
        const _common_instance = await this._common();
        await _common_instance.init();

        // 域召集（project 域——事件綁定）
        const project_instance = await this.project();
        await project_instance.init();

        // 域召集（modal 域——事件綁定）
        const modal_instance = await this.modal();
        await modal_instance.init();

        // 域召集（表單 tab 域——事件綁定）
        const tab_general_form_instance = await this.tab_general_form();
        await tab_general_form_instance.init();

        // 域召集（git tab 域——事件綁定）
        const tab_git_instance = await this.tab_git();
        await tab_git_instance.init();

        // 域召集（rule tab 域——事件綁定）
        const tab_rule_instance = await this.tab_rule();
        await tab_rule_instance.init();

        // 域召集（environment tab 域——事件綁定）
        const tab_environment_instance = await this.tab_environment();
        await tab_environment_instance.init();

        // 域召集（diagnostics tab 域——事件綁定）
        const tab_diagnostics_instance = await this.tab_diagnostics();
        await tab_diagnostics_instance.init();

        // hook 登記表（固定 catalog——宣告本 plugin 提供嘅勾點）
        this.hooks["obs:page_loaded"] = [];
        this.hooks["load_project_list"] = [];
        this.hooks["load_project_item"] = [];
        this.hooks["switch_project"] = [];
        this.hooks["obs:project_item_updated"] = [];
        this.hooks["obs:url_changed"] = [];
        this.hooks["load_modal_form"] = [];

        // 專案側 hook 註冊（清單重繪 hook＋觀察器派信號 → 本機側反應——暫時分組）
        await this.project_hook_register();

        // 觀察器綁定（obs 家族——頁面旗標觀察器起一次＋obs 信號嘅反應註冊）
        await this.obs_hook_register();

        // modal 側 hook 註冊（modal 內容 render 完成——規則 tab 掛編輯器）
        await this.modal_hook_register();

        return this;
    }

    // 觀察器綁定（obs 家族——頁面旗標觀察器起一次＋obs 信號嘅反應註冊；項目觀察器係動態，掛點喺 load_project_list——見 project_hook_register）
    async obs_hook_register() {
        const project_instance = await this.project();
        const project_item_fetch_instance         = await project_instance.act().project_item_fetch();
        const project_list_fetch_instance         = await project_instance.act().project_list_fetch();
        const project_list_rank_refresh_instance  = await project_instance.act().project_list_rank_refresh();

        // 頁面旗標觀察器（一次性——盯 body[data-loaded]；成個生命週期起一次，之後唔再掛）
        (await project_instance.obs().page_loaded_observer()).init();

        // 頁面載入反應（body[data-loaded] 由 0 改 1——bootstrap 編排完成；清單載入＋URL 變化註冊嘅唯一時機）
        this.register_hook("obs:page_loaded", {
            after: async function () {

                await project_list_fetch_instance.project_list_fetch();

                // URL 變化註冊（反應＋觀察器——頁面載入後才掛；只報真變化）
                await registry().sc_ai_projects().obs_url_change();
            }
        });

        // 專案項變動反應（project 域觀察器派 obs:project_item_updated → 逐項抓新內容換入；觀察器住 project/obs/project_item_observer.js）
        this.register_hook("obs:project_item_updated", {
            after: async function (affected_project_items) {
                // 逐粒受變更項 → 取身份（project_slug）→ 抓新內容換入（只換 children——節點身份保留）
                for (let item_index = 0; item_index < affected_project_items.length; item_index++) {
                    const affected_project_slug = jQuery(affected_project_items[item_index]).attr("data-project_slug");

                    await project_item_fetch_instance.project_item_fetch(affected_project_slug);
                }

                // 次序交唯一排位者（內容換完後——最後一下——FLIP 自動調位）
                project_list_rank_refresh_instance.project_list_rank_refresh();
            }
        });

    }

    // URL 變化註冊（反應＋觀察器——一次性；觀察器只報真變化）
    async obs_url_change() {
        const project_instance = await this.project();

        // URL 變化觀察器（一次性——訂閱 sc_http；只報真變化）
        (await project_instance.obs().url_changed_observer()).init();

        // URL 變化反應（URL 變化觀察器派 obs:url_changed——帶前一條 URL → 偵測 act：專案有冇真換（換咗＝交項目切換）＋清單語境有冇真換（入／離歸檔＝清單重取））
        this.register_hook("obs:url_changed", {
            after: async function (previous_url) {
                const project_instance = await registry().sc_ai_projects().project();
                const detect_project_slug_change_instance         = await project_instance.act().detect_project_slug_change();
                const detect_project_list_context_change_instance = await project_instance.act().detect_project_list_context_change();

                await detect_project_slug_change_instance.detect_project_slug_change(previous_url);
                await detect_project_list_context_change_instance.detect_project_list_context_change(previous_url);
            }
        });


    }

    // modal 側 hook 註冊（modal 內容 render 完成 → 規則 pane 在場＝掛編輯器／環境 pane 在場＝掛檢視器——派發端喺 modal_open）
    async modal_hook_register() {
        // 接駁（tab_rule 域 act 橋——規則編輯器）
        const tab_rule_instance = await this.tab_rule();
        const rule_editor_instance = await tab_rule_instance.act().rule_editor();

        // 接駁（tab_environment 域 act 橋——環境檢視器）
        const tab_environment_instance = await this.tab_environment();
        const environment_editor_instance = await tab_environment_instance.act().environment_editor();

        // modal render 完成反應（每次 fetch_project_form 後——pane 唔在場＝唔做）
        this.register_hook("load_modal_form", {
            after: async function (ui_context) {
                const j_modal = ui_context.j_modal;

                // 判定：規則 pane 在場（規則 tab）→ 掛規則編輯器
                if (j_modal.find(".modal_pane[data-pane='rule']").length !== 0) {
                    await rule_editor_instance.mount_rule_editor(j_modal);
                }

                // 判定：環境 pane 在場（環境 tab）→ 掛環境檢視器（唯讀——無工具列、無儲存）
                if (j_modal.find(".modal_pane[data-pane='environment']").length !== 0) {
                    await environment_editor_instance.mount_environment_editor(j_modal);
                }
            }
        });
    }

    // 專案側 hook 註冊（清單重繪 hook＋觀察器派信號 → 本機側反應——暫時分組，令 root 好睇）
    async project_hook_register() {
        // 接駁（project 域 obs 橋——項目觀察器預先攞定；load_project_list hook 重掛用）
        const project_instance = await this.project();
        const project_item_observer_instance = await project_instance.obs().project_item_observer();

        const j_sidebar_project_list = jQuery(".sc_sidebar--projects");

        // 清單重繪 hook 反應（清單 DOM 已換——觀察器要重掛＋激活健康更新）
        this.register_hook("load_project_list", {
            before: function () {
                registry().sc_loading().start_panel_loading(j_sidebar_project_list);
            },
            after: async function () {

                registry().sc_loading().stop_loading(j_sidebar_project_list);

                // 清單重繪後同步狀態列（sb_project 讀 .sc_project_list .sc_project_card--active——來源域推：專案清單變咗＝專案側叫同步）
                await registry().sc_ai_status_bar().sync_project();

                // !! 必須留喺呢度（清單載入後才激活）——唔可以搬上 init !!
                // 原因：health 每拍會調整／改變專案列（更新／建立／刪除卡）——早註冊＝對未載入清單盲建卡（project_list_refresh 建立路線）
                await registry().sc_ai_projects().health_hook_register();

                // 項目觀察器重掛（動態——清單加減／重繪後新卡要重新盯；init 內置 disconnect 防重）
                project_item_observer_instance.init();
            }
        });

        // 單項換入 hook 反應（單項內容已換——項目觀察器重掛；同 load_project_list 同形）
        this.register_hook("load_project_item", {
            after: async function () {
                // 項目觀察器重掛（單項內容換咗——重新盯；init 內置 disconnect 防重）
                project_item_observer_instance.init();
            }
        });

        // 切換專案 hook 反應（切換完成——項目觀察器重掛；同 load_project_item 同形）
        this.register_hook("switch_project", {
            after: async function () {
                // 項目觀察器重掛（清單節點郁過——重新盯；init 內置 disconnect 防重）
                project_item_observer_instance.init();
            }
        });
    }

    // 激活健康更新反應（清單載入後呼叫；重入已防——只認第一次）
    async health_hook_register() {
        // 判定：已激活 → 唔重複登記（load_project_list 每次清單載入都派；只認第一次）
        if (this.health_hook_registered) {
            return;
        }

        this.health_hook_registered = true;

        // 接駁（health plugin 根——登記健康更新反應）
        const health_instance = registry().sc_ai_health();

        // 接駁（project 域 act 橋——反應同步用）
        const project_instance = await this.project();
        const project_list_refresh_instance = await project_instance.act().project_list_refresh();

        // 健康更新 hook 反應（通訊由 chats 發起——登記喺 health 側；每輪執行）
        health_instance.register_hook("health_updated", {
            before: function () {

            },
            after: async function (health_result) {
                // 刷新專案列（health_result 直入——更新／補缺／刪除／刷狀態全交 project_list_refresh）
                await project_list_refresh_instance.project_list_refresh(health_result);
            }
        });
    }

}

// 連接（出入口掛上 prototype——唯一入口；動態載入＝用先載）
_sc_ai_projects.prototype._common          = async function () { return (await import("./_common/_common.js"))._common(); };
_sc_ai_projects.prototype.project          = async function () { return (await import("./project/project.js")).project(); };
_sc_ai_projects.prototype.modal            = async function () { return (await import("./modal/modal.js")).modal(); };
_sc_ai_projects.prototype.tab_general_form = async function () { return (await import("./tab_general_form/tab_general_form.js")).tab_general_form(); };
_sc_ai_projects.prototype.tab_git          = async function () { return (await import("./tab_git/tab_git.js")).tab_git(); };
_sc_ai_projects.prototype.tab_rule         = async function () { return (await import("./tab_rule/tab_rule.js")).tab_rule(); };
_sc_ai_projects.prototype.tab_environment  = async function () { return (await import("./tab_environment/tab_environment.js")).tab_environment(); };
_sc_ai_projects.prototype.tab_diagnostics  = async function () { return (await import("./tab_diagnostics/tab_diagnostics.js")).tab_diagnostics(); };

// 連接（hook 機制掛上 root——唯一位置；F12 追唔到為已知代價）
Object.assign(_sc_ai_projects.prototype, {
    register_hook       : _domain_hooks.prototype.register_hook,
    execute_hook_before : _domain_hooks.prototype.execute_hook_before,
    execute_hook_after  : _domain_hooks.prototype.execute_hook_after,
});

export const sc_ai_projects = (function (instance) {
    return function () {

        return instance;
    };
})(new _sc_ai_projects());
