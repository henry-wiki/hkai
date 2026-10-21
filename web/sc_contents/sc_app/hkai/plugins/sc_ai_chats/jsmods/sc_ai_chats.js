// jsmods/sc_ai_chats.js —— chat plugin 入口（四層骨架：bind／action／handler／callback——除 registry 外全部動態載入）
// 職責：plugin 啟動編排（bind 家族全部 init；其餘層用先載）
// 對外入口（跨 plugin 經 registry().sc_ai_chats()）：init；register_hook／execute_hook 系（勾點機制——唯一住 root）
// 結構：class 單例 + export const factory（registry().sc_ai_chats() 取單例）

import { _domain_hooks } from "./_common/_domain_hooks.js";

// plugin 例項化（module closure 不散落 let——狀態皆收進 class）
class _sc_ai_chats {
    constructor() {
        this.ctx = null;
        this.inited = false;
        this.health_hook_registered = false;
        this.auto_start_checked = false;
        this.hooks = {};
    }
    // 啟動 plugin（bootstrap 取例項後呼叫——子模組初始化；重複呼叫只行一次）
    async init() {
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 域召集（_common 桶——事件綁定）
        const _common_instance = await this._common();
        await _common_instance.init();

        // 域召集（chat 域 init——事件綁定）
        const chat_instance = await this.chat();
        await chat_instance.init();

        // 域召集（訊息／prompt 模組 init——原本 bootstrap 直接叫，現歸 plugin 編排）
        const message_instance = await this.message();
        await message_instance.init();

        const prompt_instance = await this.prompt();
        await prompt_instance.init();

        // 域召集（queue 域 init——事件綁定）
        const queue_instance = await this.queue();
        await queue_instance.init();

        // 域召集（chat_option 域 init——事件綁定＋model_info 觀察器）
        const chat_option_instance = await this.chat_option();
        await chat_option_instance.init();

        // 2. 載入對話（清單重繪 hook 驅動——obs:page_loaded 後專案清單載入即派發，自動重載；未選專案＝後端決定預設）
        // registry 取值（靜態——直回單例）
        const projects = registry().sc_ai_projects();

        // 側欄專案清單容器（aside 常駐——唔會被重繪替換；loading 遮罩目標——before／after 兩相共用）
        const j_sidebar_project_list    = jQuery(".sc_sidebar--projects");

        // 訊息側 hook 註冊（訊息載入 hook＋觀察器重掛＋跳底＋markdown 還原——暫時分組）
        await this.message_hook_register();

        // 對話側 hook 註冊（清單重繪 hook＋觀察器派信號 → 本機側反應——暫時分組）
        await this.chat_hook_register();

        // prompt 側 hook 註冊（發送完成 hook → 重啟 health 輪詢——暫時分組）
        await this.prompt_hook_register();

        // 觀察器綁定（obs 家族——頁面旗標觀察器起一次＋obs 信號嘅反應註冊）
        await this.obs_hook_register();

        // 頁面載入一次性：bridge 停咗 → 自動啟動（唔等結果——唔阻塞頁面）
        this.auto_start_server_once();


        return this;
    }

    // 頁面載入一次性：檢查 bridge；停咗即自動啟動（後端自動分配埠；每頁載入最多一次）
    async auto_start_server_once() {
        // 判定：本次載入已檢查過 → 唔再做
        if (this.auto_start_checked) {
            return;
        }

        this.auto_start_checked = true;

        const shared = registry().shared();

        try {
            const bridge_check = await shared.sc_request().post_json("/request/hkai/fetch_bridge_check/", {});

            // 判定：讀唔到狀態 → 唔做
            if (!bridge_check) {
                return;
            }

            // 判定：server 已運行／啟動中 → 唔做
            if (bridge_check.server_status !== "stopped") {
                return;
            }

            // 自動啟動（host／port 交後端決定——port 自動分配）
            const start_result = await shared.sc_request().post_json("/request/hkai/settings_start_server/", {});

            // 判定：啟動成功 → 用實際埠更新 stream base_url（頁面 meta 係載入時快照）
            if (start_result && start_result.ok === true && start_result.port > 0) {
                this.update_stream_base_url(start_result.host, start_result.port);
            }
        } catch (request_error) {
            console.error("auto start server failed: " + request_error.message);
        }
    }

