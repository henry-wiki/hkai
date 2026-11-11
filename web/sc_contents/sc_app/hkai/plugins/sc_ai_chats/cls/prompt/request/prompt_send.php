<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\request;

// == sc_ai_chats prompt prompt_send：prompt 域動作（trait——掛入 prompt_Class；bridging sc_ai_chats()->prompt()） ==
// == 職責：prompt_send——送出訊息端點（工序：準備→建對話→定稿→上帶→推帶→UI→回應；每工序一函數）
// == 上游：request/prompt/prompt_send.php；下游：project／draft／chat／queue／message／poll 域

trait prompt_send
{
    // POST prompt_send：只編排工序（準備→建對話→上帶→推帶→抽取→回應）
    public function prompt_send()
    {
        $input_data = sc_request()->json();

        // 工序耗時標記（診斷用——只記毫秒；成功路徑最後一次 log）
        $timing_mark = [];
        $timing_mark["start"] = microtime(true);

        // 只認 project_slug＋chat_sid（其餘由草稿 meta 取；error 槽＝工序失敗回報）
        $context                 = [];
        $context["error"]        = null;
        $context["error_status"] = 200;
        $context["project_slug"] = isset($input_data["project_slug"]) ? $input_data["project_slug"] : "";
        $context["chat_sid"]     = isset($input_data["chat_sid"]) ? $input_data["chat_sid"] : "";

        // 判定：請求直接帶文字（前端唔再等 draft_save_meta）→ 即場寫入草稿 meta（零 HTTP 往返）
        if (isset($input_data["prompt_text"])) {
            $draft_project_slug = $context["project_slug"];

            // 判定：空 slug → 用預設（Home——與 draft_save_meta 同一 fallback）
            if ($draft_project_slug === "") {
                $draft_project_slug = (string)sc_get_config("hkai.default_project_slug");
            }

            $draft_patch = [];
            $draft_patch["prompt_text"] = (string)$input_data["prompt_text"];
            $draft_patch["agent"]       = (string)hkai()->agent()->get_agent();

            // 選型資訊（前端一併帶——避免「揀完模型即送出」時 meta 仍係舊值）
            $draft_optional_field_keys = ["provider_id", "model_id", "reasoning_effort", "chat_mode"];

            foreach ($draft_optional_field_keys as $draft_field_key) {
                // 判定：請求冇帶 → 保留 meta 原值
                if (isset($input_data[$draft_field_key]) === false) {
                    continue;
                }

                $draft_patch[$draft_field_key] = (string)$input_data[$draft_field_key];
            }

            $draft_chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($context["chat_sid"]);
            $draft_patch["is_new_chat"] = ($draft_chat_row === null) ? 1 : 0;

            sc_ai_chats()->draft()->set_draft_meta($draft_project_slug, $context["chat_sid"], $draft_patch);
        }

        // 工序 1–4：準備階段（專案→草稿→三關驗證→queue 上限）
        $context = sc_ai_chats()->prompt()->prepare_prompt_request($context);

        // 判定：準備階段失敗 → 按工序指定 status 回
        if ($context["error"] !== null) {
            return sc_response()->json($context["error"], $context["error_status"]);
        }

        $timing_mark["prepare"] = microtime(true);

        // 工序 5：建對話列（DB 未有此 sid 就建；回 {ok, chat_row, context}）
        $chat_result = sc_ai_chats()->chat()->resolve_chat_row($context);

        // 判定：建對話失敗 → 500（唔搬檔、唔上帶）
        if ($chat_result["ok"] === false) {
            hkai()->runtime()->logger()->error("prompt_send 工序5: 建對話失敗 chat_sid=" . $context["chat_sid"]);

            return sc_response()->json(["ok" => false, "error" => "failed to create chat"], 500);
        } else {
            $context = $chat_result["context"];
        }

        $timing_mark["chat"] = microtime(true);

        // 工序 6：定稿草稿（上帶前最後一次寫入：chat_id＋status=pending）
        $project_slug = $context["project_slug"];
        $chat_sid     = $context["chat_sid"];
        $prompt_id    = $context["prompt_id"];

        $finalized = sc_ai_chats()->draft()->finalize_draft($project_slug, $chat_sid, $chat_result["chat_row"]);

        // 判定：定稿失敗 → 500（草稿維持原狀，唔上帶）
        if ($finalized === false) {
            hkai()->runtime()->logger()->error("prompt_send 工序6: 定稿失敗 chat_sid=" . $chat_sid . " prompt_id=" . $prompt_id);

            return sc_response()->json(["ok" => false, "error" => "failed to finalize draft"], 500);
        }

        $timing_mark["finalize"] = microtime(true);

        // 工序 7：上帶（queue 域：純 rename；箱內零改寫）
        try {
            sc_ai_chats()->queue()->enqueue_prompt_from_draft($project_slug, $chat_sid);
        } catch (\Throwable $enqueue_error) {
            $context["error"] = ["ok" => false, "error" => "failed to move prompt to queue"];

            hkai()->runtime()->logger()->error("prompt_send 工序7: 上帶失敗 chat_sid=" . $chat_sid . " prompt_id=" . $prompt_id . " error=" . $enqueue_error->getMessage());

            return sc_response()->json($context["error"], 500);
        }

        $timing_mark["enqueue"] = microtime(true);

        // 工序 8：推帶（唯一入口——內部先驗證、後前移；處理中＝留隊，唔算失敗）
        try {
            $shift_result = sc_ai_chats()->queue()->dequeue_prompt_message_to_poll($project_slug, $chat_sid);
        } catch (\Throwable $dequeue_error) {
            hkai()->runtime()->logger()->error("prompt_send 工序8: 推帶失敗 chat_sid=" . $chat_sid . " error=" . $dequeue_error->getMessage());

            return sc_response()->json(["ok" => false, "error" => "failed to push prompt"], 500);
        }

        $context["shift_result"] = $shift_result;

        $timing_mark["dequeue"] = microtime(true);

        // 工序 9：UI（message view——新氣泡＋完整隊列）
        $context = sc_ai_chats()->message()->view()->render_prompt_send_result($context);

        // 判定：真係推咗帶 → 最後開關開回覆
        if ($shift_result["dispatched"] === true) {
            // 測試時 // 停用下面一句：箱照入 polling，唔叫 AI
            sc_ai_chats()->queue()->dispatch_polling_job();
        }

        $timing_mark["dispatch"] = microtime(true);

        // 工序 10：回應 JSON（先印工序耗時——診斷用）
        $timing_text_list = [];
        $timing_prev_at = $timing_mark["start"];

        foreach ($timing_mark as $timing_name => $timing_at) {
            // 判定：第一個標記本身 → 跳過（唔計差）
            if ($timing_name === "start") {
                continue;
            }

            $timing_text_list[] = $timing_name . "=" . round(($timing_at - $timing_prev_at) * 1000) . "ms";
            $timing_prev_at = $timing_at;
        }

        hkai()->runtime()->logger()->info("prompt_send timing: " . implode(" ", $timing_text_list));

        return sc_response()->json(sc_ai_chats()->prompt()->build_prompt_send_response($context));
    }

    // 工序 10：回應 JSON（新訊息氣泡＋隊列 HTML＋新對話旗標＋chat URL）
    public function build_prompt_send_response($context)
    {
        $response_json = [];
        $prompt_id = $context["prompt_id"];

        $response_json["ok"]                    = true;
        $response_json["project_slug"]          = $context["project_slug"];
        $response_json["chat_sid"]              = $context["chat_sid"];
        $response_json["chat_id"]               = $context["chat_id"];
        $response_json["message_id"]            = $context["new_message_id"];
        $response_json["prompt_id"]             = $prompt_id;
        $response_json["is_new_chat_row"]       = $context["is_new_chat_row"];
        $response_json["chat_url"]              = sc_ai_chats()->chat()->build_chat_url($context["project_slug"], "normal", $context["chat_sid"]);

        $response_json["html"]["new_message"]   = $context["new_message"];
        $response_json["html"]["queue_list"]    = $context["queue_list"];

        return $response_json;
    }
}
