<?php

namespace sc\setup\sections;

// 短名域（prerequisite 域四門——basic()／php_extension()／executor()／permission()）
use sc\setup\sections\prerequisite;

// 短名域（database 域單元——派發直取）
use sc\setup\sections\database;

// 短名域（setting 域單元——派發直取）
use sc\setup\sections\setting;

// 短名域（install 域門——派發直取）
use sc\setup\sections\install;

// == HKAI setup sections sections.cls.php：sections 域本體（class 載單元＋六格註冊表 $sections——六格門；方法名＝檔名）
// == 用法：\sc\setup\sections\sections_Class::getInstance()->welcome()——直取 welcome 格單元
// == 六格：welcome／prerequisite／database／settings／install／completed（次序＝註冊表次序；註冊表 ctor 即砌）

class sections_Class
{
    private static $instance = null;

    // 六格註冊表（id => 名——唯一來源；次序＝navigation 次序；載入／class 由下面六個格門各自負責）
    private $sections = [];

    /**
     * 單例（系統唯一入口——F12 鏈式跳定義靠本註解）
     *
     * @return \sc\setup\sections\sections_Class
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
        // 六格註冊表（次序＝navigation 次序——底列導覽「第一／最後／前後」由此定）
        $this->sections = [
            "welcome" => [
                "name"  => ["zh-HK" => "歡迎", "en-US" => "Welcome"],
            ],
            "prerequisite" => [
                "name"  => ["zh-HK" => "前置檢查", "en-US" => "Prerequisites"],
            ],
            "database" => [
                "name"  => ["zh-HK" => "資料庫", "en-US" => "Database"],
            ],
            "settings" => [
                "name"  => ["zh-HK" => "設定", "en-US" => "Settings"],
            ],
            "install" => [
                "name"  => ["zh-HK" => "安裝", "en-US" => "Install"],
            ],
            "completed" => [
                "name"  => ["zh-HK" => "完成", "en-US" => "Completed"],
            ],
        ];
    }

    // 攞註冊表（六格：id => 名）
    public function get_sections()
    {
        return $this->sections;
    }

    // 攞合法格 id（唔合法／唔存在 → 第一格——唯一裁決位）
    public function get_section_id($section_id)
    {
        // 判定：唔係字串（叉入） → 歸空
        if (!is_string($section_id)) {
            $section_id = "";
        }

        // 判定：唔喺註冊表（含空） → 用第一格
        if (!array_key_exists($section_id, $this->sections)) {
            $section_ids = array_keys($this->sections);
            $section_id  = $section_ids[0];
        }

        return $section_id;
    }

    /**
     * welcome 格單元（單例——按需 require 單元檔）
     *
     * @return \sc\setup\sections\welcome_Class
     */
    public function welcome()
    {
        // 載入 welcome 格本體＋格 func（setup() 短名——include once）
        require_once __DIR__ . "/welcome/welcome.php";
        require_once __DIR__ . "/welcome/welcome.func.php";

        return welcome_Class::getInstance();
    }

    /**
     * prerequisite 格單元（單例——按需 require 單元檔）
     *
     * @return \sc\setup\sections\prerequisite\prerequisite_Class
     */
    public function prerequisite()
    {
        // 載入 prerequisite 格本體＋格 func（setup() 短名——include once）
        require_once __DIR__ . "/prerequisite/prerequisite.php";
        require_once __DIR__ . "/prerequisite/_.internal.func.php";

        return prerequisite\prerequisite_Class::getInstance();
    }

    /**
     * database 格單元（單例——按需 require 單元檔）
     *
     * @return \sc\setup\sections\database_Class
     */
    public function database()
    {
        // 載入 database 格本體＋格 func（setup() 短名——include once）
        require_once __DIR__ . "/database/database.php";
        require_once __DIR__ . "/database/database.func.php";

        return database_Class::getInstance();
    }

    /**
     * settings 格單元（單例——按需 require 單元檔）
     *
     * @return \sc\setup\sections\setting_Class
     */
    public function setting()
    {
        // 載入 setting 格本體＋格 func（setup() 短名——include once）
        require_once __DIR__ . "/setting/setting.php";
        require_once __DIR__ . "/setting/setting.func.php";

        return setting_Class::getInstance();
    }

