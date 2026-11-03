<?php

namespace sc\app\hkai\cls\settings;

// == settings_Class：hkai app 層用戶設定（singleton——經 hkai()->settings() 取得） ==
// == 檔案：出廠 web/sc_contents/sc_app/hkai/cls/settings/settings.json（唯讀）＋用戶 web/sc_contents/sc_app/hkai/cls/settings/settings.json（覆蓋、可寫） ==
// == API：get("group.key", default)／get_group(group)／set("group.key", value)（原子寫） ==
// == 上游：i18n（general.language）、sc_ai_settings general 分頁；下游：sc_get_config（兩個檔路徑）

class settings_Class
{
    private static $instance = null;

    // 有效設定快取（request 內一次；set() 後失效重讀）
    private $effective_settings = null;

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

    // 取單一設定（group.key；只寫組名 → 回整組）
    public function get($setting_key, $default_value = null)
    {
        $setting_parts = explode(".", $setting_key, 2);

        // 判定：只有組名 → 回整組
        if (count($setting_parts) === 1) {
            return $this->get_group($setting_parts[0]);
        }

        $settings_group_key = $setting_parts[0];
        $setting_name       = $setting_parts[1];
        $settings_group     = $this->get_group($settings_group_key);

        // 判定：組內無此 key → 預設值
        if (array_key_exists($setting_name, $settings_group) === false) {
            return $default_value;
        }

        return $settings_group[$setting_name];
    }

    // 取整組（缺組 → 空表）
    public function get_group($settings_group_key)
    {
        $effective_settings = $this->get_effective_settings();

        // 判定：無此組 → 空表
        if (array_key_exists($settings_group_key, $effective_settings) === false) {
            return [];
        }

        return $effective_settings[$settings_group_key];
    }

    // 寫單一設定（只寫 web/sc_contents/sc_app/hkai/cls/settings/settings.json；必須 group.key）
    public function set($setting_key, $setting_value)
    {
        $setting_parts = explode(".", $setting_key, 2);

        // 判定：唔係 group.key → 拒絕（寫入必須指明組）
        if (count($setting_parts) !== 2) {
            return false;
        }

        $settings_group_key = $setting_parts[0];
        $setting_name       = $setting_parts[1];
        $user_settings_file = sc_get_config("hkai.public.settings");
        $user_settings      = $this->read_settings_file($user_settings_file);
        $settings_group     = $user_settings[$settings_group_key] ?? [];

        // 判定：用戶組非陣列（壞檔）→ 以空組為底
        if (!is_array($settings_group)) {
            $settings_group = [];
        }

        $settings_group[$setting_name]        = $setting_value;
        $user_settings[$settings_group_key]   = $settings_group;

        $is_written = $this->write_settings_file($user_settings_file, $user_settings);

        // 判定：寫入失敗 → false
        if ($is_written === false) {
            return false;
        }

        // 快取失效（下次讀重新合併）
        $this->effective_settings = null;

        return true;
    }

    // 有效設定（出廠 ← 用戶覆蓋；逐組 array_replace）
    private function get_effective_settings()
    {
        // 判定：已有快取 → 直接回
        if ($this->effective_settings !== null) {
            return $this->effective_settings;
        }

        $default_settings = $this->read_settings_file(sc_get_config("hkai.settings"));
        $user_settings    = $this->read_settings_file(sc_get_config("hkai.public.settings"));

        $effective_settings = $default_settings;

        // 逐組合併（用戶值覆蓋出廠值）
        foreach ($user_settings as $settings_group_key => $settings_group_values) {
            $default_group_values = $effective_settings[$settings_group_key] ?? [];

            // 判定：出廠組非陣列 → 以空組為底
            if (!is_array($default_group_values)) {
                $default_group_values = [];
            }

            // 判定：用戶組非陣列（壞檔）→ 跳過該組
            if (!is_array($settings_group_values)) {
                continue;
            }

            $effective_settings[$settings_group_key] = array_replace($default_group_values, $settings_group_values);
        }

        $this->effective_settings = $effective_settings;

        return $effective_settings;
    }

    // 讀設定檔（缺檔／空檔／壞 JSON → 空表）
    private function read_settings_file($settings_file)
    {
        // 判定：檔唔存在 → 空表
        if (is_file($settings_file) === false) {
            return [];
        }

        $settings = json_decode((string)file_get_contents($settings_file), true);

        // 判定：非陣列（空檔／壞 JSON）→ 空表
        if (!is_array($settings)) {
            return [];
        }

        return $settings;
    }

    // 原子寫設定檔（temp＋rename；美化 JSON、保留中文與斜線）
    private function write_settings_file($settings_file, $settings)
    {
        $settings_text = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // 判定：編碼失敗 → false
        if ($settings_text === false) {
            return false;
        }

        $temp_file  = $settings_file . ".tmp";
        $is_written = file_put_contents($temp_file, $settings_text . "\n", LOCK_EX);

        // 判定：寫 temp 失敗 → false
        if ($is_written === false) {
            return false;
        }

        return rename($temp_file, $settings_file);
    }
}
