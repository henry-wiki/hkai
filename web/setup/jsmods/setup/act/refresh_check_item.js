// jsmods/setup/act/refresh_check_item.js —— 檢查項重跑（act 層——一 action 一檔）
// 職責：start_batch_check（開場／切格後叫——掃本格 init 項逐項單獨查；查緊＝loading＋鎖底列＋報數；完＝解鎖）
// 結構同 model_selector 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { setup } from "../setup.js";

// 域例項（頂層 import 單例——流程內域存取用）
const setup_instance = setup();

class _refresh_check_item {
    constructor() {
        // 開鎖前可用嘅底列掣（解鎖時復原——首格上一步等後端預設唔會被誤開）
        this.enabled_buttons = null;
    }

    // 批次檢查（入口——開場／切格／重新檢查後叫；背景逐項跑唔阻塞）
    async start_batch_check() {
        const j_pending_items = jQuery(".setup_section.is-active .check_item[data-status='init']");

        // 判定：冇待查項 → 唔動
        if (j_pending_items.length === 0) {
            return;
        }

        this.lock_footer();

        let check_item_index;

        // 逐項查（序列——一項完先一項；每步報數）
        for (check_item_index = 0; check_item_index < j_pending_items.length; check_item_index++) {
            await this.start_single_check(j_pending_items.eq(check_item_index));

            this.update_counts();
        }

        this.unlock_footer();
    }

    // 查單一項（標 loading、停控件 → 叫檢查端點 → 換真卡；失敗＝歸回 init 等重查）
    async start_single_check(j_check_item) {
        j_check_item.attr("data-status", "loading");
        this.set_item_controls_disabled(j_check_item);

        const section_id      = setup_instance.get_current_section_id();
        const check_item_code = j_check_item.attr("data-check_item");

        // spawn 取 req 組（start_single_check——連線側：單項重跑）
        const start_single_check_instance = await setup_instance.req().start_single_check();

        await start_single_check_instance.start_single_check(section_id, check_item_code, {
            success: function (result) {
                // 換真卡（後端砌好——前端只 insert）
                j_check_item.replaceWith(result.html.item);
            },
            error: function () {
                // 網絡／失敗 → 歸回 init（保持待查——重新檢查再試）
                j_check_item.attr("data-status", "init");
            }
        });
    }

    // 控件停用（查緊——項內掣同欄位一律停）
    set_item_controls_disabled(j_check_item) {
        const j_field_elements = j_check_item.find("input, select, textarea, button");

        j_field_elements.prop("disabled", true);
    }

    // 鎖底列（查緊——導航掣一律停；可用嗰批留住俾解鎖）
    lock_footer() {
        const j_footer = jQuery(".setup_section.is-active .setup_section-foot");
        const j_footer_buttons = this.get_footer_buttons();

        // 上檢查中記號（重新檢查掣文字切換——純 css 閘）
        j_footer.addClass("is-checking");

        // 記住開鎖前可用嗰批（一個集合——免逐粒記）
        this.enabled_buttons = j_footer_buttons.filter(":enabled");

        // 搵到嘅掣一律停（集合一次過——後端預設停用嘅唔喺上面嗰批）
        j_footer_buttons.prop("disabled", true);
    }

    // 解鎖底列（查完——可用嗰批復原；報數收尾）
    unlock_footer() {
        const j_footer = jQuery(".setup_section.is-active .setup_section-foot");

        // 除檢查中記號（掣文字歸位）
        j_footer.removeClass("is-checking");

        // 可用嗰批復原（後端預設停用嘅維持原狀）
        this.enabled_buttons.prop("disabled", false);

        this.enabled_buttons = null;

        this.update_counts();
    }

    // 取本格底列導航掣（重新檢查／上一步／下一步——data-nav_action 為記）
    get_footer_buttons() {
        return jQuery(".setup_section.is-active .setup_section-foot button[data-nav_action]");
    }

    // 全格報數（span：查緊＝checking；查完＝all／pass／fail）＋組徽章跟數
    update_counts() {
        const j_check_items = jQuery(".setup_section.is-active .check_item[data-check_item]");
        const j_counts_span = jQuery(".setup_section.is-active .check_counts");

        const pass_count     = j_check_items.filter("[data-status='pass']").length;
        const fail_count     = j_check_items.filter("[data-status='fail']").length;
        const checking_count = j_check_items.filter("[data-status='init'], [data-status='loading']").length;

        // 判定：仲有查緊 → 示查緊數；否則示總結數
        if (checking_count > 0) {
            j_counts_span.text("checking: " + checking_count);
        }
        else {
            j_counts_span.text("all: " + j_check_items.length + ", pass: " + pass_count + ", fail: " + fail_count);
        }

        // 逐組徽章（各自子集點數——查緊＝待查；有未通過＝紅；有提醒＝黃；否則全通）
        jQuery(".setup_section.is-active .check_group").each(function () {
            const j_check_group        = jQuery(this);
            const j_group_items        = j_check_group.find(".check_item[data-check_item]");
            const j_badge              = j_check_group.find(".check_group-badge");
            const group_done_count     = j_group_items.filter("[data-status='pass'], [data-status='warn'], [data-status='init'], [data-status='loading']").length;
            const group_fail_count     = j_group_items.length - group_done_count;
            const group_warn_count     = j_group_items.filter("[data-status='warn']").length;
            const group_checking_count = j_group_items.filter("[data-status='init'], [data-status='loading']").length;

            // 判定：仲有查緊 → 徽章保持待查
            if (group_checking_count > 0) {
                j_badge.attr("class", "check_group-badge pending");
                j_badge.text(j_badge.attr("data-text_checking"));

                return;
            }

            // 判定：有未通過 → 徽章轉紅
            if (group_fail_count > 0) {
                j_badge.attr("class", "check_group-badge fail");
                j_badge.text(group_fail_count + j_badge.attr("data-text_fail_suffix"));

                return;
            }

            // 判定：有提醒 → 徽章轉黃
            if (group_warn_count > 0) {
                j_badge.attr("class", "check_group-badge warn");
                j_badge.text(group_warn_count + j_badge.attr("data-text_warn_suffix"));

                return;
            }

            j_badge.attr("class", "check_group-badge pass");
            j_badge.text(j_badge.attr("data-text_pass"));
        });
    }
}

export const refresh_check_item = (function (instance) {
    return function () {

        return instance;
    };
})(new _refresh_check_item());