    /**
     * install 格單元（單例——按需 require 單元檔）
     *
     * @return \sc\setup\sections\install_Class
     */
    public function install()
    {
        // 載入 install 格本體＋格 func（setup() 短名——include once）
        require_once __DIR__ . "/install/install.php";
        require_once __DIR__ . "/install/install.func.php";

        return install_Class::getInstance();
    }

    /**
     * completed 格單元（單例——按需 require 單元檔）
     *
     * @return \sc\setup\sections\completed_Class
     */
    public function completed()
    {
        // 載入 completed 格本體＋格 func（setup() 短名——include once）
        require_once __DIR__ . "/completed/completed.php";
        require_once __DIR__ . "/completed/completed.func.php";

        return completed_Class::getInstance();
    }

    /**
     * 格導覽（第一／最後／前後格——次序由本域註冊表 $sections 定；例：格底列導覽）
     *
     * @param string $section_id 格 id
     *
     * @return array 導覽（is_first／is_last／prev_section_id／next_section_id）
     */
    public function get_section_nav($section_id)
    {
        $section_ids = array_keys($this->sections);

        $section_index = array_search($section_id, $section_ids);

        $section_nav = [
            "is_first"        => ($section_id === $section_ids[0]),
            "is_last"         => ($section_id === $section_ids[count($section_ids) - 1]),
            "prev_section_id" => "",
            "next_section_id" => "",
        ];

        // 判定：唔係第一格 → 落前一格 id
        if ($section_index > 0) {
            $section_nav["prev_section_id"] = $section_ids[$section_index - 1];
        }

        // 判定：唔係最後一格 → 落後一格 id
        if ($section_index < count($section_ids) - 1) {
            $section_nav["next_section_id"] = $section_ids[$section_index + 1];
        }

        return $section_nav;
    }

