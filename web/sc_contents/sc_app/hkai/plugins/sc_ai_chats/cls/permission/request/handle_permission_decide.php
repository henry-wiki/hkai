<?php

namespace sc\app\hkai\plugins\sc_ai_chats\permission\request;

// == sc_ai_chats permission request handle_permission_decide：permission 域動作（trait——掛入 permission_Class；檔＝動作） ==
// == 收前端決定（decision 單字 allow／deny ＋ apply_all 數字旗標）→ 併返四態原子寫 msg_permission_decision.json（Python ACP 側讀——檔案契約）＋allow_all／deny_all 寫 msg_conf 旗標 ==

trait handle_permission_decide
{

    // POST permission_decide：使用者對 goose 工具權限請求嘅決定
    public function handle_permission_decide()
    {
        // 參數（JSON body——message_id／request_id／decision／apply_all）
        $request_data = sc_request()->json();
        $message_id   = (int)($request_data["message_id"] ?? 0);
        $request_id   = (string)($request_data["request_id"] ?? "");
        $decision     = (string)($request_data["decision"] ?? "");
        $apply_all    = (int)($request_data["apply_all"] ?? 0);

        // 判定：message_id 不合法 → 回錯誤
        if ($message_id < 1) {
            return sc_response()->json(["ok" => false, "error" => "invalid message"], 400);
        }

        // 判定：request_id 缺 → 回錯誤
        if ($request_id === "") {
            return sc_response()->json(["ok" => false, "error" => "invalid request_id"], 400);
        }

        // 判定：決定詞彙非法 → 回錯誤（前端契約兩態——allow／deny；「全部」由 apply_all 帶）
        if (!in_array($decision, ["allow", "deny"], true)) {
            return sc_response()->json(["ok" => false, "error" => "invalid decision"], 400);
        }

        // 判定：apply_all 旗標非法 → 回錯誤（數字兩態——0（單次）／1（套用到全部））
        if (!in_array($apply_all, [0, 1], true)) {
            return sc_response()->json(["ok" => false, "error" => "invalid apply_all"], 400);
        }

        // 併返內部四態（前端決定＋apply_all → allow_all／deny_all 契約詞彙）
        $decision_effective = $decision;

        // 判定：勾咗套用全部 → 決定升級兩態
        if ($apply_all === 1) {
            // 判定：允許 → 允許全部；否則（拒絕）→ 拒絕全部
            if ($decision === "allow") {
                $decision_effective = "allow_all";
            } else {
                $decision_effective = "deny_all";
            }
        }

        // 以 message_id 反查 chat_id（不信任前端）
        $message_row = sc_ai_chats()->message()->repo_select()->get_message_row($message_id);

        // 判定：訊息不存在 → 回錯誤
        if ($message_row === null) {
            return sc_response()->json(["ok" => false, "error" => "message not found"], 404);
        }

        $chat_id = (int)$message_row["chat_id"];

        // pending 權限請求（Python ACP 側寫——無檔＝請求已結／未出過）
        $permission_request_file = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, "permission");

        // 判定：無 pending 請求 → 回錯誤（卡已過時）
        if (!is_file($permission_request_file)) {
            return sc_response()->json(["ok" => false, "error" => "no pending permission request"], 404);
        }

        $permission_request = json_decode(file_get_contents($permission_request_file), true);

        // 判定：請求檔唔完整 → 回錯誤
        if (!is_array($permission_request)) {
            return sc_response()->json(["ok" => false, "error" => "permission request unreadable"], 409);
        }

        // 判定：request_id 唔對（已被新請求取代）→ 回錯誤
        if ((string)($permission_request["request_id"] ?? "") !== $request_id) {
            return sc_response()->json(["ok" => false, "error" => "permission request superseded"], 409);
        }

        // 決定檔（原子寫——Python 側讀）
        $permission_decision_file = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, "permission_decision");

        // 判定：已有決定（重覆點擊）→ 當成功（幂等——決定以先到者為準）
        if (is_file($permission_decision_file)) {
            return sc_response()->json(["ok" => true, "already_decided" => true]);
        }

        // 允許全部（本輪）→ 先寫 msg_conf 旗標（Python 側之後嘅權限請求直接放行、唔再出卡——goose_entry _is_permission_allow_all）
        if ($decision_effective === "allow_all") {
            $conf_file = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, "conf");
            $conf_data = sc_ai_chats()->poll()->file()->get_msg_conf_data($chat_id, $message_id);

            // 判定：conf 讀到 → 加旗標重寫（缺失＝異常——略過，唔阻放行）
            if (is_array($conf_data)) {
                $conf_data["permission_allow_all"] = true;
                sc_write_json($conf_file, $conf_data);
            }
        }

        // 拒絕全部（本輪）→ 先寫 msg_conf 旗標（Python 側之後嘅權限請求直接拒絕、唔再出卡——*_entry _is_permission_deny_all）
        if ($decision_effective === "deny_all") {
            $conf_file = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, "conf");
            $conf_data = sc_ai_chats()->poll()->file()->get_msg_conf_data($chat_id, $message_id);

            // 判定：conf 讀到 → 加旗標重寫（缺失＝異常——略過，唔阻拒絕）
            if (is_array($conf_data)) {
                $conf_data["permission_deny_all"] = true;
                sc_write_json($conf_file, $conf_data);
            }
        }

        $permission_decision = [];
        $permission_decision["request_id"] = $request_id;
        $permission_decision["decision"]   = $decision_effective;
        $permission_decision["source"]     = "user";
        $permission_decision["decided_at"] = time();

        // 原子寫（tmp＋rename——讀者永遠見到完整 JSON）
        $temporary_file = $permission_decision_file . ".tmp";
        file_put_contents($temporary_file, json_encode($permission_decision));
        rename($temporary_file, $permission_decision_file);

        $response_json = [];
        $response_json["ok"]       = true;
        $response_json["decision"] = $decision_effective;

        return sc_response()->json($response_json);
    }

}
