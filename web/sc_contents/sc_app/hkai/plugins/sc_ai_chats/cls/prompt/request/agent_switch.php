<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\request;

// == sc_ai_chats prompt agent_switch：prompt 域動作（trait——掛入 prompt_Class；檔＝動作：切換現行 agent） ==
// == 上游：request/prompt/agent_switch.php（route action）／toolbar 的 data-btn='agent' ==
// == 下游：hkai()->agent()->write_agent()（寫 runtime.json 動態層；manifest 出廠值不動） ==

trait agent_switch
{

// POST agent_switch：切換現行 agent（驗證後寫 runtime.json 動態層）
public function agent_switch()
{
    $request_data = sc_request()->json();

    $agent = trim((string)($request_data["agent"] ?? ""));

    // 驗證（合法值集＝manifest agents 對映表——get_agents() 唯一來源）
    $agents = hkai()->agent()->get_agents();

    // 判定：唔係合法 agent（空／打錯）→ 不寫檔（非欄位錯誤結構＋400）
    if (!in_array($agent, $agents)) {
        return sc_response()->json(["ok" => false, "error" => "invalid agent"], 400);
    }

    // 寫入 runtime.json 動態層（切換＝「我想用邊個」——manifest 出廠值不動）
    hkai()->agent()->write_agent($agent);

    return sc_response()->json(["ok" => true]);
}
}
