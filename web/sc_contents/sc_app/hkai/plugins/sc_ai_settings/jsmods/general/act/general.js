// jsmods/general/act/general.js —— general 域動作（act——開單叫工作）
// 職責：general（fetch 分頁內容＋save 偏好儲存）
// 結構同 sc_ai_settings 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _general {
    constructor() {}

    // 載入（POST section；回傳 General 分頁內容 HTML——header 注入 modal__header；失敗回 ""）
    async fetch_general() {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        const result = await shared.sc_request().post_json("/request/hkai/fetch_general/", { section: "general" });

        // 網路／解析層失敗 → ""
        if (result === null) {
            return "";
        }

        return result;
    }

    // 讀取 General 表單欄位值（逐個 data-field 收集——表單擴項免改）
    get_form_values(j_modal) {
        const form_values = {};

        j_modal.find("[data-field]").each(function (index, field_element) {
            const j_field = jQuery(field_element);

            form_values[j_field.attr("data-field")] = j_field.val();
        });

        return form_values;
    }

    // 儲存 General 偏好（儲存鈕載入 → 後端驗證寫檔；成功彈窗後重載分頁、失敗套錯／錯誤彈窗）
    async settings_save_general(j_save_button, j_modal) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();
        const loading = registry().sc_loading();
        const i18n = registry().sc_i18n();

        const form_values = this.get_form_values(j_modal);

        // 儲存鈕載入（遮罩定位 sc_btn_wrapper；request 期間不可點）
        loading.start_basic_loading(j_save_button);

        const result = await shared.sc_request().post_json("/request/hkai/settings_save_general/", form_values);

        loading.stop_loading(j_save_button);

        // 判定：請求失敗（無回應）→ 錯誤彈窗後停
        if (result === null) {
            shared.sc_alert().error(i18n.get("settings.general.save_request_failed"));
            return;
        }

        // 判定：欄位驗證失敗 → 套落表單（§18——data-field 定位）後停
        if (!result.ok && result.errors) {
            shared.sc_web_utils().apply_form_errors(j_modal, result.errors);
            return;
        }

        // 判定：其餘失敗 → 錯誤彈窗後停
        if (!result.ok) {
            shared.sc_alert().error(result.error);
            return;
        }

        // 成功 → 成功彈窗（關閉後）＋整組 settings 外殼重畫（語言可能已變——左選單同右欄一齊出返新語言）
        await shared.sc_alert().success(i18n.get("settings.general.saved"));
        await this.reload_settings_modal();
    }

    // 重畫整個 settings modal（存檔完成後——左選單重抓＋右欄重載；語言變更即時反映）
    async reload_settings_modal() {
        // spawn 取組（settings_modal——外殼擁有者：重抓 nav、重載 section）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_module = await modal_domain.act().settings_modal();

        await modal_module.render_setting_data(modal_module.j_modal, "general");
    }
}

export const general = (function (instance) {
    return function () {

        return instance;
    };
})(new _general());
