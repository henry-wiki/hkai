// jsmods/message/act/message_load_more.js —— 向上補載入口（act 層——一檔一事）
// 職責：message_load_more（向上補載入口——單飛：行緊直回；一叫＝載一頁舊窗（交 load_older_page：插頂 → 灌 slots → render markdown → 派 load_message_list——位置交看守校正）；要唔要第二頁＝外面決定——本檔唔自己迴圈、唔自己重判）
// 觸發源：頂錨觀察（obs/message_intersection_observer——reached_top；上面夠唔夠＝嗰邊嘅政策閘）
// 閘：有窗口（游標落咗）＋單飛（行緊唔重入——載完先釋放）；換對話＝req abort（回應唔返 → 停）
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _message_load_more {
    constructor() {
        // 單飛旗（行緊＝true——期間新觸發直回）
        this.in_flight = false;
    }

    // 向上補載（單飛入口——一叫＝載一頁；要唔要第二頁＝外面再叫）
    async message_load_more() {
        // 判定：行緊 → 直回（單飛——同一時間只准一條再入）
        if (this.in_flight) {
            return;
        }

        const j_messages = jQuery(".sc_thread__viewport");

        // 判定：窗口未載過（游標唔存在——未選對話／未載入）→ 唔做
        if (j_messages.attr("data-message_index") === undefined) {
            return;
        }

        this.in_flight = true;

        try {
            // 唯一要做嘅事：載一頁舊窗（插頂 → 灌 slots → render markdown——每段即場補償）
            await this.load_older_page();
        } finally {
            this.in_flight = false;
        }
    }

    // 載一頁舊窗（req 帶游標 → 插頂（防重疊）→ 灌 slots → render markdown → 更新游標 → 派 load_message_list；位置釘死交看守（message_position_keeper）；回傳有冇載到）
    async load_older_page() {
        const j_messages = jQuery(".sc_thread__viewport");

        // 本頁窗口定位（游標＝本窗最舊 message_id——keyset：後台取 < 游標 嗰頁）
        const current_cursor = parseInt(j_messages.attr("data-message_index"), 10);

        // 向後台取舊窗（req——帶 message_id 游標）
        const message_instance = await registry().sc_ai_chats().message();
        const req_instance = await message_instance.req().message_list_fetch();

        // 成功旗（成功回呼行完＝true——回傳有冇載到）
        let load_ok = false;

        await req_instance.message_list_fetch({
            success: async function (response_data) {
                // 判定：回應唔屬於現行對話（切換後舊請求——唔可以逆寫）→ 停
                if (response_data.chat_sid !== j_messages.attr("data-chat_sid")) {
                    return;
                }

                const message_entries = response_data.html.messages;

                // 判定：回窗冇訊息（冇嘢插）→ 停
                if (message_entries.length === 0) {
                    return;
                }

                // 插入位（現有第一條之前——後台正序插同一參照前＝次序保持）
                const j_first_wrapper = j_messages.children(".message_wrapper").first();
                let j_insert_reference = j_first_wrapper;

                // 判定：窗口一條都冇（空窗）→ 插喺底錨之前
                if (j_first_wrapper.length === 0) {
                    j_insert_reference = j_messages.children("#sc_scroll_anchor");
                }

                // 今批真插咗嘅外殼／資料（稍後只處理呢批——唔全量重跑）
                const inserted_wrapper_elements = [];
                const inserted_entries = [];

                // 逐條插頂（已喺 DOM 嘅 message_id 跳過＝防窗口重疊段）
                for (let index = 0; index < message_entries.length; index++) {
                    const message_entry = message_entries[index];

                    const j_message_wrapper = jQuery(message_entry.template.message);
                    const message_id = j_message_wrapper.attr("data-message_id");

                    // 判定：該 message_id 已經喺窗口（重疊段）→ 跳
                    if (j_messages.find(".message_wrapper[data-message_id=" + message_id + "]").length > 0) {
                        continue;
                    }

                    // 狀態標記：骨架空殼（render_status——state 1 frame；內容未灌）
                    j_message_wrapper.attr("data-render_status", "frame");

                    j_message_wrapper.insertBefore(j_insert_reference);

                    inserted_wrapper_elements.push(j_message_wrapper[0]);
                    inserted_entries.push(message_entry);
                }

                const j_inserted_wrappers = jQuery(inserted_wrapper_elements);

                // 位置校正：交閱讀位置看守（obs/message_position_keeper——mutation batch 尾、畫格前即量即校；本檔唔自行補償）
                // 灌 slots（重用 message_item_render 嘅內容階段——按 message_id 搵外殼；一條一幀等畫）
                const message_item_render_instance = await message_instance.act().message_item_render();
                await message_item_render_instance.message_slot_apply(inserted_entries);

                // render markdown（只今批——逐條；交 render_single_markdown 接駁）
                const message_render_instance = await message_instance.act().message_render();

                for (let index = 0; index < inserted_wrapper_elements.length; index++) {
                    await message_render_instance.render_single_markdown(jQuery(inserted_wrapper_elements[index]));
                }

                // render_status 收結交掛鈎鏈（上面 render_single_markdown 已逐條派 rendered_message_markdown——判定＋有 mermaid 交排隊；完結由 rendered_message_mermaid 收結——本檔唔自行標，同 root 載入側同形）

                // 更新游標（回窗最舊 message_id——下輪 keyset 游標）
                j_messages.attr("data-message_index", response_data.message_id);
                j_messages.attr("data-message_limit", response_data.message_limit);
                j_messages.attr("data-has_more", response_data.has_more);

                // 訊息窗口載入完成 → 派發 load_message_list hook（外部用家各自嘅反應——補載／初次／未選對話同一契約）
                registry().sc_ai_chats().execute_hook_after("load_message_list", []);

                load_ok = true;
            },
            error: function () {
                // 失敗（網絡／被取消——換對話 abort）→ 唔載（load_ok 留 false）
            },
        }, { message_id: current_cursor });

        return load_ok;
    }
}

export const message_load_more = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_load_more());
