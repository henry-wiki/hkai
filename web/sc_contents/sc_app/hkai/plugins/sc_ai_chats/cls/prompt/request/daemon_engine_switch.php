<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\request;

// == sc_ai_chats prompt daemon_engine_switch：prompt 域動作（trait——掛入 prompt_Class；檔＝動作：切換現行 daemon 引擎（agent 選擇）） ==
// == 2026-10-09 建（寫 runtime.json 動態層——下一條訊息蓋章生效；manifest 留出廠值） ==

trait daemon_engine_switch
{

// POST daemon_engine_switch：切換現行 daemon 引擎（agent 選擇——驗證後寫 runtime.json 動態層）
public function daemon_engine_switch()
{
    $request_data = sc_request()->json();

    $daemon_engine = trim((string)($request_data["daemon_engine"] ?? ""));

    // 驗證（合法值集＝manifest 引擎設定組——get_daemon_engines() 唯一來源）
    $daemon_engines = hkai__core()->get_daemon_engines();

    // 判定：唔係合法引擎（空／打錯）→ 不寫檔（非欄位錯誤結構＋400）
    if (!in_array($daemon_engine, $daemon_engines)) {
        return sc_response()->json(["ok" => false, "error" => "invalid daemon engine"], 400);
    }

    // 寫入 runtime.json 動態層（切換＝「我想用邊個」——manifest 出廠值不動）
    hkai__core()->write_daemon_engine($daemon_engine);

    return sc_response()->json(["ok" => true]);
}
}