    // 更新頁面 stream 設定（server 實際 host／port——message_stream 下次開連線即用）
    update_stream_base_url(server_host, server_port) {
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

    // 觀察器綁定（obs 家族——頁面旗標／URL 變化觀察器起一次＋obs 信號嘅反應註冊；對話項觀察器係動態，掛點喺 load_chat_list——見 chat_hook_register）
    async obs_hook_register() {
        const chat_instance = await this.chat();
        const chat_activity_instance   = await chat_instance.act().chat_activity();
        const chat_item_fetch_instance = await chat_instance.act().chat_item_fetch();
        const chat_list_fetch_instance = await chat_instance.act().chat_list_fetch();

        const message_instance = await this.message();
        const message_list_fetch_instance = await message_instance.act().message_list_fetch();
        const prompt_instance = await this.prompt();
        const prompt_fetch_instance = await prompt_instance.act().prompt_fetch();

        // 頁面旗標觀察器（一次性——盯 body[data-loaded]；成個生命週期起一次，之後唔再掛）
        (await chat_instance.obs().page_loaded_observer()).init();


        // 頁面載入反應（body[data-loaded] 由 0 改 1——bootstrap 編排完成；首載三連：清單 → 訊息 → prompt）
        this.register_hook("obs:page_loaded", {
            after: async function () {
                await chat_list_fetch_instance.chat_list_fetch();
                await message_list_fetch_instance.message_list_fetch();
                await prompt_fetch_instance.prompt_fetch();

                // URL 變化註冊（反應＋觀察器——頁面載入後才掛；只報真變化）
                await registry().sc_ai_chats().obs_url_change();
            }
        });


        // 訊息渲染狀態收結（狀態軸唯一推進點——初始載入／補載／手動重渲染同一條線）
        // rendered_message_markdown：單條 markdown 標完 → 判定（冇 mermaid＝ready；有＝markdown 等 mermaid）＋有 mermaid 交排隊（收結交 rendered_message_mermaid）
        this.register_hook("rendered_message_markdown", {
            after: async function (j_message_wrapper) {
                const message_instance = await registry().sc_ai_chats().message();
                const message_render_instance = await message_instance.act().message_render();

                await message_render_instance.refresh_render_status(j_message_wrapper);

                // 判定：有 mermaid → 交排隊渲染（ready 由 rendered_message_mermaid 掛鈎收結）
                const contains = message_render_instance.message_contains(j_message_wrapper);
                if (contains.mermaid) {
                    message_render_instance.render_single_mermaid(j_message_wrapper);
                }
            }
        });

        // rendered_message_mermaid：單條 mermaid 完 → 收結 ready（管線全完）
        this.register_hook("rendered_message_mermaid", {
            after: async function (j_message_wrapper) {
                const message_instance = await registry().sc_ai_chats().message();
                const message_render_instance = await message_instance.act().message_render();

                message_render_instance.finish_render_status(j_message_wrapper);
            }
        });




        // 對話項變動反應（chat 域觀察器派 obs:chat_item_updated → 逐項抓新內容換入＋即時排位；觀察器住 chat/obs/chat_item_observer.js）
        this.register_hook("obs:chat_item_updated", {
            after: async function (affected_chat_items) {
                // 逐粒受變更項 → 取身份（chat_sid）→ 抓新內容換入（只換 children——節點身份保留）
                for (let item_index = 0; item_index < affected_chat_items.length; item_index++) {
                    const affected_chat_sid = jQuery(affected_chat_items[item_index]).attr("data-chat_sid");

                    await chat_item_fetch_instance.chat_item_fetch(affected_chat_sid);
                }

                // 次序交唯一排位者（內容換完後——最後一下）
                chat_activity_instance.chat_list_rank_refresh();
            }
        });



    }

    // URL 變化註冊（反應＋觀察器——一次性；先註冊反應再起觀察器）
    async obs_url_change() {
        const chat_instance = await this.chat();



        // URL 變化反應（URL 變化觀察器派 obs:url_changed——帶前後兩條 URL；依序：專案有冇換（換咗＝重取清單＋訊息窗＋prompt）→ 清單語境有冇換（入／離歸檔＝重取清單）→ 對話有冇換（＝載入訊息窗＋prompt）；歸檔連結方向＝交 status bar 區重渲）
        this.register_hook("obs:url_changed", {
            after: async function (goto_url, prev_url) {


                const chat_instance = await registry().sc_ai_chats().chat();
                const detect_project_slug_change_instance    = await chat_instance.act().detect_project_slug_change();
                const detect_chat_list_context_change_instance = await chat_instance.act().detect_chat_list_context_change();
                const detect_chat_sid_change_instance        = await chat_instance.act().detect_chat_sid_change();


                detect_project_slug_change_instance.detect_project_slug_change(goto_url, prev_url);
                detect_chat_list_context_change_instance.detect_chat_list_context_change(goto_url, prev_url);
                detect_chat_sid_change_instance.detect_chat_sid_change(goto_url, prev_url);
            }
        });

        // URL 變化觀察器（一次性——訂閱 sc_http；只報真變化）
        (await chat_instance.obs().url_changed_observer()).init();
    }

    // 對話側 hook 註冊（清單重繪 hook＋觀察器派信號 → 本機側反應——暫時分組，令 root 好睇）
    async chat_hook_register() {
        // 接駁（chat 域 act 橋——反應同步用）
        const chat_instance = await this.chat();

        // 側欄對話清單容器（aside 常駐——唔會被重繪替換；loading 遮罩目標——before／after 兩相共用）
        const j_sidebar_chat_list = jQuery(".sc_sidebar--chats");

        // 清單重繪 hook 反應（chat 域派發 load_chat_list → 收遮罩＋激活健康更新＋重掛對話項觀察器；失敗＝只收遮罩）
        this.register_hook("load_chat_list", {
            before: function () {
                registry().sc_loading().start_panel_loading(j_sidebar_chat_list);
            },
            after: async function () {
                registry().sc_loading().stop_loading(j_sidebar_chat_list);

                // 清單重繪後同步狀態列（sb_chat 讀 .sc_chat_list .sc_chat_card--active——來源域推：對話清單變咗＝chat 側叫同步）
                await registry().sc_ai_status_bar().sync_chat();

                // !! 必須留喺呢度（清單載入後才激活）——唔可以搬上 init !!
                // 原因：health 每拍會調整／改變對話列（更新／建立／刪除卡）——早註冊＝對未載入清單盲建卡（chat_list_refresh 建立路線）
                const chats_instance = registry().sc_ai_chats();
                await chats_instance.health_hook_register();

                // 對話項觀察器重掛（動態——清單加減／重繪後新卡要重新盯；init 內置 disconnect 防重）
                const chat_item_observer_instance = await chat_instance.obs().chat_item_observer();
                chat_item_observer_instance.init();
            },
            failed: function () {
                // 失敗（清單維持現狀）→ 只收遮罩（激活健康更新／重掛觀察器＝成功先做）
                registry().sc_loading().stop_loading(j_sidebar_chat_list);
            }
        });

        // 切換對話 hook 反應（切換完成——對話項觀察器重掛；同 load_chat_list 同形）
        this.register_hook("chat_item_switch", {
            after: async function () {
                // 對話項觀察器重掛（清單節點郁過——重新盯；init 內置 disconnect 防重）
                const chat_item_observer_instance = await chat_instance.obs().chat_item_observer();
                chat_item_observer_instance.init();
            }
        });
    }

    // 訊息側 hook 註冊（訊息載入 hook＋觀察器重掛＋跳底＋歷史 markdown 還原——暫時分組，令 root 好睇）
    async message_hook_register() {
        // 主區容器（.sc_workspace——loading 遮罩目標；before／after 兩相共用）
        const j_sc_workspace = jQuery(".sc_workspace");

        // 訊息載入 hook 反應（message 域派發 load_message_list → 揭示＋重掛觀察器＋逐條套用＋跳底＋歷史 markdown 還原）
        this.register_hook("load_message_list", {
            before: function () {
                registry().sc_loading().start_panel_loading(j_sc_workspace);
            },
            after: async function (hook_data) {
                registry().sc_loading().stop_loading(j_sc_workspace);

                // 揭示（骨架先行——放開 opacity 畀骨架同逐條套用都睇得到）
                jQuery(".sc_thread__viewport").css("opacity", "1");

                // 橋接駁（同域 evt／act——預先攞定 instance；載入後反應要用）
                const message_instance = await registry().sc_ai_chats().message();
                const message_intersection_observer_instance     = await message_instance.obs().message_intersection_observer();
                const message_mutation_observer_instance         = await message_instance.evt().message_mutation_observer();
                const message_scroll_to_bottom_instance          = await message_instance.act().message_scroll_to_bottom();
                const message_frame_apply_instance               = await message_instance.act().message_frame_apply();
                const message_rail_active_observer_instance      = await message_instance.obs().message_rail_active_observer();
                const message_prompt_observer_instance = await message_instance.obs().message_prompt_observer();
                const message_position_keeper_instance           = await message_instance.obs().message_position_keeper();


                // 觀察器重掛（保底——錨點＋.sc_thread__viewport 變動；init 內置 disconnect 防重）
                message_intersection_observer_instance.init();
                message_mutation_observer_instance.init();
                message_rail_active_observer_instance.init();
                message_prompt_observer_instance.init();
                message_position_keeper_instance.init();

                // 判定：初次載入（load_status 仍喺 loading——fetch 起步標記，跨到呢個 hook 收結）→ 行初次管線（跳底＋套用＋全窗還原）；補載派發（ready——向上舊頁）＝唔郁位置、唔重渲
                if (jQuery(".sc_thread__viewport").attr("data-load_status") === "loading") {
                    // 跳到底部（起初位＝最底——最新訊息喺底；等套用由底向上行）
                    message_scroll_to_bottom_instance.message_scroll_to_bottom(false);

                    // 逐條套用（由最底（最新）向上——成品按 message_id 換入骨架框；空窗派發冇骨架＝跳過）
                    if (hook_data.html) {
                        await message_frame_apply_instance.message_frame_apply(hook_data.html.messages);
                    }

                    // 歷史訊息 markdown 還原（全窗——交 render_all_markdown 接駁；逐條渲染＋完結派勾喺掃描內；渲染嘅高度變化由觀察器釘底跟上）
                    const message_render_instance = await message_instance.act().message_render();
                    await message_render_instance.render_all_markdown();

                    // 跳到底部（渲染完畢再貼返底——高度已定）
                    message_scroll_to_bottom_instance.message_scroll_to_bottom(false);
                }


                // 起 poll 接手（回應直接拋入 message_poll 域——偵測＋判定喺嗰邊）
                const message_poll_instance = await message_instance.act().message_poll();
                message_poll_instance.message_poll_detect(hook_data);

                registry().sc_loading().stop_loading(j_sc_workspace);

                // 載入相位收結（load_status 返 ready——向上補載嘅閘先再受叫）
                jQuery(".sc_thread__viewport").attr("data-load_status", "ready");
            }
        });
    }

    // prompt 側 hook 註冊（prompt 載入 → composer 遮罩；發送完成 → 重啟 health 輪詢——暫時分組，令 root 好睇）
    async prompt_hook_register() {
        // 接駁（health plugin 根——發送後重啟輪詢用）
        const health_instance = registry().sc_ai_health();

        // composer 容器（loading 遮罩目標——load_prompt before／after 兩相共用）
        const j_sc_composer = jQuery(".sc_composer");

        // prompt 載入 hook 反應（prompt 域派發 load_prompt → composer 區塊 loading 遮罩開／關＋輸入框高度觀察接駁）
        this.register_hook("load_prompt", {
            before: function () {
                registry().sc_loading().start_panel_loading(j_sc_composer);
            },
            after: async function () {
                registry().sc_loading().stop_loading(j_sc_composer);

                // 輸入框高度觀察接駁（composer 入畫面——重掛安全：先 off 再 on）
                // 橋接駁（同域 obs——新方式）
                const prompt_instance = await registry().sc_ai_chats().prompt();
                const prompt_input_height_observer_instance = await prompt_instance.obs().prompt_input_height_observer();

                prompt_input_height_observer_instance.init();
            }
        });

        // 發送完成 hook 反應（prompt 流派發 prompt_sent → 重啟 health 輪詢——新任務即刻偵測）
        this.register_hook("prompt_sent", {
            before: function () {

            },
            after: async function () {
                // 立即重跳一輪（唔等下一班——新 job 早一輪見到）
                await health_instance.restart();
            }
        });
    }


    async health_hook_register() {
        // 判定：已激活 → 唔重複登記（load_chat_list 每次清單載入都派；只認第一次）
        if (this.health_hook_registered) {
            return;
        }

        this.health_hook_registered = true;

        // 接駁（health plugin 根——登記健康更新反應）
        const health_instance = registry().sc_ai_health();

        // 接駁（chat 域 act 橋——反應同步用）
        const chat_instance = await this.chat();
        const chat_list_refresh_instance = await chat_instance.act().chat_list_refresh();

        // 健康更新 hook 反應（通訊由 chats 發起——登記喺 health 側；每輪執行）
        health_instance.register_hook("health_updated", {
            before: function () {

            },
            after: async function (health_result) {
                // 刷新對話列（health_result 直入——過濾／補缺／刷狀態全交 chat_list_refresh）
                await chat_list_refresh_instance.chat_list_refresh(health_result);
            }
        });
    }



}

// 連接（出入口掛上 prototype——唯一入口；動態載入＝用先載）
_sc_ai_chats.prototype._common     = async function () { return (await import("./_common/_common.js"))._common();   };
_sc_ai_chats.prototype.chat        = async function () { return (await import("./chat/chat.js")).chat();            };
_sc_ai_chats.prototype.message     = async function () { return (await import("./message/message.js")).message();   };
_sc_ai_chats.prototype.prompt      = async function () { return (await import("./prompt/prompt.js")).prompt();      };
_sc_ai_chats.prototype.queue       = async function () { return (await import("./queue/queue.js")).queue();         };
_sc_ai_chats.prototype.chat_option = async function () { return (await import("./chat_option/chat_option.js")).chat_option(); };

// 連接（hook 機制掛上 root——唯一位置；F12 追唔到為已知代價）
Object.assign(_sc_ai_chats.prototype, {
    register_hook       : _domain_hooks.prototype.register_hook,
    execute_hook_before : _domain_hooks.prototype.execute_hook_before,
    execute_hook_after  : _domain_hooks.prototype.execute_hook_after,
    execute_hook_failed : _domain_hooks.prototype.execute_hook_failed,
});

export const sc_ai_chats = (function (instance) {
    return function () {

        return instance;
    };
})(new _sc_ai_chats());
