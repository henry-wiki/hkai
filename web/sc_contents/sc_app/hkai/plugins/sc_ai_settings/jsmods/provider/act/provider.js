// jsmods/provider/act/provider.js —— provider 域動作（act——開單叫工作）
// 職責：fetch_providers（POST 取 Provider 分頁 HTML）／save_provider_settings（單 provider 儲存——寫 .env）／reset_provider_settings（重設：唯讀入編輯）／cancel_provider_reset（取消回唯讀）
// 結構同 sc_ai_settings 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _provider {
    constructor() {}

    // 載入（POST provider 分頁——取右欄內容 HTML——header 併入 modal__header；失敗回 ""）
    async fetch_providers() {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        const result = await shared.sc_request().post_json("/request/hkai/fetch_providers/", {});

        // 判定：網路／解析層失敗 → ""
        if (result === null) {
            return "";
        }

        return result;
    }

    // 儲存單一 provider（掣＝事件來源——讀同列輸入值；空白＝清除〔先確認〕；mask 未變＝後端 no-op）
    async save_provider_settings(j_button) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();
        const i18n = registry().sc_i18n();

        const provider_id = j_button.attr("data-provider");
        const j_row       = j_button.closest(".provider_key_row");
        const api_token   = j_row.find("[data-field='api_token']").val();

        // 判定：輸入空白（＝清除此 key）→ 先出確認警告
        if (api_token.trim() === "") {
            const save_blank_confirmed = confirm(i18n.get("settings.provider.empty_token_confirm"));

            // 判定：用戶取消 → 唔提交（維持畫面）
            if (!save_blank_confirmed) {
                return;
            }
        }

        const request_data = {};
        request_data["provider_id"] = provider_id;
        request_data["api_token"] = api_token;

        const result = await shared.sc_request().post_json("/request/hkai/settings_save_provider_settings/", request_data);

        // 判定：請求失敗（無回應）→ 停（維持畫面）
        if (result === null) {
            return;
        }

        // 判定：欄位驗證失敗 → 套落該列（§18——data-field 定位）
        if (!result.ok && result.errors) {
            shared.sc_web_utils().apply_form_errors(j_row, result.errors);
            return;
        }

        // 判定：其餘失敗 → alert 後停
        if (!result.ok) {
            alert(result.error);
            return;
        }

        // 重載（stay——反映新 mask 與「需重啟」提示；切頁編排在 nav 層）
        // spawn 取組（modal_nav）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_nav_module = await modal_domain.act().modal_nav();

        await modal_nav_module.show_section("provider");
    }

    // 重設該 provider 鎖匙（唯讀態 → 入編輯——純切態；提交由儲存掣出）
    reset_provider_settings(j_button) {
        const j_row = j_button.closest(".provider_key_row");

        j_row.attr("data-provider_state", "resetting");
        j_row.find("[data-provider_part='input']").trigger("focus");
    }

    // 取消重設（回唯讀——清輸入值與錯誤痕跡）
    cancel_provider_reset(j_button) {
        const j_row = j_button.closest(".provider_key_row");
        const j_input = j_row.find("[data-provider_part='input']");

        j_row.attr("data-provider_state", "saved");
        j_input.val("");
        j_input.removeClass("invalid");
        j_row.removeClass("show-err");
        j_row.find(".form_err").text("");
    }
}

export const provider = (function (instance) {
    return function () {

        return instance;
    };
})(new _provider());
