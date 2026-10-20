<?php

namespace sc\setup\state;

// == HKAI setup state：state 域本體（state.cls.php——singleton；呼叫形 setup()->state()->read_setup_process()）
// == 內容：class（singleton）＋過程檔／完成標記讀寫——add／del／update／modify／read（setup_process.json／setup_done.json 單一出入閘）
// == 語法下限：PHP 5.4（同 load.php）

class state_Class
{
    private static $instance = null;

    /**
     * 單例（系統唯一入口——F12 鏈式跳定義靠本註解）
     *
     * @return \sc\setup\state\state_Class
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    // 讀過程檔（setup_process.json——合法 JSON＝接住用；已收尾〔過程檔改咗名〕＝讀完成檔；皆無／解析失敗＝起始形）
    public function read_setup_process()
    {
        $setup_process = sc_get_config("setup.setup_process");
        $setup_done    = sc_get_config("setup.setup_done");

        $process_data = [];
        $process_data["started_at"] = date("Y-m-d H:i:s");
        $process_data["sections"]   = [];

        $process_file = $setup_process;

        // 判定：過程檔唔存在（已收尾改名）→ 改讀完成檔（同一份記錄）
        if (!is_file($process_file)) {
            $process_file = $setup_done;
        }

        // 判定：讀取檔存在 → 讀返
        if (is_file($process_file)) {
            $process_raw = @file_get_contents($process_file);

            // 判定：讀得到 → 試解析
            if ($process_raw !== false) {
                $process_decoded = json_decode($process_raw, true);

                // 判定：解析到陣列 → 接住用
                if (is_array($process_decoded)) {
                    $process_data = $process_decoded;
                }
            }
        }

        return $process_data;
    }

    // 共用：設過程檔記錄值（setup_process.json——sections.<section>.<code> ＝ {value, update_at}；同項再設＝更新嗰條；完成檔已存在＝唔寫）
    public function set_setting_setup_process($section_id, $check_item_code, $setting_value)
    {
        $setup_done    = sc_get_config("setup.setup_done");
        $state_dir     = sc_get_config("setup.state_dir");
        $setup_process = sc_get_config("setup.setup_process");

        // 判定：完成檔已存在 → 唔寫（已收尾——唔再產生過程檔）
        if (is_file($setup_done)) {
            return;
        }

        // 判定：state 目錄唔存在 → 先建立
        if (!is_dir($state_dir)) {
            mkdir($state_dir, 0777, true);
        }

        $process_data = $this->read_setup_process();

        // 判定：sections 未立 → 立（空）
        if (!isset($process_data["sections"])) {
            $process_data["sections"] = [];
        }

        // 判定：該 section 未立 → 立（空）
        if (!isset($process_data["sections"][$section_id])) {
            $process_data["sections"][$section_id] = [];
        }

        $section_value_entry = [];
        $section_value_entry["value"]     = $setting_value;
        $section_value_entry["update_at"] = date("Y-m-d H:i:s");

        $process_data["sections"][$section_id][$check_item_code] = $section_value_entry;

        $process_json = json_encode($process_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        file_put_contents($setup_process, $process_json, LOCK_EX);
    }

    // 共用：設過程檔記錄值（setup_process.json——sections.<section>.<code> ＝ {value, update_at}；同項再設＝更新嗰條；完成檔已存在＝唔寫）
    public function set_setting_setup_process_v2($section_id, $check_item_code, $setting_value)
    {
        $setup_done    = sc_get_config("setup.setup_done");
        $state_dir     = sc_get_config("setup.state_dir");
        $setup_process = sc_get_config("setup.setup_process");

        // 判定：完成檔已存在 → 唔寫（已收尾——唔再產生過程檔）
        if (is_file($setup_done)) {
            return;
        }

        // 判定：state 目錄唔存在 → 先建立
        if (!is_dir($state_dir)) {
            mkdir($state_dir, 0777, true);
        }

        $process_data = $this->read_setup_process();

        // 判定：sections 未立 → 立（空）
        if (!isset($process_data["sections"])) {
            $process_data["sections"] = [];
        }

        // 判定：該 section 未立 → 立（空）
        if (!isset($process_data["sections"][$section_id])) {
            $process_data["sections"][$section_id] = [];
        }

        $process_data["sections"][$section_id][$check_item_code] = $setting_value;

        $process_json = json_encode($process_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        file_put_contents($setup_process, $process_json, LOCK_EX);
    }

    // 共助：砌敏感項記錄（遮罩〔前十八星＋尾四〕＋密文＋時間——單一來源；set_sensitive… 同各格 get_state_value 共用）
    public function build_sensitive_setting_entry($sensitive_plain_value)
    {
        $sensitive_value_entry = [];
        $sensitive_value_entry["value"]           = str_repeat("*", 18) . substr($sensitive_plain_value, -4);
        $sensitive_value_entry["encrypt_content"] = setup()->encrypter()->encrypt_text($sensitive_plain_value);
        $sensitive_value_entry["update_at"]       = date("Y-m-d H:i:s");

        return $sensitive_value_entry;
    }

    // 共用：清過程檔記錄值（setup_process.json——清走嗰條；冇嗰條＝唔動；完成檔已存在＝唔寫）
    public function remove_setting_setup_process($section_id, $check_item_code)
    {
        $setup_done    = sc_get_config("setup.setup_done");
        $setup_process = sc_get_config("setup.setup_process");

        // 判定：完成檔已存在 → 唔寫（已收尾）
        if (is_file($setup_done)) {
            return;
        }

        $process_data = $this->read_setup_process();

        // 判定：冇嗰條（格未立／項未記）→ 唔動
        if (!isset($process_data["sections"][$section_id][$check_item_code])) {
            return;
        }

        unset($process_data["sections"][$section_id][$check_item_code]);

        $process_json = json_encode($process_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        file_put_contents($setup_process, $process_json, LOCK_EX);
    }

    // 共用：取過程檔記錄值（setup_process.json——sections.<section>.<code>.value；冇記錄＝空串）
    public function get_setting_setup_process($section_id, $check_item_code)
    {
        $setup_process = $this->read_setup_process();

        $setting_value = "";

        // 判定：有該項記錄 → 取用
        if (isset($setup_process["sections"][$section_id][$check_item_code]["value"])) {
            $setting_value = $setup_process["sections"][$section_id][$check_item_code]["value"];
        }

        // 判定：唔係字串（叉入） → 歸空
        if (!is_string($setting_value)) {
            $setting_value = "";
        }

        return $setting_value;
    }

    // 共用：取敏感項明文（讀 encrypt_content → 解密；未記錄＝空串）
    public function get_sensitive_setting_setup_process($section_id, $check_item_code)
    {
        $setup_process = $this->read_setup_process();

        $sensitive_encrypt_content = "";

        // 判定：有該項密文 → 取用
        if (isset($setup_process["sections"][$section_id][$check_item_code]["encrypt_content"])) {
            $sensitive_encrypt_content = $setup_process["sections"][$section_id][$check_item_code]["encrypt_content"];
        }

        // 判定：唔係字串（未記錄）→ 回空
        if (!is_string($sensitive_encrypt_content)) {
            return "";
        }

        return setup()->encrypter()->decrypt_text($sensitive_encrypt_content);
    }

    // 判定：完成標記存在（首次自動檢查嘅依據——file_exists 一發）
    public function is_done()
    {
        $setup_done = sc_get_config("setup.setup_done");

        return is_file($setup_done);
    }
}
