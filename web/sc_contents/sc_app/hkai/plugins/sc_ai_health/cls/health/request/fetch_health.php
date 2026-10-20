<?php

namespace sc\app\hkai\plugins\sc_ai_health\health\request;

// == sc_ai_health health fetch_health：health 域動作（trait——掛入 health_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat main_Class 分遷入（one file one action） ==

trait fetch_health
{
    // health 狀態（組裝 get_default_health_data 純資料＋各報告——refresh 決策待做）
    public function fetch_health()
    {
        // 請求資料解析（POST JSON——project_slug／chat_sid／count／time）
        $request_data = sc_request()->json();

        // 判定：冇 JSON body（外部 GET 探測等）→ 退回 query（相容舊呼叫）
        if (count($request_data) === 0) {
            $request_data = sc_request()->query();
        }

        // 對話語境（message_poll 派發依據——唔喺對話內＝冇 chat_sid）
        $request_chat_sid = isset($request_data["chat_sid"]) ? (string)$request_data["chat_sid"] : "";

        $health_data = $this->get_default_health_data();

        // 外加報告（登記名＝reports 桶內 key——每次檢查即時解析；先解析再落閘讀取）
        foreach ($this->external_health_reports as $health_report_name => $health_report_callback) {
            $health_data["reports"][$health_report_name] = $this->resolve_health_report($health_report_callback, $request_data);
        }

        // 外加動作（每次檢查即時執行——回傳值丟棄、唔入回應；背景收尾等掛此）
        foreach ($this->external_health_actions as $health_action_callback) {
            $this->resolve_health_action($health_action_callback);
        }

        // message_poll 派發（須帶 chat_sid——冇對話語境唔派）
        if ($request_chat_sid === "") {
            unset($health_data["reports"]["message_poll"]);
        }

        // 有待辦工作 → 啟動 daemon（已暫停：喚醒改由 python bridge 負責——待測試完成後刪除）
        // if (count($health_data["message_poll"]) > 0)
        // {
        //     hkai__core()->wake_daemon();
        // }

        // 無待辦工作（message_poll 空）且 daemon 進程存在 → 停止 daemon（暫停——本機進程查詢已停用；daemon 靠 idle_timeout 自殺）
        // if (count($health_data["message_poll"]) < 1 && !empty($health_data["processes"]))
        // {
        //     hkai__core()->stop_daemons();
        // }

        return $health_data;
    }
}
