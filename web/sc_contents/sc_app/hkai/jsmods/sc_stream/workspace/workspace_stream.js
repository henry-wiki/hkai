// jsmods/sc_stream/workspace/workspace_stream.js —— 工作區工具狀態 SSE（單例）
// 職責：open(project_slug)——開 /workspace_stream/；收到 git 事件 → 交狀態列渲染；close()
// 上游：sc_stream.js（init／URL 變化）
// 下游：sc_ai_status_bar status_bar().act().git_status_item()

class _workspace_stream {
    constructor() {
        this.event_source = null;
        this.project_slug = "";
        this.chat_sid = "";

        // 訂閱者（{event_name, handler}——外面自行登記，本檔唔識任何消費者）
        this.subscribers = [];

        // 已掛 listener 嘅事件名（由伺服器第一幀名單而來——本檔唔寫死）
        this.bound_event_names = [];

        // 每個事件最新一份（遲訂閱者補發用）
        this.last_payloads = {};

        // 事件處理鏈（嚴格 FIFO——每個訂閱者 handler await 完才下一個；幀與幀之間唔會插隊）
        this.event_chain = Promise.resolve();
    }

    // 開線（同一個 project_slug＋chat_sid 已連 → 唔重開）
    open(project_slug, chat_sid) {
        // 判定：同一位置已連住 → 唔重開
        if (this.project_slug === project_slug && this.chat_sid === chat_sid && this.event_source !== null) {
            return;
        }

        this.close();

        this.project_slug = project_slug;
        this.chat_sid     = chat_sid;

        const stream_config = registry().sc_stream().get_config();

        // 判定：未啟用串流／冇 base_url → 唔開
        if (stream_config.enabled !== true || !stream_config.base_url) {
            return;
        }

        const stream_url = stream_config.base_url
            + "/workspace_stream?project_slug=" + encodeURIComponent(project_slug)
            + "&chat_sid=" + encodeURIComponent(chat_sid);

        const event_source = new EventSource(stream_url);

        // 第一幀（無名）＝事件名單；之後照名單自行掛 listener（本檔零寫死事件名）
        event_source.onmessage = this._on_manifest.bind(this);

        this.bound_event_names = [];
        this.last_payloads = {};
        this.event_chain = Promise.resolve();
        this.event_source = event_source;
    }

    // 關線
    close() {
        // 判定：冇連線 → 唔做
        if (this.event_source === null) {
            return;
        }

        this.event_source.close();
        this.event_source = null;
        this.project_slug = "";
        this.chat_sid = "";
    }

    // 訂閱（event_name＝"git"…；回取消函數——外面（例如狀態列 obs）自行登記）
    subscribe(event_name, handler) {
        const subscriber = { event_name: event_name, handler: handler };

        this.subscribers.push(subscriber);

        // 判定：已有該事件最新一份 → 即刻補一次（訂閱遲過開線都唔會漏）
        if (Object.prototype.hasOwnProperty.call(this.last_payloads, event_name) === true) {
            handler(this.last_payloads[event_name]);
        }

        const thisObject = this;

        return function () {
            thisObject.subscribers = thisObject.subscribers.filter(function (subscriber_item) {
                return subscriber_item !== subscriber;
            });
        };
    }

    // UI 現況收集（ACK 回報用；交標準收集器——本檔唔自己砌結構）
    async collect_observed(event_name) {
        const { workspace_observed } = await import("./workspace_observed.js");

        return workspace_observed().collect(event_name);
    }

    // 收到無名幀（事件名單）→ 逐個名自行掛 listener（前端零寫死事件名）
    _on_manifest(event) {
        let manifest = null;

        // 判定：解析失敗 → 唔做
        try {
            manifest = JSON.parse(event.data);
        } catch (parse_error) {
            return;
        }

        const event_names = manifest.events || [];

        // 逐個事件名：未掛過就掛
        for (let name_index = 0; name_index < event_names.length; name_index++) {
            const event_name = String(event_names[name_index]);

            // 判定：已掛過 → 跳過
            if (this.bound_event_names.indexOf(event_name) >= 0) {
                continue;
            }

            this.bound_event_names.push(event_name);

            // 判定：冇連線物件（已關）→ 停
            if (this.event_source === null) {
                return;
            }

            this.event_source.addEventListener(event_name, this._on_named_event.bind(this, event_name));
        }
    }

    // 具名事件 → 記住最新一份＋派給訂閱者（入嚴格 FIFO 鏈）
    _on_named_event(event_name, event) {
        const thisObject = this;
        let event_payload = null;

        // 判定：解析失敗 → 唔派
        try {
            event_payload = JSON.parse(event.data);
        } catch (parse_error) {
            return;
        }

        this.last_payloads[event_name] = event_payload;

        // 入鏈（逐幀處理完才下一幀——排位／補卡等非同步工作唔會被下一幀插隊）
        this.event_chain = this.event_chain
            .then(function () {
                return thisObject._dispatch(event_name, event_payload);
            })
            .catch(function (dispatch_error) {
                sc_console("workspace", "stream").error("派發失敗", event_name, dispatch_error);
            });
    }

    // 派發（逐個訂閱者 await——確保「先套用、後排位」嘅次序）
    async _dispatch(event_name, event_payload) {
        // 逐個訂閱者派
        for (let subscriber_index = 0; subscriber_index < this.subscribers.length; subscriber_index++) {
            const subscriber_item = this.subscribers[subscriber_index];

            // 判定：唔同事件 → 跳過
            if (subscriber_item.event_name !== event_name) {
                continue;
            }

            // await：handler 可能係 async（套用快照／補卡）——完成才到下一個
            await subscriber_item.handler(event_payload);
        }
    }
}

export const workspace_stream = (function (instance) {
    return function () {

        return instance;
    };
})(new _workspace_stream());
