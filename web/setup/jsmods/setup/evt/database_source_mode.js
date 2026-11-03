// jsmods/setup/evt/database_source_mode.js —— 「資料庫來源」綁定（evt 層——一檔一事）
// 職責：database_source_mode（模式切換即保存；舊安裝目錄→掃描→保存；下一步前擋空／擋無效）

import { setup } from "../setup.js";

// 域例項（頂層 import 單例——流程內域存取用）
const setup_instance = setup();

class _database_source_mode {
    constructor() {
        // 上次已掃描目錄（避免 blur 重複查）
        this.last_checked_source_dir = "";
    }

    // 綁定註冊（域召集呼叫——錨 modal 根；頁面初始化一次）
    init() {
        const j_modal = setup_instance.get_modal_element();

        j_modal.off("change.setup_database_source_mode").on("change.setup_database_source_mode", this.on_mode_change.bind(this));
        j_modal.off("change.setup_database_source_dir blur.setup_database_source_dir").on("change.setup_database_source_dir blur.setup_database_source_dir", this.on_source_dir_change.bind(this));
    }

    // 模式切換（顯示／隱藏目錄行 → 即時保存）
    async on_mode_change(event) {
        const j_mode_input = jQuery(event.target).closest("input[name='formdata[welcome][source_mode][value]']");

        // 判定：唔係來源模式 radio → 唔動
        if (j_mode_input.length === 0) {
            return;
        }

        const j_database_section = setup_instance.get_section_element("welcome");
        const j_path_row         = j_database_section.find(".setup_db_source-path");
        const selected_mode      = j_mode_input.val();

        // 判定：搬遷模式 → 顯示目錄行
        if (selected_mode === "import") {
            j_path_row.removeClass("is-hidden");
        } else {
            j_path_row.addClass("is-hidden");
        }

        await this.save_current_source();
    }

    // 目錄輸入（失焦／Enter）→ 掃描 → 通過即保存
    async on_source_dir_change(event) {
        const j_dir_input = jQuery(event.target).closest(".setup_db_source-input");

        // 判定：唔係目錄輸入框 → 唔動
        if (j_dir_input.length === 0) {
            return;
        }

        const source_dir = String(j_dir_input.val() || "").trim();

        // 判定：清空 → 清狀態＋保存
        if (source_dir === "") {
            this.render_status("", "");
            this.last_checked_source_dir = "";

            await this.save_current_source();

            return;
        }

        // 判定：同一目錄已掃描過 → 只保存
        if (source_dir === this.last_checked_source_dir) {
            await this.save_current_source();

            return;
        }

        const scan_result = await this.scan_source(source_dir);

        // 判定：通過掃描（有版本）→ 保存
        if (scan_result !== null && scan_result.ok === true && scan_result.version !== 0) {
            await this.save_current_source();
        }
    }

    // 下一步前驗證（搬遷模式：目錄必須存在且掃描通過）
    async validate_before_next() {
        const source_state = this.get_current_source();

        // 判定：預設模式 → 放行
        if (source_state.mode !== "import") {
            return true;
        }

        // 判定：目錄空 → 擋（搬遷模式必須填）
        if (source_state.source_dir === "") {
            this.render_status("fail", this.get_message("empty"));

            return false;
        }

        const scan_result = await this.scan_source(source_state.source_dir);

        // 判定：網絡失敗 → 擋
        if (scan_result === null) {
            return false;
        }

        // 判定：掃描失敗（目錄不存在／找不到資料庫）→ 擋
        if (scan_result.ok !== true || scan_result.version === 0) {
            return false;
        }

        await this.save_current_source();

        return true;
    }

    // 叫後端掃描＋顯示結果
    async scan_source(source_dir) {
        this.last_checked_source_dir = source_dir;

        this.render_status("", "…");

        const check_instance = await setup_instance.req().check_migration_source();
        const scan_result    = await check_instance.check_migration_source(source_dir);

        // 判定：網絡失敗 → 顯示失敗
        if (scan_result === null) {
            this.render_status("fail", this.get_message("network"));

            return null;
        }

        this.render_status(scan_result.status || "fail", scan_result.message || "");

        return scan_result;
    }

    // 取目前選擇（DOM 為準）
    get_current_source() {
        const j_database_section = setup_instance.get_section_element("welcome");
        const source_mode        = String(j_database_section.find("input[name='formdata[database][source_mode][value]']:checked").val() || "default");
        const source_dir         = String(j_database_section.find(".setup_db_source-input").val() || "").trim();

        const source_state = {};
        source_state.mode       = source_mode;
        source_state.source_dir = source_dir;

        return source_state;
    }

    // 即時保存（交統一遞交路徑——record_section：收集本格具名 field）
    async save_current_source() {
        const refresh_instance = await setup_instance.act().refresh_setup_process();

        await refresh_instance.refresh_setup_process();
    }

    // 顯示狀態行（class＋文字）
    render_status(status_class, message) {
        const j_database_section = setup_instance.get_section_element("welcome");
        const j_status           = j_database_section.find(".setup_db_source-status");

        j_status.attr("class", "setup_db_source-status " + status_class).text(message);
    }

    // 取訊息（伺服器已渲染喺容器 data-msg_empty——同後端同一句）
    get_message(message_key) {
        // 判定：空目錄提示
        if (message_key === "empty") {
            const j_database_section = setup_instance.get_section_element("welcome");

            return String(j_database_section.find(".setup_db_source").attr("data-msg_empty") || "");
        }

        return "";
    }
}

export const database_source_mode = (function (instance) {
    return function () {

        return instance;
    };
})(new _database_source_mode());
