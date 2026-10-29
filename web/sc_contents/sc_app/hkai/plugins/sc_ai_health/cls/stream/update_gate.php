<?php

namespace sc\app\hkai\plugins\sc_ai_health\stream;

// == stream update_gate：監視目標更新閘（trait——掛入 stream_Class） ==
// == 上游：寫入端（project／chat／draft 動作）呼叫 mark_target_updated()；讀取端 init_stream_snapshot() 呼叫 read_update_gate() ==
// == 下游：data/stream/update/{target}.lastupdate.log（內容＝epoch 毫秒；一目標一檔） ==
// == 目的：每 tick 只讀 3 個細檔，gate 冇變＝唔查 DB、唔掃目錄 ==

trait update_gate
{
    // 讀目標更新閘（毫秒；冇檔＝0）
    public function read_update_gate($target_name)
    {
        $gate_file = $this->get_update_gate_file($target_name);

        // 判定：未建立（從未有寫入）→ 0
        if (is_file($gate_file) === false) {
            return 0;
        }

        $gate_text = @file_get_contents($gate_file);

        // 判定：讀唔到 → 0
        if ($gate_text === false) {
            return 0;
        }

        return (int)trim($gate_text);
    }

    // 標記目標有更新（寫入端呼叫——每次真實寫入一次）
    public function mark_target_updated($target_name)
    {
        $gate_file = $this->get_update_gate_file($target_name);

        $gate_dir = dirname($gate_file);

        // 判定：目錄唔存在 → 先建立
        if (is_dir($gate_dir) === false) {
            @mkdir($gate_dir, 0777, true);
        }

        $gate_millisecond = (int)round(microtime(true) * 1000);

        @file_put_contents($gate_file, (string)$gate_millisecond);
    }

    // 閘檔路徑（一目標一檔）
    private function get_update_gate_file($target_name)
    {
        return sc_get_config("hkai.stream_dir") . "/update/" . $target_name . ".lastupdate.log";
    }
}
