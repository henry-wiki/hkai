// jsmods/instructions/act/instructions.js —— Instructions 分頁（action 層——一 action 一檔）
// 職責：instructions（fetch 頁面＋save／reset 編輯流程；編輯器本體＝共用 sc_editor 服務——設定語境專屬控制器）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _instructions {
    constructor() {
        this.editor_controller = null;
    }

    // 編輯器控制器（懶建——本模組唯一實例；語境＝settings modal）
    async get_editor_controller() {
        // 判定：未建 → 建（registry 取 sc_editor 服務）
        if (!this.editor_controller) {
            const editor_service = await registry().sc_editor();
            this.editor_controller = editor_service.create_editor("[data-modal='settings_modal']", "sc_ai_settings_instructions");
        }

        return this.editor_controller;
    }

    // 載入（POST item；回傳 Instructions 頁內容 HTML——header 注入 modal__header；失敗回 ""）
    async fetch_instructions(instructions_name) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        const result = await shared.sc_request().post_json("/request/hkai/fetch_" + instructions_name + "/", {});

        // 網路／解析層失敗 → ""
        if (result === null) {
            return "";
        }

        return result;
    }

    // 掛載編輯器（交共用控制器——設定語境；頁內無 mount 節點＝其它分頁、自動略過）
    async mount_editor(j_modal) {
        const editor_controller = await this.get_editor_controller();

        editor_controller.mount_editor(j_modal);
    }

    // 卸載編輯器（切頁前先移除——唔留殘留實例；無實例＝no-op）
    async teardown_editor() {
        const editor_controller = await this.get_editor_controller();

        editor_controller.teardown_editor();
    }

    // 儲存（確認後——編輯器現值 → 後端寫 storage 工作檔；成功重載分頁）
    async settings_save_instructions(j_modal) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        // 判定：用戶唔確認 → 唔做（標準確認框——讀 data-confirm_msg）
        const j_save_button = j_modal.find("[data-btn='save_instructions']");
        if (!await shared.sc_web_utils().is_confirmed(j_save_button)) {
            return;
        }

        // spawn 取 modal 狀態（active_section＝現行 item）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_module = await modal_domain.act().settings_modal();

        const instructions_name = modal_module.active_section;

        const editor_controller = await this.get_editor_controller();
        const content           = editor_controller.read_editor_content();

        const request_data = {};
        request_data["instructions_name"] = instructions_name;
        request_data["content"]           = content;

        const result = await shared.sc_request().post_json("/request/hkai/settings_save_instructions/", request_data);

        // 判定：請求失敗（無回應）→ alert 後停
        if (result === null) {
            alert("Save failed: request error");
            return;
        }

        // 判定：後端拒絕 → alert 後停
        if (!result.ok) {
            alert(result.error);
            return;
        }

        alert("Saved.");

        await this.reload_instructions_section(instructions_name);
    }

    // Reset（確認後——後端刪走 storage 自訂版；成功重載分頁回出廠默認）
    async settings_reset_instructions(j_modal) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        // 判定：用戶唔確認 → 唔做（標準確認框——讀 data-confirm_msg）
        const j_reset_button = j_modal.find("[data-btn='reset_instructions']");
        if (!await shared.sc_web_utils().is_confirmed(j_reset_button)) {
            return;
        }

        // spawn 取 modal 狀態（active_section＝現行 item）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_module = await modal_domain.act().settings_modal();

        const instructions_name = modal_module.active_section;

        const request_data = {};
        request_data["instructions_name"] = instructions_name;

        const result = await shared.sc_request().post_json("/request/hkai/settings_reset_instructions/", request_data);

        // 判定：請求失敗（無回應）→ alert 後停
        if (result === null) {
            alert("Reset failed: request error");
            return;
        }

        // 判定：後端拒絕 → alert 後停
        if (!result.ok) {
            alert(result.error);
            return;
        }

        alert("Reset.");

        await this.reload_instructions_section(instructions_name);
    }

    // 更新系統環境（後端重新偵測並重寫 manifest 環境檔；唯讀頁——成功重載分頁）
    async settings_refresh_system_env(j_modal) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        // spawn 取 modal 狀態（active_section＝現行 item）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_module = await modal_domain.act().settings_modal();

        const instructions_name = modal_module.active_section;

        const result = await shared.sc_request().post_json("/request/hkai/settings_refresh_system_env/", {});

        // 判定：請求失敗（無回應）→ alert 後停
        if (result === null) {
            alert("Update failed: request error");
            return;
        }

        // 判定：後端拒絕 → alert 後停
        if (!result.ok) {
            alert(result.error);
            return;
        }

        alert("Updated.");

        await this.reload_instructions_section(instructions_name);
    }

    // 重載分頁（切頁編排喺 nav 層——同 server save 一致）
    async reload_instructions_section(instructions_name) {
        // 動態載入（registry——用先載）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_nav_module = await modal_domain.act().modal_nav();

        await modal_nav_module.show_section(instructions_name);
    }
}

export const instructions = (function (instance) {
    return function () {

        return instance;
    };
})(new _instructions());
