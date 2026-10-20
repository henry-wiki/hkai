// jsmods/message/act/message_list_fetch.js —— 訊息窗口載入（act 層——一檔一事）
// 職責：message_list_fetch（訊息窗口載入——未選對話＝交 message_list_default act（清窗＋空狀態＋marker；唔向後台攞）；有對話＝後端取窗口（成功先鋪骨架框——內容交 root 側逐條套用）＋派發 load_message_list＋落窗口游標（data-message_index＝本窗最舊 message_id／data-message_limit）；錯 sid＝後端當未選回空狀態（同一視覺）；回傳成功旗 load_ok）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _message_list_fetch {
    constructor() {}

    // 訊息窗口載入（換主後叫——未選對話行本機路徑、有對話成功先鋪骨架；回傳成功旗；跳底／揭示＝message after 做）
    async message_list_fetch(callbacks) {
        const thisObject = this;

        callbacks = typeof callbacks !== "object" ? {} : callbacks;

        // 橋接駁（同域——instance 畀 req）
        const chats = registry().sc_ai_chats();
        const message_instance = await chats.message();

        // 現時選取嘅對話（SSOT＝chats 域——未選＝空；行邊條路全靠佢）
        const chat_instance = await chats.chat();
        const chat_sid = chat_instance.get_chat_sid();

        // 窗口容器
        const j_messages = jQuery(".sc_thread__viewport");

        // 成功旗（成功回呼行完＝true——載入後反應據此判斷）
        let load_ok = false;

        // 判定：未選對話（URL 未帶 sid——唔發請求、唔起 poll；錯 sid 行後端路線，由後端判未選）
        if (chat_sid === "") {
            // 未有訊息窗狀態（清窗＋亮常駐空狀態＋落 marker＋派 hook——專責 act）
            const message_list_default_instance = await message_instance.act().message_list_default();

            return await message_list_default_instance.message_list_default();
        }

        // 派發 load_message_list hook（before 相位——訊息載入前；跨模組用家各自嘅反應）
        chats.execute_hook_before("load_message_list", []);

        // 載入相位起步（load_status＝loading——由起請求跨到載入 hook 收結；期間兩門初判閘＝唔叫，防骨架期誤補）
        j_messages.attr("data-load_status", "loading");

        // 有對話 → 向後台取窗口（req 橋——新方式）
        const message_list_fetch_instance = await message_instance.req().message_list_fetch();

        await message_list_fetch_instance.message_list_fetch({
            success: async function (response_data) {
                // 容器層「未選專案」空狀態（節點長駐——有專案載入即隱藏；唔移除——返 home 要再用）
                jQuery(".sc_thread > .sc_empty_state").prop("hidden", true);

                // 模板錨點（固定結構——唯一來源＝chat/view.php；換訊息唔可以抺佢）
                const j_scroll_anchor = j_messages.children("#sc_scroll_anchor");

                // 清舊訊息（只抺內容——錨點同常駐空狀態節點保留；後台插入嘅空狀態一併清）
                j_messages.children().not("#sc_scroll_anchor").not(".sc_thread__empty_state").remove();

                // 判定：對話有訊息（message_count > 0——後台窗口界）→ 渲染訊息項目
                if (response_data.message_count > 0) {
                    // 訊息項目渲染（外殼＋內容一體——act/message_item_render；一條一幀等畫）
                    const message_entries = response_data.html.messages;

                    const message_item_render_instance = await message_instance.act().message_item_render();

                    await message_item_render_instance.message_item_render(message_entries);
                }

                // 空狀態刷新（訊息區——自動偵測窗口訊息數：有＝收埋／零＝顯示；一句引用搞掂）
                thisObject.refresh_empty_state_msg_select_project();

                // 容器落窗口標記（chat_sid marker——現行顯示嘅對話；窗口游標——現行窗口最舊 message_id／頁大——向上補載嘅 SSOT）
                // 判定：回應嘅對話就係現行選取 → 落標記（切換後舊請求嘅回應唔可以逆寫；同值唔白寫）
                if (response_data.chat_sid === chat_instance.get_chat_sid()) {
                    j_messages.attr("data-chat_sid", response_data.chat_sid);
                    j_messages.attr("data-message_index", response_data.message_id);
                    j_messages.attr("data-message_limit", response_data.message_limit);
                    j_messages.attr("data-has_more", response_data.has_more);
                }

                // 載入標記（data-loaded 0→1——元素「已載入」語義；用家待未來）
                j_messages.attr("data-loaded", "1");


                // 訊息窗口載入完成 → 派發 load_message_list hook（外部用家各自嘅反應）
                chats.execute_hook_after("load_message_list", response_data);

                load_ok = true;
            },
            error: function (response_data) {
                // 失敗 → 不更新（維持空白，不揭示）；載入相位收結（返 ready）
                j_messages.attr("data-load_status", "ready");

                if (typeof callbacks.error === "function") {
                    callbacks.error(response_data);
                }
            }
        });

        return load_ok;
    }

    // 空狀態刷新（訊息區——自動偵測窗口訊息數：有＝收埋（有嘢睇唔使提示）／零＝顯示（窗口唔會空白））
    refresh_empty_state_msg_select_project() {
        const j_messages = jQuery(".sc_thread__viewport");
        const j_empty_state = j_messages.children(".sc_thread__empty_state");

        // 判定：窗口有訊息（.message_wrapper 存喺）→ 空狀態收埋
        if (j_messages.children(".message_wrapper").length > 0) {
            j_empty_state.prop("hidden", true);
        } else {
            // 否則（零條訊息）→ 空狀態顯示
            j_empty_state.prop("hidden", false);
        }
    }
}

export const message_list_fetch = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_list_fetch());
