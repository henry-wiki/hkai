<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\request;

// == sc_ai_chats poll\request handle_message_poll：poll request 子域動作（trait——掛入 request_Class；檔＝動作） ==
// == 2026-09-27 自 cls/poll/ 搬入（request 子域——sc_ai_chats()->poll()->request()->handle_message_poll()） ==

trait handle_message_poll
{

    //---------------------------------------------------------
    // 1. 掃本對話進行中嘅訊息（jobs）
    // 2. 逐條砌 task（內容＋狀態＋log）
    // 3. 順手將狀態寫入 DB
    // 4. 回 tasks＋隊列 UI
    //---------------------------------------------------------
    public function handle_message_poll()
    {
        // 等待列 chat_id（queue 檔——chips 資料源）：請求帶 chat_sid → 解析該對話
        $request_data = sc_request()->json();
        $chat_sid     = isset($request_data["chat_sid"]) ? $request_data["chat_sid"] : "";

        // 已獲取申報（前端——per 訊息 per 段已收長度；缺＝全量）
        $acquired_responses = [];

        // 逐訊息 key 正規化為 int（JSON 數字字串 key——配合 $_message_id 查表）
        foreach (($request_data["acquired_responses"] ?? []) as $acquired_message_key => $acquired_message_item) {
            $acquired_responses[(int)$acquired_message_key] = $acquired_message_item;
        }
        
        // 判定：無 chat_sid → 報錯（poll 層必須有——送出＝唯一激活者，嗰刻對話一定已建）
        if ($chat_sid === "") {
            return sc_response()->json(["ok" => false, "error" => "chat_sid is required"], 400);
        }
        
        // 解析 chat row（chat_id／project_id 直取——送出＝唯一激活者，poll 嗰刻對話一定已建）
        $chat_row   = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);
        $chat_id    = (int)$chat_row["chat_id"];
        $project_id = (int)$chat_row["project_id"];
        
        // 掃描該對話進行中任務（msg_conf——有幾多出幾多）
        $polling_jobs = sc_ai_chats()->poll()->file()->get_poll_jobs($chat_id);
        
        // 等待列 UI（與 submit／頁面初始共用 builder——傳 chat_id 即掃＋砌整排；前端只 bind）
        $queue_list = sc_ai_chats()->prompt()->render_queue_list($chat_id);
        
