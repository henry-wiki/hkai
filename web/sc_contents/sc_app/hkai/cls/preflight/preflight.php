<?php

namespace sc\app\hkai\cls\preflight;

// == preflight_Class：初始化自檢（singleton——經全域入口呼叫）
// == 職責：每次頁面初始化跑一輪檢查；發現「可重算」值過時即修正（冪等、單條例外不阻塞頁面）
// == 上游：sc_app/hkai/index.php（初始化階段）
// == 下游：action/ 逐條 check（一檔一條；check 自己負責 detect＋repair）

class preflight_Class
{
    // 檢查清單（一檔一條——加新檢查＝加檔＋加一行）
    private const CHECK_NAME_LIST = [
        "ensure_home_project_path",
        "sweep_empty_draft_dirs",
    ];

    private static $instance = null;

    public static function getInstance()
    {
        // 判定：未建立 → 建立單例
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct()
    {}

    // 跑一輪（回已修正條數；任何例外只記 log，唔影響頁面）
    public function run()
    {
        $fixed_count = 0;

        // 逐條 check：累計修正數
        foreach (self::CHECK_NAME_LIST as $check_name_item) {
            $fixed_count = $fixed_count + $this->run_check($check_name_item);
        }

        return $fixed_count;
    }

    // 單條 check（載入 → 執行；例外吞掉並記 log）
    private function run_check($check_name)
    {
        $check_file_path = __DIR__ . "/action/" . $check_name . ".php";

        // 判定：check 檔唔存在 → 跳過
        if (is_file($check_file_path) === false) {
            return 0;
        }

        $check_class = __NAMESPACE__ . "\\action\\" . $check_name . "_Class";

        require_once $check_file_path;

        // 判定：class 唔存在（檔名同 class 名唔對）→ 記 log 跳過
        if (class_exists($check_class) === false) {
            hkai()->runtime()->logger()->warn("preflight: class missing " . $check_class);

            return 0;
        }

        try {
            $check_instance = new $check_class();

            return (int)$check_instance->run();
        } catch (\Throwable $check_error) {
            $error_message = "preflight: " . $check_name . " failed — " . $check_error->getMessage();

            hkai()->runtime()->logger()->warn($error_message);

            return 0;
        }
    }
}
