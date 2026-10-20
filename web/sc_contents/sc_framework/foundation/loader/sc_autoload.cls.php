<?php
namespace sc\framework\foundation\loader;

class sc_autoload_Class 
{
    private static $instance;
    private static $loader;
    
    private $prefix_map = [];
    
    
    private function __construct()	{}
    private static function singleton()	{
        if(!@isset(self::$instance))
        {	$c                = __CLASS__;
            self::$instance = new $c();
        }
        return self::$instance;
    }
    
    // == 對齊 foundation 其他 class 的單例慣例（sc_file_loader_Class 同款） ==
    public static function getInstance()
    {
        return self::singleton();
    }
    
    
    
    public static function register()
    {
        $c      = __CLASS__;
        $loader = $c::singleton();
        
        if(@self::$loader)
        return null;
        
        self::$loader = $loader;
        
        
        spl_autoload_register([$loader, 'loadClassLoader'], true, true);
        return self::$loader;
    }
    
    public function add_prefix($prefix_map)
    {
        $this->prefix_map = array_replace($this->prefix_map,$prefix_map);
        self::$sorted_prefix_cache = null;
    }

    // 排序後 prefix 清單（per-request 快取——避免每次 autoload 都 usort）
    private static $sorted_prefix_cache = null;

    // cls 子目錄清單（per-request 快取——同一 plugin cls 只 scandir 一次）
    private static $cls_subdir_cache = [];

    private function get_sorted_prefixes()
    {
        // 判定：已有快取 → 直接回傳
        if (self::$sorted_prefix_cache !== null) {
            return self::$sorted_prefix_cache;
        }

        $prefixes = array_keys($this->prefix_map);
        usort($prefixes, function($prefixA, $prefixB) {
            return strlen($prefixB) - strlen($prefixA);
        });

        self::$sorted_prefix_cache = $prefixes;

        return self::$sorted_prefix_cache;
    }

    private function get_cls_subdirs($cls_dir)
    {
        // 判定：已有快取 → 直接回傳
        if (isset(self::$cls_subdir_cache[$cls_dir])) {
            return self::$cls_subdir_cache[$cls_dir];
        }

        $subdirs = [];

        // 判定：cls 目錄存在 → 掃描
        if (is_dir($cls_dir)) {
            $subdirs = scandir($cls_dir);
        }

        self::$cls_subdir_cache[$cls_dir] = $subdirs;

        return $subdirs;
    }
    
    
    public function loadClassLoader($class) {
        // 載入器自身早過 function 樹載入——用原生 trim（叫 sc_trim＝未載 Fatal）
        $class = trim($class);
        
        // == 先查 prefix 對映（PSR-4 式）：命中即組路徑 include ==
        if ($this->load_by_prefix_map($class)) {
            return;
        }
        
        // == 備援：猜路徑（原有 sc_cpn\ / sc\ 拆解） ==
        if(strpos($class,"sc_cpn\\") === 0 || strpos($class,"sc\\") === 0)
        {
            $this->load_SC_Class($class);
        }
    }
    