        // 工作一：產生 task（逐條全出——輪詢由前端驅動）
        $tasks = [];
        foreach ($polling_jobs as $_polling_job_item) {
            $_chat_id            = (int)$_polling_job_item["chat_id"];
            $_message_id         = (int)$_polling_job_item["message_id"];
            $_message_poll_status = $_polling_job_item["status"];

            // 段同步（檔案→卡——寫入鏈：串流期間只寫卡檔；終態 finalize 一次 flush 入 DB）
            sc_ai_chats()->message()->card()->upsert_message_card_response($chat_row, $_polling_job_item);

            // 段讀取（卡＝讀源，冇卡讀 DB；兩列入、內部自理）
            $_response_sections = [];

            // 訊息列（模型資訊喺度——骨架用）
            $_message_row = sc_ai_chats()->message()->repo_select()->get_message_row($_message_id);

            $_response_rows = sc_ai_chats()->message()->repo_select()->get_message_response_rows_by_message_id([$_message_id]);

            // 逐 DB 列砌 serve 段（message_row＋response_row——砌法喺 build_response_section）
            foreach ($_response_rows as $_response_row_item) {
                $_response_sections[] = sc_ai_chats()->message()->view()->build_response_section($_message_row, $_response_row_item);
            }

            // 本訊息已獲取申報（per 段——response_id → {content_length}；缺＝全量）
            $_acquired_map = $acquired_responses[$_message_id] ?? [];

            // 逐段裁增量（後台據申報裁走已獲取部分——前端只收新增）
            foreach ($_response_sections as $_response_section_index => $_response_section_item) {
                $_response_section_response_id = (int)$_response_section_item["response_id"];

                // 段全文字元長（mb——申報／裁剪同一把尺；前端唔自己計）
                $_section_content_length = mb_strlen($_response_section_item["content"]);

                $_acquired_content_length = (int)($_acquired_map[$_response_section_response_id]["content_length"] ?? 0);

                // 判定：負數申報 → 當 0（申報唔可為負）
                if ($_acquired_content_length < 0) {
                    $_acquired_content_length = 0;
                }

                // 增量（已獲取 < 全文先裁；≥＝冇新內容 → 空）
                $_delta_content = "";

                // 判定：有新增內容 → 裁出增量（mb 字元——同申報同一把尺）
                if ($_acquired_content_length < $_section_content_length) {
                    $_delta_content = mb_substr($_response_section_item["content"], $_acquired_content_length);
                }

                $_response_sections[$_response_section_index]["content"] = $_delta_content;

                // 段長隨輪帶返（前端據此更新申報——每輪都係最新）
                $_response_sections[$_response_section_index]["content_length"] = $_section_content_length;

                // 判定：已申報（盒已存在）→ 骨架唔帶（省傳）
                if ($_acquired_content_length > 0) {
                    $_response_sections[$_response_section_index]["skeleton_html"] = "";
                }
            }

            // 活動日誌解析（自讀 msg_log；非終態顯示：logs 逐行——前端 .activity_monitor）
            $_logs = sc_ai_chats()->poll()->log()->parse_activity_events($_chat_id, $_message_id);

            // task 統一結構（status 單一詞彙——前端直接認）
            // working＝非終態標記（後端算——前端零 status 清單，主按鈕兩態據此判）
            $_task = [];
            $_task["status"]    = $_message_poll_status;
            $_task["working"]   = sc_ai_chats()->fsm()->is_terminal_status($_message_poll_status) ? "0" : "1";
            // success＝完成（成功）標記——後台算，前臺唔判斷 status 清單
            $_task["success"]   = ($_message_poll_status === "completed") ? "1" : "0";
            // 用時秒數（自訊息建立起算——前端 counter 基準；每輪重算）
            $_task["elapsed_sec"] = max(0, time() - (int)strtotime((string)$_message_row["message_created_at"]));
            $_task["data"]      = [
                "message_id"    => $_message_id,
                "responses"     => $_response_sections,
            ];
            $_task["logs"]      = $_logs;

            // 權限提示（pending 先出——僅非終態；終態＝請求已死，卡唔應殘留）
            $_task["permission"] = null;

            // 判定：非終態 → 讀 pending 權限請求砌提示卡
            if (!sc_ai_chats()->fsm()->is_terminal_status($_message_poll_status)) {
                $_task["permission"] = sc_ai_chats()->permission()->build_permission_prompt($_chat_id, $_message_id);
            }

            $tasks[] = $_task;

        }

        // 工作二：改狀態（讀到咩 status 直接寫入 DB——同 task 產生分開）
        $has_terminal_job = false;
        $has_abnormal_job = false;
        foreach ($polling_jobs as $_polling_job_item) {
            $_chat_id            = (int)$_polling_job_item["chat_id"];
            $_message_id         = (int)$_polling_job_item["message_id"];
            $_message_poll_status = $_polling_job_item["status"];

            sc_ai_chats()->message()->card()->update_message_card_status($_message_id, $_message_poll_status);

            // 判定：已完結 → 記低（之後歸檔）
            if (sc_ai_chats()->fsm()->is_terminal_status($_message_poll_status)) {
                $has_terminal_job = true;
            }
            // 判定：未完結（未終態）→ 標記回應異常（交 finalize_abnormal_jobs 雙重檢查——真卡死先了結）
            else {
                $has_abnormal_job = true;
            }
        }

        // 工作三：歸檔（有終態 → 收尾——後台自己嘅工作）
        if ($has_terminal_job) {
            sc_ai_chats()->poll()->finalize_poll_jobs($chat_id);
        }

        // 工作四：異常收尾（有回應異常 → 交異常收尾器——msg_log 停滯＋主人唔在，雙重檢查先了結）
        if ($has_abnormal_job) {
            sc_ai_chats()->poll()->finalize_abnormal_jobs($chat_id);
        }
        
        // 本拍回應：chat_sid（本批屬邊個對話——前端據此丟棄切換後嘅舊批）＋tasks（含 status）＋等待列 UI
        $response_data = ["ok" => true, "chat_sid" => $chat_sid, "tasks" => $tasks, "html" => ["queue_list" => $queue_list]];

        return sc_response()->json($response_data);
    }
}