    /**
     * 格檢查項單元（按 section_id＋check_item_code 取——全格對映收於本域＝唯一派發位）
     *
     * @param string $section_id      格 id
     * @param string $check_item_code 檢查項 code
     *
     * @return object 檢查項單元（格／項未知＝null）
     */
    public function get_section_unit_instance($section_id, $check_item_code)
    {
        // 判定：welcome 格 → 逐項對單元
        if ($section_id === "welcome") {
            // 判定：語言項 → welcome 本體
            if ($check_item_code === "language") {
                return setup()->sections()->welcome()->language();
            }
        }
        // 判定：prerequisite 格 → 逐項對單元（格本體先載——四門短名隨載）
        elseif ($section_id === "prerequisite") {
            setup()->sections()->prerequisite();

            if ($check_item_code === "basic__php_version") {
                return prerequisite\basic()->php_version();
            }
            elseif ($check_item_code === "basic__os_version") {
                return prerequisite\basic()->os_version();
            }
            elseif ($check_item_code === "basic__mod_rewrite") {
                return prerequisite\basic()->mod_rewrite();
            }
            elseif ($check_item_code === "basic__memory_limit") {
                return prerequisite\basic()->memory_limit();
            }
            elseif ($check_item_code === "basic__max_execution_time") {
                return prerequisite\basic()->max_execution_time();
            }
            elseif ($check_item_code === "php_extension__pdo_sqlite") {
                return prerequisite\php_extension()->pdo_sqlite();
            }
            elseif ($check_item_code === "php_extension__curl") {
                return prerequisite\php_extension()->curl();
            }
            elseif ($check_item_code === "php_extension__curl_cainfo") {
                return prerequisite\php_extension()->curl_cainfo();
            }
            elseif ($check_item_code === "php_extension__openssl") {
                return prerequisite\php_extension()->openssl();
            }
            elseif ($check_item_code === "php_extension__sodium") {
                return prerequisite\php_extension()->sodium();
            }
            elseif ($check_item_code === "php_extension__mbstring") {
                return prerequisite\php_extension()->mbstring();
            }
            elseif ($check_item_code === "php_extension__json") {
                return prerequisite\php_extension()->json();
            }
            elseif ($check_item_code === "php_extension__exec") {
                return prerequisite\php_extension()->exec();
            }
            elseif ($check_item_code === "php_extension__shell_exec") {
                return prerequisite\php_extension()->shell_exec();
            }
            elseif ($check_item_code === "php_extension__popen") {
                return prerequisite\php_extension()->popen();
            }
            elseif ($check_item_code === "php_extension__pclose") {
                return prerequisite\php_extension()->pclose();
            }
            elseif ($check_item_code === "php_extension__escapeshellarg") {
                return prerequisite\php_extension()->escapeshellarg();
            }
            elseif ($check_item_code === "php_extension__proc_open") {
                return prerequisite\php_extension()->proc_open();
            }
            elseif ($check_item_code === "php_extension__proc_close") {
                return prerequisite\php_extension()->proc_close();
            }
            elseif ($check_item_code === "php_extension__proc_get_status") {
                return prerequisite\php_extension()->proc_get_status();
            }
            elseif ($check_item_code === "php_extension__proc_terminate") {
                return prerequisite\php_extension()->proc_terminate();
            }
            elseif ($check_item_code === "php_extension__putenv") {
                return prerequisite\php_extension()->putenv();
            }
            elseif ($check_item_code === "php_extension__getenv") {
                return prerequisite\php_extension()->getenv();
            }
            elseif ($check_item_code === "php_extension__flock") {
                return prerequisite\php_extension()->flock();
            }
            elseif ($check_item_code === "php_extension__fsockopen") {
                return prerequisite\php_extension()->fsockopen();
            }
            elseif ($check_item_code === "environment__python_executable") {
                return prerequisite\executor()->python_executable();
            }
            elseif ($check_item_code === "environment__pip") {
                return prerequisite\executor()->pip();
            }
            elseif ($check_item_code === "environment__python_packages") {
                return prerequisite\executor()->python_packages();
            }
            elseif ($check_item_code === "environment__goose_executable") {
                return prerequisite\executor()->goose_executable();
            }
            elseif ($check_item_code === "environment__aider_pylibs_dir") {
                return prerequisite\executor()->aider_pylibs_dir();
            }
            elseif ($check_item_code === "environment__env_writable") {
                return prerequisite\permission()->env_writable();
            }
            elseif ($check_item_code === "environment__services_dir") {
                return prerequisite\permission()->services_dir();
            }
            elseif ($check_item_code === "environment__storage_dirs") {
                return prerequisite\permission()->storage_dirs();
            }
            elseif ($check_item_code === "environment__project_ready") {
                return prerequisite\permission()->project_ready();
            }
            elseif ($check_item_code === "environment__disk_space") {
                return prerequisite\permission()->disk_space();
            }
        }
        // 判定：database 格 → 逐項對單元（格本體先載——域短名檔隨載）
        elseif ($section_id === "database") {
            setup()->sections()->database();

            if ($check_item_code === "db_ready") {
                return database\db_ready();
            }
        }
        // 判定：settings 格 → 逐項對單元（格本體先載——units 類同檔隨載）
        elseif ($section_id === "settings") {
            setup()->sections()->setting();

            if ($check_item_code === "initialization__timezone") {
                return setting\timezone();
            }
            elseif ($check_item_code === "key_settings__openrouter") {
                return setting\provider()->openrouter();
            }
            elseif ($check_item_code === "key_settings__kilocode") {
                return setting\provider()->kilocode();
            }
            elseif ($check_item_code === "key_settings__llm_stats") {
                return setting\benchmark()->llm_stats();
            }
            elseif ($check_item_code === "key_settings__artificial_analysis") {
                return setting\benchmark()->artificial_analysis();
            }
        }
        // 判定：install 格 → 逐項對單元（格本體先載——域短名檔隨載）
        elseif ($section_id === "install") {
            setup()->sections()->install();

            // 判定：完成標記項 → 標記單元
            if ($check_item_code === "install_marker") {
                return install\install_marker();
            }
        }

        return null;
    }
}