    // prefix 對映解析：sc\framework\http\sc_request_Class
    //   → 找最長匹配 prefix（sc\framework）
    //   → 剩餘段 http\sc_request_Class → 去 _Class → http\sc_request
    //   → 目錄 {prefix_dir}/http/sc_request/ ＋ 檔名 sc_request.cls.php
    // 候選規則（依序試）：
    //   1. module 制：{class_dir}/{base}/{base}.cls.php（既有結構，零衝擊）
    //   2. namespace 直映：{class_dir}/{base}.cls.php（class 直放子域目錄，namespace 自動追檔）
    private function load_by_prefix_map($class)
    {
        // 最長 prefix 優先：依長度排序（避免 sc\framework 被 sc\ 先吃掉）——排序結果 per-request 快取
        $prefixes = $this->get_sorted_prefixes();
        
        foreach ($prefixes as $prefix) {
            if (strpos($class, $prefix . "\\") === 0) {
                $remaining = substr($class, strlen($prefix . "\\"));
                $remaining = preg_replace("/_Class$/i", "", $remaining);
                
                $parts      = explode("\\", $remaining);
                $class_name = array_pop($parts);
                
                $class_dir = $this->prefix_map[$prefix];
                if (count($parts) > 0) {
                    $class_dir .= "/" . implode("/", $parts);
                }
                
                // plugin class（class_dir 含 /plugins/）→ plugin 專用檔名組合；否則一般直映
                if (strpos($class_dir, "/plugins/") !== false) {
                    $prefix_dir     = $this->prefix_map[$prefix];
                    $class_filepath = $this->get_sc_plugin_class_filepath($prefix_dir, $class_name, $parts);
                    
                    // 判定：一般映射命中 → 載入
                    if (is_file($class_filepath)) {
                        include($class_filepath);
                        return true;
                    }
                    
                    // 系統後備：目錄內 bridge 檔（_.bridge.cls.php——系統指定名）
                    // 次序＝一般映射先、bridge 後備：外層請求係 trait 時 bridge 先載會死鎖
                    //   （bridge 內 use 同一 trait——PHP 對同 class 嘅 autoload 遞歸守衛拒絕重入）
                    $bridge_filepath = $this->get_sc_plugin_bridge_filepath($prefix_dir, $parts);
                    
                    // 判定：bridge 檔存在 → 載入並核對目標定義（唔盲信）
                    if (is_file($bridge_filepath)) {
                        include_once($bridge_filepath);
                        
                        // 判定：目標已定義（class／interface／trait 任一）→ 命中
                        if (class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)) {
                            return true;
                        }
                    }
                    
                } else {
                    $class_filepath = $class_dir . "/" . $class_name . ".cls.php";
                    
                    // 判定：一般直映命中 → 載入
                    if (is_file($class_filepath)) {
                        include($class_filepath);
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    // plugin class 檔名解析（三種映射——優先序由上而下，任一命中即回傳）：
    // 映射 1【全名】{plugin}_{段}_{class}.php——例 sc_ai_projects_general_form_view.php
    //   路徑：cls/ 直映 ＋ cls/ 一層子目錄掃描（原始規則：2026-09-21 前唯一映射）
    // 映射 2【短名】{段}_{class}.php——例 general_form_view.php（去 plugin 前綴）
    //   路徑：cls/ 直映 ＋ cls/ 一層子目錄掃描（域目錄已表達 plugin 歸屬，檔名免重複 plugin 前綴）
    // 映射 3【裸名】{class}.php——例 view.php（連段名也省去）
    //   路徑：僅目錄直映——cls/{namespace 段}/…/{class}.php 一一對應、不掃描
    //   （防跨域同名：各域皆有 view.php 時，掃描順序會造成誤載——直映無歧義）
    // 共通：① 已知屬 plugins；② plugin name = "plugins" 之後一段；cls/ 為 class 位置
    //      （檔名無需 .cls.php 後綴——多此一舉）
    private function get_sc_plugin_class_filepath($prefix_dir, $class_name, $parts)
    {
        // ② 取 plugin name（"plugins" 之後一段）
        $plugin_index = array_search("plugins", $parts);
        $plugin_name  = $parts[$plugin_index + 1];
        
        // plugin 根目錄（不含子目錄——cls/ 在根目錄下）
        // 由 prefix 根＋namespace 段推導——零硬編 app 區（hkai 以外照通）
        $plugin_root = $prefix_dir . "/" . implode("/", array_slice($parts, 0, $plugin_index + 2));
        
        // ③④ plugin name 之後的段（不含 plugin name）＋ base → plugin name 前置 → _ 連接
        $after_plugin_parts   = array_slice($parts, $plugin_index + 2);
        $after_plugin_parts[] = $class_name;
        $file_base            = implode("_", array_merge([$plugin_name], $after_plugin_parts));
        
        // 映射 1【全名】直映：cls/{plugin}_{段}_{class}.php
        $direct_path = $plugin_root . "/cls/" . $file_base . ".php";
        if (is_file($direct_path)) {
            return $direct_path;
        }
        
        // 映射 1【全名】子目錄掃描：cls/{subdir}/{plugin}_{段}_{class}.php
        $cls_dir = $plugin_root . "/cls/";
        $subdirs = $this->get_cls_subdirs($cls_dir);

        // 跳過 . 和 ..
        foreach ($subdirs as $subdir) {
            if ($subdir === "." || $subdir === "..") {
                continue;
            }
            $candidate = $cls_dir . $subdir . "/" . $file_base . ".php";
            // 判定：子目錄內找到檔案 → 回傳路徑
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        
        // 映射 2【短名】（2026-09-21 使用者 IDEA）：去 plugin 前綴＝{段}_{class}.php
        // 例：sc_ai_projects\tab_general_form\ui_fields_Class → general_form_ui_fields.php
        // 目的：域目錄已表達 plugin 歸屬，檔名免重複 plugin 前綴
        // 順序：映射 1（全名）先行——短名僅後備，既有檔案解析行為零變化
        $file_base_short = implode("_", $after_plugin_parts);
        
        // 映射 2【短名】直映：cls/{段}_{class}.php
        $direct_short_path = $plugin_root . "/cls/" . $file_base_short . ".php";
        if (is_file($direct_short_path)) {
            return $direct_short_path;
        }
        
        // 映射 2【短名】子目錄掃描：cls/{subdir}/{段}_{class}.php
        $subdirs = $this->get_cls_subdirs($cls_dir);

        // 跳過 . 和 ..
        foreach ($subdirs as $subdir) {
            if ($subdir === "." || $subdir === "..") {
                continue;
            }
            $candidate_short = $cls_dir . $subdir . "/" . $file_base_short . ".php";
            // 判定：子目錄內找到短名檔案 → 回傳路徑
            if (is_file($candidate_short)) {
                return $candidate_short;
            }
        }
        
        // 映射 3【裸名】（2026-09-21 使用者 IDEA——極端縮短）：只剩 class 名＝{class}.php
        // 例：sc_ai_projects\tab_general_form\view_Class → cls/tab_general_form/view.php
        // 僅目錄直映（namespace 段即目錄名）——不掃描：各域皆有 view.php 時掃描順序會跨域誤載，
        //     直映則 FQN→路徑一一對應、零歧義；無段名的 plugin 根 class → cls/{class}.php（照樣末位後備）
        $namespace_subdirs = array_slice($parts, $plugin_index + 2);
        $direct_bare_path  = $plugin_root . "/cls/";
        
        // 逐段接上 namespace 子目錄（段＝目錄名一一對應）
        foreach ($namespace_subdirs as $namespace_subdir) {
            $direct_bare_path .= $namespace_subdir . "/";
        }
        $direct_bare_path .= $class_name . ".php";
        
        // 判定：裸名直映檔存在 → 回傳路徑
        if (is_file($direct_bare_path)) {
            return $direct_bare_path;
        }
        
        return $direct_path;
    }
    
    
    // bridge 檔路徑解析（plugin 目錄進目錄優先載入——系統指定名 _.bridge.cls.php）：
    // 位置＝plugin 根/cls/{namespace 子目錄段}/_.bridge.cls.php；非 plugin class → 回 ""
    private function get_sc_plugin_bridge_filepath($prefix_dir, $parts)
    {
        $plugin_index = array_search("plugins", $parts);
        
        // 判定：namespace 冇 plugins 段 → 冇 bridge 位置
        if ($plugin_index === false) {
            return "";
        }
        
        // bridge 位置＝prefix 根/{…至 plugin name}/cls/{其後段}/_.bridge.cls.php
        // 「cls」插在 plugin name 之後——零硬編 app 區（hkai 以外照通）
        $bridge_dir_parts = array_merge(
            array_slice($parts, 0, $plugin_index + 2),
            ["cls"],
            array_slice($parts, $plugin_index + 2)
        );
        
        return $prefix_dir . "/" . implode("/", $bridge_dir_parts) . "/_.bridge.cls.php";
    }
    
    
    
    private function load_SC_Class($class)
    {
        $master_dir = null;
        $subclass   = null;
        
        if(strpos($class,"sc_cpn\\") === 0)
        {
            $master_dir = SC_DIR_FRAMEWORK . "/../components";
            $subclass   = substr($class,strlen("sc_cpn\\"));
            $subclass   = preg_replace("/_Class$/i",'',$subclass);
            $pos        = strrpos($subclass,"\\");
            if($pos !== false)
            {
                $module     = substr($subclass,0,$pos);
                $classBase  = substr($subclass,$pos + 1);
                $subdirpath = $module."/cls/".$module."_".$classBase.".cls.php";
            }
        }
        else if(strpos($class,"sc\\") === 0)
        {
            $master_dir       = SC_DIR_APP;
            $subclass         = substr($class,strlen("sc\\"));
            $subclass         = preg_replace("/_Class$/i",'',$subclass);
            $subclass_explode = explode("\\",$subclass);
            
            if(isset($subclass_explode[0]) && $subclass_explode[0] === "plugins")
            {
                $dir_part_1 = array_slice($subclass_explode,0,1);
                $dir_part_2 = array_slice($subclass_explode,1,1);
                $dir_part_3 = array_slice($subclass_explode,2);
            }
            else
            {
                $dir_part_1 = array_slice($subclass_explode,0,2);
                $dir_part_2 = array_slice($subclass_explode,2,1);
                $dir_part_3 = array_slice($subclass_explode,3);
            }
            $dir_part_2[] = "cls";
            
            // == namespace 段數不足（無法構成 class 名）：不屬 sc\ 拆解結構，放棄 ==
            if(count($dir_part_3) === 0)
            {
                return;
            }
            
            if(count($dir_part_3) > 1)
            $class_dirname = $dir_part_3[count($dir_part_3) - 2];
            else 
            $class_dirname = $dir_part_2[0];
            
            $class_name                         = @$class_dirname."_".$dir_part_3[count($dir_part_3) - 1];
            $dir_part_3[count($dir_part_3) - 1] = $class_name;
            
            $subdirpath = [];
            $subdirpath = array_merge($subdirpath,$dir_part_1);
            $subdirpath = array_merge($subdirpath,$dir_part_2);
            $subdirpath = array_merge($subdirpath,$dir_part_3);
            $subdirpath = implode("/",$subdirpath).".php";
        }
        
        if(!@$subdirpath)
        return ;
        
        $test_includefile = $master_dir."/".$subdirpath;
        
        if(@file_exists($test_includefile))
        {
            include($test_includefile);
        }
    }
    
    
}
