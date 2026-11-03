<?php

namespace sc\app\hkai\cls\agent;

// == agent_Class：agent 選擇中樞（singleton——經 bridging hkai()->agent() 取得） ==
// == 職責：manifest agents 對映表（agent 名 ↔ daemon 名／label）＋現行 agent 讀寫（runtime.json）
// == 上游：prompt toolbar／chat options／agent_switch action／queue 蓋章／python_bridge 程序鏈
// == 下游：hkai()->get_hkai_configs()（manifest）、hkai()->runtime()（runtime.json 讀寫）

class agent_Class
{
    private static $instance = null;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }

    public function __construct()
    {}

    // agent 清單（manifest agents 次序——次序＝UI 次序；只列有對應 daemon 設定組嘅 agent）
    public function get_agents()
    {
        $agents_config = $this->get_agents_config();
        $hkai_configs  = hkai()->get_hkai_configs();

        $agents = [];

        // 逐項檢查（daemon 設定組唔存在 → 唔算——防清單寫錯）
        foreach ($agents_config as $agent_name => $agent_item) {
            $agent_daemon = $agent_item["daemon"] ?? "";

            // 判定：冇對應 daemon 設定組 → 跳過
            if (!isset($hkai_configs[$agent_daemon])) {
                continue;
            }

            $agents[] = $agent_name;
        }

        return $agents;
    }

    // agent 對映表（manifest agents——agent 名 → daemon 名／顯示名）
    public function get_agents_config()
    {
        $hkai_configs = hkai()->get_hkai_configs();
        $agents       = $hkai_configs["agents"] ?? [];

        // 判定：非陣列（舊格式／缺）→ 空表
        if (!is_array($agents)) {
            return [];
        }

        return $agents;
    }

    // 現行 agent（runtime 已存值＞舊 daemon_engine 值換算＞manifest 值；皆唔合法 → 清單第一條）
    public function get_agent()
    {
        $agents                = $this->get_agents();
        $saved_runtime_configs = $this->read_runtime_configs();

        $runtime_agent = $saved_runtime_configs["agent"] ?? "";

        // 判定：動態 agent 合法 → 回傳
        if (in_array($runtime_agent, $agents)) {
            return $runtime_agent;
        }

        // 舊資料兼容（runtime 只有 daemon_engine＝daemon 名 → 換算成 agent 名）
        $legacy_agent = $this->get_agent_name_by_daemon($saved_runtime_configs["daemon_engine"] ?? "");

        // 判定：換算結果合法 → 回傳
        if (in_array($legacy_agent, $agents)) {
            return $legacy_agent;
        }

        $hkai_configs = hkai()->get_hkai_configs();
        $static_agent = $hkai_configs["agent"] ?? "";

        // 判定：靜態值合法 → 回傳
        if (in_array($static_agent, $agents)) {
            return $static_agent;
        }

        // 皆唔合法（缺／打錯）→ 清單第一條
        return $agents[0] ?? "";
    }

    // agent → daemon 名（查 manifest 對映表；查唔到 → 空字串）
    public function get_agent_daemon($agent)
    {
        $agents_config = $this->get_agents_config();
        $agent_item    = $agents_config[$agent] ?? [];

        return $agent_item["daemon"] ?? "";
    }

    // 現行 agent 對應嘅 daemon 名（舊名保留——等於 get_agent_daemon(get_agent())）
    public function get_daemon_engine()
    {
        return $this->get_agent_daemon($this->get_agent());
    }

    // 現行 agent 寫入（存 runtime.json 動態層；manifest 留出廠值；同時寫舊 key 過渡）
    public function write_agent($agent)
    {
        $runtime_configs = hkai()->runtime()->get_runtime_configs();

        $runtime_configs["agent"]         = $agent;
        $runtime_configs["daemon_engine"] = $this->get_agent_daemon($agent);

        $this->save_runtime_configs($runtime_configs);
    }

    // daemon 名 → agent 名（舊資料換算；查唔到 → 空字串）
    private function get_agent_name_by_daemon($daemon_name)
    {
        $agents_config = $this->get_agents_config();

        // 逐項比對 daemon 名
        foreach ($agents_config as $agent_name => $agent_item) {
            // 判定：對映表 daemon 名相同 → 回傳 agent 名
            if (($agent_item["daemon"] ?? "") === $daemon_name) {
                return $agent_name;
            }
        }

        return "";
    }

    // 已存 runtime.json 讀取（經 runtime——runtime I/O 唯一出口）
    private function read_runtime_configs()
    {
        return hkai()->runtime()->read_runtime_configs();
    }

    // runtime.json 落地（經 runtime——runtime I/O 唯一出口）
    private function save_runtime_configs($runtime_configs)
    {
        hkai()->runtime()->save_runtime_configs($runtime_configs);
    }
}
