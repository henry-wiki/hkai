// web/sc_contents/sc_app/hkai/cls/process/server/server.php —— Server 設定分頁（action 層——一 action 一檔）
// 職責：server（fetch 分頁內容＋test／start／restart／stop 表單流程＋狀態 icon／按鈕 loading／動作後重載）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _server {
    constructor() {}

    // 載入（POST section；回傳 Server 分頁內容 HTML——header 注入 modal__header；失敗回 ""）
    async fetch_server_settings() {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        const result = await shared.sc_request().post_json("/request/hkai/fetch_server_settings/", { section: "server" });

        // 網路／解析層失敗 → ""
        if (result === null) {
            return "";
        }

        return result;
    }

    // 讀取 Server 表單欄位值（host／port）
    get_form_values(j_modal) {
        const server_host = j_modal.find("[data-name='settings[server][host]']").val();
        const server_port = j_modal.find("[data-name='settings[server][port]']").val();

        const form_values = {};
        form_values["host"] = server_host;
        form_values["port"] = server_port;

        return form_values;
    }

    // 連線測試（表單 host／port 現值 → 後端探測 base URL；alert＋Host/Port 列狀態 icon）
    async settings_test_server(j_modal) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        const form_values = this.get_form_values(j_modal);
        const result = await shared.sc_request().post_json("/request/hkai/settings_test_server/", form_values);

        // 判定：請求失敗（無回應）→ alert 後停
        if (result === null) {
            alert("Test failed: request error");
            return;
        }
        // Host/Port 列狀態 icon（後端回傳 state：pass／warning／error）
        const j_host_port_row = j_modal.find("[data-name='settings[server][host]']").closest(".modal-form__row");
        this.set_row_icon(j_host_port_row, result.state);

        alert(result.message);
    }

    // 啟用 server（表單 host／port 現值交後端：佔用檢查＋寫入拉起；狀態轉 Starting——完成後整頁重載）
    async settings_start_server(j_button, j_modal) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        this.begin_button_loading(j_button);
        this.set_server_status(j_modal, "starting");

        // 只交 host；port 由後端自動分配
        const form_values = this.get_form_values(j_modal);
        const start_payload = { host: form_values["host"] };
        const result = await shared.sc_request().post_json("/request/hkai/process/bridge/start_server/", start_payload);


        this.end_button_loading(j_button);

        // 判定：請求失敗（無回應）→ 狀態回落 stopped 後停
        if (result === null) {
            this.set_server_status(j_modal, "stopped");
            return;
        }

        // 判定：後端拒絕（啟動失敗）→ 錯誤彈窗＋狀態回落
        if (result.ok !== true) {
            shared.sc_alert().error(result.error);
            this.set_server_status(j_modal, "stopped");
            return;
        }

        // 成功：用實際埠更新頁面 stream 設定
        this.update_stream_base_url(result.host, result.port);

        // 成功：即刻以實際位址開 presence（唔靠 meta 舊值／下一 tick）
        await registry().sc_presence().ensure_open("http://" + result.host + ":" + result.port);

        // 完成（成功）→ 重載分頁（狀態／按鈕／日誌由後端重畫）
        await this.reload_server_section();
    }

    // 清頁面 stream 設定（server 已停——enabled=false、base_url 空）
    clear_stream_base_url() {
        const meta_element = document.querySelector("meta[name='stream-config']");

        // 判定：頁面冇設定 meta → 唔做
        if (!meta_element) {
            return;
        }

        try {
            const stream_config = JSON.parse(meta_element.getAttribute("content") || "{}");
            stream_config.enabled  = false;
            stream_config.base_url = "";
            meta_element.setAttribute("content", JSON.stringify(stream_config));
        } catch (parse_error) {
            sc_console("settings", "server").error("clear stream base_url failed: " + parse_error.message);
        }
    }

    // 更新頁面 stream 設定（server 實際 host／port——message_stream 下次開連線即用）
    update_stream_base_url(server_host, server_port) {
        // 判定：後端未回實際位址 → 唔做
        if (!server_host || !server_port) {
            return;
        }

        const meta_element = document.querySelector("meta[name='stream-config']");

        // 判定：頁面冇設定 meta → 唔做
        if (!meta_element) {
            return;
        }

        try {
            const stream_config = JSON.parse(meta_element.getAttribute("content") || "{}");
            stream_config.enabled  = true;
            stream_config.base_url = "http://" + server_host + ":" + server_port;
            meta_element.setAttribute("content", JSON.stringify(stream_config));
        } catch (parse_error) {
            sc_console("settings", "server").error("update stream base_url failed: " + parse_error.message);
        }
    }

    // 重新啟動 server（表單現值交後端：停清 → 等 port 放 → 拉起；完成後整頁重載）
    async settings_restart_server(j_button, j_modal) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        this.begin_button_loading(j_button);

        // 只交 host；port 由後端自動分配
        const form_values = this.get_form_values(j_modal);
        const restart_payload = { host: form_values["host"] };
        const result = await shared.sc_request().post_json("/request/hkai/process/bridge/restart_server/", restart_payload);

        this.end_button_loading(j_button);

        // 判定：請求失敗（無回應）→ 停
        if (result === null) {
            return;
        }

        // 判定：成功 → 用實際埠更新頁面 stream 設定＋即刻開 presence
        if (result.ok === true) {
            this.update_stream_base_url(result.host, result.port);

            await registry().sc_presence().ensure_open("http://" + result.host + ":" + result.port);
        }

        // 完成（成功／後端拒絕）→ 重載分頁（狀態／按鈕／日誌由後端重畫）
        await this.reload_server_section();
    }

    // 停用 server（狀態轉 Stopping——完成後整頁重載）
    async settings_stop_server(j_button, j_modal) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        this.begin_button_loading(j_button);
        this.set_server_status(j_modal, "stopping");

        const result = await shared.sc_request().post_json("/request/hkai/process/bridge/stop_server/", {});

        this.end_button_loading(j_button);

        // 判定：請求失敗（無回應）→ 狀態回落 running 後停
        if (result === null) {
            this.set_server_status(j_modal, "running");
            return;
        }

        // 停止：即刻關 presence（唔等 conn 下一 tick，避免舊連線盲重連）
        registry().sc_presence().stop();

        // 停止：清頁面 stream 設定（enabled=false、base_url 空）
        this.clear_stream_base_url();

        // 完成（成功／後端拒絕＝未運行）→ 重載分頁（狀態／按鈕／日誌由後端重畫）
        await this.reload_server_section();
    }

    // 重載 server 分頁（動作完成後——狀態／按鈕／日誌一次過由後端重畫）
    async reload_server_section() {
        // spawn 取組（modal_nav）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_nav_module = await modal_domain.act().modal_nav();

        await modal_nav_module.show_section("server");
    }

    // 按鈕載入開始（轉圈＋不可點擊）
    begin_button_loading(j_button) {
        j_button.prop("disabled", true);
        j_button.addClass("is_loading");
        j_button.prepend("<i class='fa-solid fa-spinner fa-spin'></i>");
    }

    // 按鈕載入結束（還原可點擊）
    end_button_loading(j_button) {
        j_button.find("i.fa-spinner").remove();
        j_button.removeClass("is_loading");
        j_button.prop("disabled", false);
    }

    // 切換 server 狀態文字（四態：stopped／starting／running／stopping——文字＋顏色 class）
    set_server_status(j_modal, server_status) {
        const status_label_map = {};
        status_label_map["stopped"]  = "Stopped";
        status_label_map["starting"] = "Starting";
        status_label_map["running"]  = "Running";
        status_label_map["stopping"] = "Stopping";

        const j_status = j_modal.find(".settings_server_status");
        j_status.attr("class", "settings_server_status settings_server_status--" + server_status);
        j_status.text(status_label_map[server_status]);
    }

    // 設定列狀態 icon（state：pass／warning／error；未知 state → 清空不顯示）
    set_row_icon(j_row, state) {
        const j_icon  = j_row.find(".modal-form__icon");
        const j_glyph = j_row.find(".modal-form__icon i");

        // 判定：pass → 綠勾（fa-circle-check）
        if (state === "pass") {
            j_icon.attr("class", "modal-form__icon modal-form__icon--pass");
            j_glyph.attr("class", "fa-solid fa-circle-check");
        } else if (state === "warning") {
            // 判定：warning → 琥珀警告（fa-triangle-exclamation）
            j_icon.attr("class", "modal-form__icon modal-form__icon--warning");
            j_glyph.attr("class", "fa-solid fa-triangle-exclamation");
        } else if (state === "error") {
            // 判定：error → 紅叉（fa-circle-xmark）
            j_icon.attr("class", "modal-form__icon modal-form__icon--error");
            j_glyph.attr("class", "fa-solid fa-circle-xmark");
        } else {
            // 未知 state → 清空（無 glyph 不顯示）
            j_icon.attr("class", "modal-form__icon");
            j_glyph.attr("class", "fa-solid");
        }
    }

}

export const server = (function (instance) {
    return function () {

        return instance;
    };
})(new _server());
