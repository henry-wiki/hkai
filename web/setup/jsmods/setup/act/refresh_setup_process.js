// jsmods/setup/act/refresh_setup_process.js —— 格選擇記錄（act 層——一 action 一檔）
// 職責：refresh_setup_process（打包目前格嘅具名 field（同 serialize() 同義：radio／checkbox 只收選中·停用唔收）→ 交 req 側出街；值處理交後台；冇 field＝唔交）
// 結構同 model_selector 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { setup } from "../setup.js";

// 域例項（頂層 import 單例——流程內域存取用）
const setup_instance = setup();

class _refresh_setup_process {
    constructor() {}

    // 交走目前格嘅選擇（撳「下一步」嗰刻——同 serialize() 同義：嗰格具名 field 一律打包交去；唔等回應·唔阻切格）
    async refresh_setup_process() {
        const active_section_id = setup_instance.get_current_section_id();
        const j_section_element = setup_instance.get_section_element(active_section_id);
        const j_value_fields = j_section_element.find("input[name], textarea[name], select[name]");
        const value_pairs = [];

        // 逐個 field 收集（名＋值一對——盲收：空值照交·收唔收由後台決定）
        j_value_fields.each(function () {
            const j_value_field = jQuery(this);
            const field_type = j_value_field.attr("type");

            // 判定：radio／checkbox 類型 → 未選中唔收
            if (["radio", "checkbox"].indexOf(field_type) !== -1) {
                // 判定：未選中 → 跳
                if (j_value_field.prop("checked") === false) {
                    return;
                }
            }

            // 判定：停用欄位 → 唔收
            if (j_value_field.prop("disabled") === true) {
                return;
            }

            value_pairs.push([j_value_field.attr("name"), j_value_field.val()]);
        });

        // 判定：一個 field 都冇 → 唔交
        if (value_pairs.length === 0) {
            return;
        }

        // spawn 取 req 組（record_section——連線側；fire-and-forget 唔等回應）
        const record_section_instance = await setup_instance.req().record_section();

        await record_section_instance.record_section(value_pairs, {
            success: function (result) {

            },
            error: function (result) {

            }
        });
    }
}

export const refresh_setup_process = (function (instance) {
    return function () {

        return instance;
    };
})(new _refresh_setup_process());
