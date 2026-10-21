// jsmods/action/server.js —— Server 設定分頁（action 層——一 action 一檔）
// 職責：server（fetch 分頁內容＋test／start／restart／stop 表單流程＋Live logs 觀察器＋1s 增量輪詢（新行 prepend）＋狀態 icon／按鈕 loading／動作後重載——19 法組檔）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _server {
    constructor() {
        this.auto_refresh_timer_id = null;
        this.auto_refresh_interval = 1000;
        this.log_observer = null;
        this.observed_log_node = null;
        this.log_visible = false;
    }

    // 載入（POST section；回傳 Server 分頁內容 HTML——header 注入 modal__header；失敗回 ""）
    async fetch_server_settings() {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        const result = await shared.sc_request().post_json("/request/hkai/fetch_server_settings/", { section: "server" });

        // 網路／解析層失敗 → ""
        if (result === null) {
            return "";
        }

        // 判定：後端叫停（keep_auto_refresh=false）→ 清掉計時器、不啟動自動更新
        if (!this.should_keep_auto_refresh(result)) {
            this.stop_auto_refresh();
            return result;
        }

        // 自己的行為自己接：本頁載入成功 → 啟動自動更新（idempotent——每輪重置鏈）
        this.start_auto_refresh();

        return result;
    }

    // 載入日誌（帶 offset 標記——0／無＝全量、>0＝增量；失敗回 null）
    async fetch_server_log(log_offset) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        const result = await shared.sc_request().post_json("/request/hkai/fetch_server_log/", { offset: log_offset });

        return result;
    }

    // 啟動自動更新（每 1 秒靜默重載日誌槽——鏈式 setTimeout：fetch 成功才排下一輪）
    start_auto_refresh() {
        const thisObject = this;

        this.stop_auto_refresh();
        this.auto_refresh_timer_id = setTimeout(function () { thisObject.auto_refresh(); }, this.auto_refresh_interval);
    }

    // 停止自動更新（切走 section／關 modal 時呼叫——清掉鏈，不再發 request）
    stop_auto_refresh() {
        if (this.auto_refresh_timer_id) {
            clearTimeout(this.auto_refresh_timer_id);
            this.auto_refresh_timer_id = null;
        }
    }

    // 自動更新一輪（觀察器＋增量——成功 → 排下一輪；失敗／已離開 → 停鏈）
    async auto_refresh() {
        // 動態載入（registry——用先載）

        const thisObject = this;

        // spawn 取 modal 狀態（j_modal／active_section）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_module = await modal_domain.act().settings_modal();

        // 判定：modal 已關（DOM 移除）或已切走 → 停止鏈，不再排下一輪
        if (!modal_module.j_modal || !jQuery.contains(document.body, modal_module.j_modal[0]) || modal_module.active_section !== "server") {
            this.stop_auto_refresh();
            return;
        }

        const j_log = modal_module.j_modal.find(".settings_server_log");

        // 判定：日誌節點未現身（載入中）→ 排下一輪再試
        if (!j_log.length) {
            this.start_auto_refresh();
            return;
        }

        // 觀察器（節點有變先重掛——顯示中先續鏈）
        this.ensure_log_observer(j_log);

        const log_result = await thisObject.fetch_server_log(j_log.attr("data-log_offset"));

        // 判定：抓取失敗（無回應）→ 停鏈（等下次切回本頁再啟動）
        if (log_result === null) {
            this.stop_auto_refresh();
            return;
        }

        // 判定：全量（初次／檔案輪替）→ 容器整隻換；增量 → 新行 prepend（最新喺頂）
        if (log_result.log_mode === "full") {
            this.replace_log_full(j_log, log_result);
        } else {
            this.append_log_lines(j_log, log_result);
        }

        // 判定：後端叫停（keep_auto_refresh=false）→ 停鏈，不再抓
        if (!thisObject.should_keep_auto_refresh(log_result)) {
            this.stop_auto_refresh();
            return;
        }

        this.start_auto_refresh();
    }

    // 後端是否要繼續自動更新（旗標在回應根層：{ok, keep_auto_refresh, log_mode, …}）
    should_keep_auto_refresh(log_result) {
        // 判定：後端明確叫停（false）→ 停鏈
        if (log_result.keep_auto_refresh === false) {
            return false;
        }

        return true;
    }

    // 觀察器（盯日誌節點——顯示中先續鏈；節點被換走 → 下輪重掛）
    ensure_log_observer(j_log) {
        const thisObject = this;
        const log_node = j_log[0];

        // 判定：節點冇變 → 唔使重掛
        if (this.observed_log_node === log_node) {
            return;
        }

        // 卸舊觀察器（節點已換走）
        if (this.log_observer) {
            this.log_observer.disconnect();
        }

        this.observed_log_node = log_node;

        // 判定：瀏覽器唔支援 IntersectionObserver → 當永遠顯示（維持原行為——照樣輪詢）
        if (typeof IntersectionObserver !== "function") {
            this.log_visible = true;
            return;
        }

        this.log_observer = new IntersectionObserver(function (entries) {
            thisObject.log_visible = entries[0].isIntersecting;

            // 判定：顯示中 → 續鏈；唔顯示 → 停鏈
            if (thisObject.log_visible) {
                thisObject.start_auto_refresh();
            } else {
                thisObject.stop_auto_refresh();
            }
        });

        this.log_observer.observe(log_node);
    }

    // 全量換（初次／檔案輪替——後端容器 HTML 直接換；新節點帶新 offset）
    replace_log_full(j_log, log_result) {
        j_log.replaceWith(log_result.log_html);
    }

    // 增量 prepend（新行插頂——最新喺頂；先更新 offset 標記）
    append_log_lines(j_log, log_result) {
        j_log.attr("data-log_offset", log_result.log_offset);
        j_log.prepend(log_result.log_lines_html);
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
        const result = await shared.sc_request().post_json("/request/hkai/settings_start_server/", start_payload);


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

        // 完成（成功）→ 重載分頁（狀態／按鈕／日誌由後端重畫）
        await this.reload_server_section();
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
            stream_config.base_url = "http://" + server_host + ":" + server_port;
            meta_element.setAttribute("content", JSON.stringify(stream_config));
        } catch (parse_error) {
            console.error("update stream base_url failed: " + parse_error.message);
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
        const result = await shared.sc_request().post_json("/request/hkai/settings_restart_server/", restart_payload);

        this.end_button_loading(j_button);

        // 判定：請求失敗（無回應）→ 停
        if (result === null) {
            return;
        }

        // 判定：成功 → 用實際埠更新頁面 stream 設定
        if (result.ok === true) {
            this.update_stream_base_url(result.host, result.port);
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

        const result = await shared.sc_request().post_json("/request/hkai/settings_stop_server/", {});

        this.end_button_loading(j_button);

        // 判定：請求失敗（無回應）→ 狀態回落 running 後停
        if (result === null) {
            this.set_server_status(j_modal, "running");
            return;
        }

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
