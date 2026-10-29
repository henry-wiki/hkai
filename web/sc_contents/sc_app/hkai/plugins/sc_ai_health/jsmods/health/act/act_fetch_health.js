// jsmods/health/act/act_fetch_health.js —— health 監視引擎（action 層——一 action 一檔）
// 職責：維持心跳——init（初始化——唔啟動）／start（開始抓取）／stop（停止＋中止進行中請求）／restart（立即重跳）；輪詢迴圈已退役（見 jsmods/_obsolete/check_health.js）
// 備註：啟動決定權喺 root（sc_ai_health）——init 即開；SSE 一直開（唔再跟分頁可見性）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _act_fetch_health {
    constructor() {
        this.running = false;
        this.abort_controller = null;

        // SSE 狀態串流（有變才推——收到即跑一輪）
        this.stream_url      = "/request/hkai/health_stream/";
        // baseline 由後端 viewers/{viewer_id}.json 負責（前端不再帶版本）
        this.event_source    = null;
        this.stream_token    = 0;
    }

    // 初始化（對外——root（sc_ai_health）呼叫；唔啟動——啟動由 root 顯式叫 start()）
    init() {
        return this;
    }

    // 停止（對外——關 SSE＋中止進行中請求）
    stop() {
        const thisObject = this;

        // 中止進行中的請求（停止後不殘留——同步中止，唔等動態載入）
        if (thisObject.abort_controller) {
            thisObject.abort_controller.abort();
        }

        thisObject.close_stream();

        thisObject.running = false;
    }

    // 開 SSE（有變才推——收到即跑一輪；斷線由 EventSource 自動重連）
    async open_stream() {
        const thisObject = this;

        thisObject.close_stream();

        // 判定：瀏覽器唔支援 EventSource → 唔開
        if (typeof EventSource === "undefined") {
            return;
        }

        // 開線令牌（await 期間再有 open／close → 本次作廢，避免兩條並存）
        thisObject.stream_token = thisObject.stream_token + 1;
        const open_token = thisObject.stream_token;

        // 當前專案（git 快照用）
        const project_instance = await registry().sc_ai_projects().project();
        const project_slug = project_instance.get_project_slug();

        // 當前對話（prompt 類 monitor 用）
        const chat_instance = await registry().sc_ai_chats().chat();
        const chat_sid = chat_instance.get_chat_sid();

        // 判定：等待期間已被取代 → 唔建立
        if (thisObject.stream_token !== open_token) {
            return;
        }

        // 本頁 viewer id（頁面 meta——每個開著的頁面一個）
        const viewer_meta = document.querySelector("meta[name='viewer-id']");
        const viewer_id = (viewer_meta === null) ? "" : viewer_meta.getAttribute("content");

        const stream_url = thisObject.stream_url
            + "?project_slug=" + encodeURIComponent(project_slug)
            + "&chat_sid=" + encodeURIComponent(chat_sid)
            + "&viewer_id=" + encodeURIComponent(viewer_id);
        const event_source = new EventSource(stream_url);

        // 收到 status event → 只按 changed 清單更新對應 UI 項
        event_source.addEventListener("status", async function (event) {
            // 判定：已停 → 唔處理
            if (thisObject.running !== true) {
                return;
            }

            let change_event = null;

            // 判定：解析失敗 → 跳過
            try {
                change_event = JSON.parse(event.data);
            } catch (parse_error) {
                return;
            }

            const changed_items = change_event.changed;

            // 判定：冇 changed → 跳過
            if (Array.isArray(changed_items) === false) {
                return;
            }

            // Console 可讀輸出（wire 保持 compact——要睇詳情喺呢度睇）
            //console.log("[health_stream] changed: " + changed_items.join(", "));
            //console.log(JSON.stringify(change_event, null, 2));

            // 判定：project 有變 → 交 act_project_updater 逐 slug 套用（零 fetch）
            if (changed_items.includes("project")) {
                const stream_instance = await registry().sc_ai_health().stream();
                const project_updater_instance = await stream_instance.act().act_project_updater();

                await project_updater_instance.apply_project_change(change_event.project);
            }

            // 判定：chat 有變 → 交 act_chat_updater 逐 chat_sid 套用
            if (changed_items.includes("chat")) {
                const stream_instance = await registry().sc_ai_health().stream();
                const chat_updater_instance = await stream_instance.act().act_chat_updater();

                await chat_updater_instance.apply_chat_change(change_event.chat);
            }

            // 判定：message 有變 → 交 act_message_updater（本頁對話有變才跑一輪 message_poll）
            if (changed_items.includes("message")) {
                const stream_instance = await registry().sc_ai_health().stream();
                const message_updater_instance = await stream_instance.act().act_message_updater();

                await message_updater_instance.apply_message_change(change_event.message);
            }

            // 判定：git 有變 → 交 act_git_updater 套落狀態欄
            if (changed_items.includes("git")) {
                const stream_instance = await registry().sc_ai_health().stream();
                const git_updater_instance = await stream_instance.act().act_git_updater();

                await git_updater_instance.apply_git_change(change_event.git);
            }

            // 判定：conn 有變 → 交 act_conn_updater 套落狀態欄
            if (changed_items.includes("conn")) {
                const stream_instance = await registry().sc_ai_health().stream();
                const conn_updater_instance = await stream_instance.act().act_conn_updater();

                await conn_updater_instance.apply_conn_change(change_event.conn);
            }

            // 判定：prompt 有變 → 只處理本頁對話（key＝{project_slug}/{chat_sid}）
            if (changed_items.includes("prompt")) {
                const change_detail = change_event.prompt;
                const item_changes = change_detail.changes || {};
                const draft_key = project_slug + "/" + chat_sid;
                const draft_change = item_changes[draft_key];

                // 判定：本頁草稿有變（added／modified／deleted）→ 交 act_prompt_updater 套用
                if (draft_change) {
                    const stream_instance = await registry().sc_ai_health().stream();
                    const prompt_updater_instance = await stream_instance.act().act_prompt_updater();

                    await prompt_updater_instance.apply_prompt_change(draft_change);
                }
            }

        });

        thisObject.event_source = event_source;
    }

    // 關 SSE（停／重啟時清走）
    close_stream() {
        // 令牌 +1（作廢所有尚未建立完成嘅 open_stream）
        this.stream_token = this.stream_token + 1;

        // 判定：有連線 → 關閉
        if (this.event_source !== null) {
            this.event_source.close();
            this.event_source = null;
        }
    }

    // 開始監視（root 顯式啟動／restart 用——先停再起；之後只靠 SSE 變化通知）
    start() {
        const thisObject = this;

        thisObject.stop();

        thisObject.running = true;

        thisObject.open_stream();

        return thisObject;
    }

}

export const act_fetch_health = (function (instance) {
    return function () {

        return instance;
    };
})(new _act_fetch_health());
