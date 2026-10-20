<?php

namespace sc\setup\i18n;

// == HKAI setup i18n：i18n 域本體（域本體——singleton；獨立於 sc_app／sc_storage——setup 自己一套）
// == 字典檔：cls/i18n/lang/{lang_code}.json（flat dot-key——同 app 慣例；檔內 lang.name＝native 名）
// == 語系碼來源：過程檔記錄（welcome.language）→ 可信語言偵測（依序）

class i18n_Class
{
    private static $instance = null;

    private $lang_data = null;

    private $loaded_lang_code = null;

    /**
     * 單例（系統唯一入口——F12 鏈式跳定義靠本註解）
     *
     * @return \sc\setup\i18n\i18n_Class
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }

    public function __construct()
    {
    }

    // 語言清單（trace 本目錄 lang/*.json——真檔先算可信；名＝檔內 lang.name·冇＝碼本身）
    public function get_languages()
    {
        $lang_dir = $this->get_lang_dir();

        // 顯示次序（primary 先——次序外嘅碼掃描補後）
        $display_order = [
            "zh-HK",
            "en-US",
        ];

        $languages = [];

        // 顯示次序先——逐個驗真檔
        foreach ($display_order as $language_code) {
            $language_name = $this->read_language_name($language_code);

            // 判定：有名（＝真檔存在） → 入列
            if ($language_name !== "") {
                $languages[$language_code] = $language_name;
            }
        }

        $dir_items = [];

        // 判定：目錄存在 → 讀入項目
        if (is_dir($lang_dir)) {
            $dir_items = scandir($lang_dir);
        }

        // 顯示次序外嘅碼（新增語言自動跟）
        foreach ($dir_items as $dir_item) {
            // 判定：唔係 .json → 跳過
            if (substr($dir_item, -5) !== ".json") {
                continue;
            }

            $language_code = basename($dir_item, ".json");

            // 判定：已入列 → 跳過
            if (isset($languages[$language_code])) {
                continue;
            }

            $language_name = $this->read_language_name($language_code);

            // 判定：有名 → 入列
            if ($language_name !== "") {
                $languages[$language_code] = $language_name;
            }
        }

        return $languages;
    }

    // 可信語言（標準法——Accept-Language：拆段·q 值大先·tag 對照清單；對唔到＝預設 zh-HK）
    public function detect_language()
    {
        $accept_language = "";

        // 判定：有 Accept-Language 標頭 → 取用
        if (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])) {
            $accept_language = $_SERVER["HTTP_ACCEPT_LANGUAGE"];
        }

        $languages = $this->get_languages();

        // 對照表（細寫碼 → 原碼——標頭 tag 一律細寫比對）
        $language_lookup = [];

        // 逐個建立對照
        foreach ($languages as $language_code => $language_name) {
            $language_lookup[strtolower($language_code)] = $language_code;
        }

        $fallback_language = "zh-HK";

        // 拆段（en-US,en;q=0.9,zh-TW;q=0.8——標準逗號分格）
        $candidates = [];

        $accept_parts = explode(",", $accept_language);

        // 逐段拆 tag＋q
        foreach ($accept_parts as $accept_part_item) {
            $segments = explode(";", $accept_part_item);

            $language_tag = trim($segments[0]);
            $language_tag = strtolower($language_tag);

            $quality = 1.0;

            // 判定：有第二段（q 參數） → 取 q 值
            if (isset($segments[1])) {
                $quality_text = trim($segments[1]);
                $quality_text = strtolower($quality_text);
                $quality_text = str_replace("q=", "", $quality_text);

                $quality = (float) $quality_text;
            }

            $candidates[] = [
                "tag"     => $language_tag,
                "quality" => $quality,
            ];
        }

        // q 值大先（標準排序）
        usort($candidates, function ($candidate_a, $candidate_b) {
            // 判定：q 相等 → 維持（0）
            if ($candidate_a["quality"] === $candidate_b["quality"]) {
                return 0;
            }

            // 判定：a 大過 b → a 先
            if ($candidate_a["quality"] > $candidate_b["quality"]) {
                return -1;
            }

            return 1;
        });

        // 逐個候選（q 大先——第一個對到嘅就係）
        foreach ($candidates as $candidate_item) {
            $language_tag = $candidate_item["tag"];

            // 判定：整串命中（zh-hk／en-us）
            if (isset($language_lookup[$language_tag])) {
                return $language_lookup[$language_tag];
            }

            // 主碼（en-gb → en；zh-tw → zh）
            $tag_parts   = explode("-", $language_tag);
            $primary_tag = $tag_parts[0];

            // 同主碼嘅可得語言（en → [en-US]；zh → [zh-HK]；將來 zh-CN 加入＝多過一個）
            $primary_matches = [];

            // 逐個揀同主碼
            foreach ($language_lookup as $lookup_code => $real_code) {
                $lookup_parts = explode("-", $lookup_code);

                // 判定：主碼相同 → 入選
                if ($lookup_parts[0] === $primary_tag) {
                    $primary_matches[] = $real_code;
                }
            }

            // 判定：啱好一個 → 回嗰個（en-gb → en-US；zh-tw → zh-HK）；0／多過一個＝試下一個候選
            if (count($primary_matches) === 1) {
                return $primary_matches[0];
            }
        }

        return $fallback_language;
    }

    // 目前語系碼（過程檔記錄先行——welcome.language；冇＝可信語言偵測）
    public function get_lang_code()
    {
        $languages = $this->get_languages();

        // 用戶記錄（過程檔——welcome.language）
        $recorded_lang = setup()->state()->get_setting_setup_process("welcome", "language");

        // 判定：有記錄且喺清單 → 用嗰個
        if (array_key_exists($recorded_lang, $languages)) {
            return $recorded_lang;
        }

        return $this->detect_language();
    }

    // 查字（未命中 → 回 key 本身）
    public function get($key)
    {
        $lang_code = $this->get_lang_code();

        $lang_data = $this->get_lang_data($lang_code);

        // 判定：字典有此 key → 回譯文
        if (array_key_exists($key, $lang_data)) {
            return $lang_data[$key];
        }

        return $key;
    }

    // 字典資料（快取——語系碼不變不重讀）
    private function get_lang_data($lang_code)
    {
        // 判定：語系碼與已載入不同 → 重新載入
        if ($this->loaded_lang_code !== $lang_code) {
            $this->lang_data        = $this->load_lang_file($lang_code);
            $this->loaded_lang_code = $lang_code;
        }

        return $this->lang_data;
    }

    // 讀單一語言 native 名（冇檔 → 空；有檔冇 lang.name → 用碼本身）
    private function read_language_name($language_code)
    {
        $lang_file = $this->get_lang_dir() . "/" . $language_code . ".json";

        // 判定：冇檔 → 空（唔算可信）
        if (!is_file($lang_file)) {
            return "";
        }

        $lang_data = $this->load_lang_file($language_code);

        $language_name = $language_code;

        // 判定：檔內有名 → 用 native 名
        if (isset($lang_data["lang.name"])) {
            $language_name = $lang_data["lang.name"];
        }

        return $language_name;
    }

    // 載入語系檔（不存在／解析失敗 → 空字典）
    private function load_lang_file($lang_code)
    {
        $lang_file = $this->get_lang_dir() . "/" . $lang_code . ".json";

        // 判定：語系檔不存在 → 空字典
        if (!is_file($lang_file)) {
            return [];
        }

        $json_content = file_get_contents($lang_file);
        $lang_data    = json_decode($json_content, true);

        // 判定：解析失敗 → 空字典
        if (!is_array($lang_data)) {
            return [];
        }

        return $lang_data;
    }

    // 本單元字典目錄（單一來源＝sc_config/load.php——sc_get_config 直取）
    private function get_lang_dir()
    {
        $lang_dir = sc_get_config("setup.lang_dir");

        return $lang_dir;
    }
}
