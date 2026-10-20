// jsmods/setup/act/refresh_install_item.js —— 安裝（act 層——一 action 一檔）
// 職責：refresh_install_item（「安裝」主掣——逐項一項一項跑：掃未完成行〔init／fail〕→ 逐行標 loading → 交 req 側跑該項 → 換行 HTML；鎖底列；收尾＝回應帶新底列〔安裝掣轉完成掣〕換走；網絡失敗＝行歸 init 停）
// 結構同 model_selector 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { setup } from "../setup.js";

// 域例項（頂層 import 單例——流程內域存取用）
const setup_instance = setup();

class _refresh_install_item {
    constructor() {
        // 開鎖前可用嘅底列掣（解鎖時復原——後端預設停用嘅唔會被誤開）
        this.enabled_buttons = null;
    }

    // 「安裝」主掣（入口——逐項跑）
    async refresh_install_item() {
        await this.start_batch_install();
    }

    // 逐項安裝（開場掃未完成行——一項完先一項；每行換新行）
    async start_batch_install() {
        const j_pending_items = jQuery(".setup_section.is-active .check_item[data-install_status='init'], .setup_section.is-active .check_item[data-install_status='fail']");

        // 判定：冇待裝項 → 唔動
        if (j_pending_items.length === 0) {
            return;
        }

        this.lock_footer();

        let install_item_index;

        // 逐項裝（序列——一項完先一項；一項失敗＝停）
        for (install_item_index = 0; install_item_index < j_pending_items.length; install_item_index++) {
            const j_install_item = j_pending_items.eq(install_item_index);
            const install_step_ok = await this.start_single_install(j_install_item);

            // 判定：該項唔成功 → 停（其餘唔做）
            if (install_step_ok !== true) {
                break;
            }
        }

        this.unlock_footer();
    }

    // 裝單一項（標 loading → 叫安裝端點 → 換行；串連失敗＝歸 init 等重裝）
    async start_single_install(j_install_item) {
        j_install_item.attr("data-install_status", "loading");

        const section_id      = j_install_item.attr("data-section_id");
        const check_item_code = j_install_item.attr("data-check_item_code");

        // spawn 取 req 組（start_single_install——連線側：單項安裝）
        const start_single_install_instance = await setup_instance.req().start_single_install();

        let install_step_ok = null;

        await start_single_install_instance.start_single_install(section_id, check_item_code, {
            success: function (result) {
                // 換行（新行＝後端砌好——前端只 insert）
                j_install_item.replaceWith(result.html.item);

                // 判定：回應帶新底列（收尾——安裝掣轉「完成」掣）→ 換走底列
                if (result.html.footer) {
                    const j_section_foot = jQuery(".setup_section.is-active .setup_section-foot");

                    j_section_foot.replaceWith(result.html.footer);
                }

                install_step_ok = result.install_ok;
            },
            error: function () {
                // 網絡／失敗 → 歸回 init（保持待裝——重新撳再試）
                j_install_item.attr("data-install_status", "init");
            }
        });

        return install_step_ok;
    }

    // 鎖底列（裝緊——底列掣一律停；可用嗰批留住俾解鎖）
    lock_footer() {
        const j_footer = jQuery(".setup_section.is-active .setup_section-foot");
        const j_footer_buttons = this.get_footer_buttons();

        // 記住開鎖前可用嗰批（一個集合——免逐粒記）
        this.enabled_buttons = j_footer_buttons.filter(":enabled");

        // 搵到嘅掣一律停（集合一次過——後端預設停用嘅唔喺上面嗰批）
        j_footer_buttons.prop("disabled", true);
    }

    // 解鎖底列（裝完——可用嗰批復原）
    unlock_footer() {
        // 可用嗰批復原（後端預設停用嘅維持原狀）
        this.enabled_buttons.prop("disabled", false);

        this.enabled_buttons = null;
    }

    // 取本格底列全部掣（安裝／上一步——一律鎖）
    get_footer_buttons() {
        return jQuery(".setup_section.is-active .setup_section-foot button");
    }
}

export const refresh_install_item = (function (instance) {
    return function () {

        return instance;
    };
})(new _refresh_install_item());
