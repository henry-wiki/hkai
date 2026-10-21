<?php
namespace sc\framework\routing\controller;

// == sc_controller：controller 管理器＋執行語境（對應 Laravel Illuminate\Routing\Controller） ==
// == 主 controller（singleton）＝管理器：持有執行語境 list，依 route 建立/取回（createRouteController） ==
// == 執行語境 controller（每次 new）＝路由執行 instance：run() 內 require include 檔 ==
// == include 檔內 $this = 執行語境 instance（統一語境：web 頁面／api handler 皆同） ==
// == 多態 response 工廠：json()／view()／redirect()——handler 內 $this->json([...], 502) 取代全域 sc_response() ==
// == 命名空間 sc\framework\routing\controller → autoload 對映 sc_framework/routing/controller/sc_controller.cls.php ==

class sc_controller_Class
{
    // 主 controller 單例（bridging sc_controller() 無參數時回傳——持有執行語境 list）
    private static $instance = null;
    
    // 執行語境 list（route_path → instance——每請求生命週期；以 sc_controller($route) 建立/取回）
    private $route_controllers = [];
    
    private $plugins = [];
    
    // 統一 request 發名單（URL 段位 key → FILE；app 層 key = {action}、plugin 層 key = {plugin_id}/{action}——request 與 plugin 解耦）
    private $registered_request_files = [];
    
    
    // 資料：route（route_path/request_path/route_data/group——setRoute 注入）
    private $route;
    
    // 取得主 controller（singleton——createRouteController 的入口）
    public static function get_instance($route = null)
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        $root_controller = self::$instance;
        
        if ($route) {
            return $root_controller->create_route_controller($route);
        }
        return $root_controller;
    }
    
    public function __construct()
    {
    }
    
    // 依 routePath 建立/取回執行語境 controller（存在直接回傳；不存在 new＋加入 list）
    public function create_route_controller($route = [])
    {
        $route_path = $route["route_path"];
        
        if (isset($this->route_controllers[$route_path])) {
            return $this->route_controllers[$route_path];
        }
        
        $route_controller = new self();
        $route_controller->set_route($route);
        
        $this->route_controllers[$route_path] = $route_controller;
        
        
        
        return $route_controller;
    }
    
    public function set_route($route)
    {
        $this->route = $route;
    }
    public function get_route()
    {
        return $this->route;
    }
    
    public function get_app_dir()
    {
        $route_path = $this->route["route_path"];
        return $route_path;
    }
    
    // 註冊 plugin（路徑：app>plugins>{name}>__init__.php 呼叫——plugin_id = 目錄名；委託 sc_plugin 管理器建立/取回 instance）
    public function register_plugin($plugin_dir, $plugin_settings = [])
    {
        // plugin_id = plugin 目錄名（app>plugins>sc_ai_projects → sc_ai_projects）
        $plugin_id = basename($plugin_dir);
        
        // 委託 sc_plugin 管理器建立/取回 plugin instance（settings 缺省值在 plugin class 內合併）
        $plugin_instance = sc_plugin($plugin_id, $plugin_dir, $plugin_settings);
        
        $this->plugins[$plugin_id] = $plugin_instance;
        
        return $plugin_instance;
    }
    
    // 取得已註冊 plugins（plugin_id → plugin instance；settings 由 instance->getSettings() 取）
    public function get_plugins()
    {
        return $this->plugins;
    }
    public function get_enabled_plugins()
    {
        return array_filter($this->plugins,function($plugin) {
            return $plugin->enabled;
        });
        
    }
    
    // 註冊 request action（路徑：app>__init__.php 呼叫——key = {action}；plugin 層經收編，key = {plugin_id}/{action}）
    public function register_request_action($action, $filepath)
    {
        // 判定：key 已註冊過 → 唔准重覆（靜默覆蓋＝其中一邊永遠唔生效；直接拋錯——錯喺源頭爆）
        if (isset($this->registered_request_files[$action])) {
            $registered_filepath = $this->registered_request_files[$action];
            throw new \InvalidArgumentException("request action 重複註冊：" . $action . "（已註冊：" . $registered_filepath . "；重覆：" . $filepath . "）");
        }

        $this->registered_request_files[$action] = $filepath;
        return $this;
    }
    
    // request 分派（以 controller 為中心——所有 request 都登記在此：載入語境 → 查統一表 → 回目標檔；null = 未登記）
    // $request_key：由 sc_request/index.php 解析（支援斜線分隔——如 settings/get_server_settings）
    public function dispatch_request($request_key)
    {
        // 空 key → 拒絕
        if ($request_key === "") {
            return null;
        }

        // 載入 app 語境（路徑：app>cls、app>__init__.php、app>plugins>__init__、enabledPlugins>cls>*.func.php——白名單於此登記）
        $this->load_app_context();
        
        // 查統一表（action key → FILE；未登記回 null）
        return isset($this->registered_request_files[$request_key]) ? $this->registered_request_files[$request_key] : null;
    }
    
    // 載入 app 語境（路徑順序：app>cls>*.func.php、app>__init__.php、app>plugins>*>__init__.php、enabledPlugins>cls>*.func.php——不執行執行檔）
    // run() 與 request 分派器共用：語境一致（$this = 本 instance），enabled 檢查包含在內
    public function load_app_context()
    {
        $route_path = $this->route["route_path"];
        
        // ① 載入 app>cls 的 func 檔（bridging——如 app>cls>_.func.php；與 plugins load_func 同機制）
        $app_cls_dir = $route_path . "/cls";
        if (is_dir($app_cls_dir)) {
            sc_file_loader()->load_func($app_cls_dir,0);
        }
        
        // ② 載入 app>__init__.php（app 設定；require_once 防重複）
        $init_path = $route_path . "/__init__.php";
        if (is_file($init_path)) {
            require_once $init_path;
        }
        
        // ③ 掃描 app>plugins> 一層，載入各 plugin 的 __init__.php（find 只回傳清單、不載入）
        $plugins_dir = $route_path . "/plugins";
        $init_files  = [];
        if (is_dir($plugins_dir)) {
            $init_files = sc_file_loader()->find($plugins_dir, "re:/^__init__\\.php$/i", 1);
        }
        foreach ($init_files as $init_file) {
            require_once $init_file;
        }
        
        // ④ 載入 enabledPlugins>cls>*.func.php（bridging——僅 cls 子目錄，不掃整個 plugin）
        $enabled_plugins = $this->get_enabled_plugins();
        foreach ($enabled_plugins as $plugin) {
            sc_file_loader()->load_func($plugin->plugin_dir . "/cls");
            
            
        }
        // -- load the request here --
        foreach ($enabled_plugins as $plugin) {
            
            $plugin_request_actions = $plugin->get_request_actions();
            
            foreach($plugin_request_actions as $_action => $_action_file)
            {
                $this->register_request_action($_action,$_action_file);
            }
        }
        
    }
    
    // 執行 include 檔（web 頁面／api handler——require 語境使 $this = 本 instance）
    // route_path：目錄 → 執行慣例檔 {dir}>index.php；直接指向檔案 → 原樣執行（plugin request 直接檔風格）
    // 同語境載入順序：app>__init__.php → app>plugins>*>__init__.php → {dir}>index.php
    public function run()
    {
        $route_path = $this->route["route_path"];
        
        // route_path 直接指向檔案 → 直接執行（無 app/plugins 載入——執行檔自行確保依賴）
        if (is_file($route_path)) {
            return require $route_path;
        }
        
        // 載入語境（app>cls>*.func.php、app>__init__.php、app>plugins>*>__init__.php、enabledPlugins>cls>*.func.php）
        $this->load_app_context();
        
        // 執行慣例檔 {route_path}>index.php
        return require $route_path . "/index.php";
    }
    
    // 取得路由固定資料（route 宣告第三元素——帶 key 取單項；無 key 回整包）
    public function get_route_data($key = null)
    {
        $route_data = $this->route["route_data"];
        
        // 無 key → 整包回傳
        if ($key === null) {
            return $route_data;
        }
        
        // 有 key → 單項（key 不存在時回 null——呼叫方寫錯 key 不炸）
        if (isset($route_data[$key])) {
            return $route_data[$key];
        }
        
        return null;
    }
    
    // 取得路由組別（"app"/"api"）
    public function get_group()
    {
        return $this->route["group"];
    }
    
    // 取得原始請求 path（router 不處理 pattern——app/handler 自行解析動態段用）
    // 註：router get_route 回傳的 key 為 request_url_path（含 url_path/route_path/group/route_data）
    public function get_request_path()
    {
        return $this->route["request_url_path"];
    }
    
    // 建立延遲渲染 View 物件（Laravel view() 對應——controller return view）
    // 建立時注入本 controller——模板內 $this->get_controller() 可達執行語境
    public function view($name, $data = [])
    {
        $view = sc_view($name, $data);
        $view->set_controller($this);
        return $view;
    }
    
    // 建立延遲渲染 View 物件（自動補 app 前綴版——只收相對名）
    // 例：get_view("sidebar") → {route_path}>views>sidebar.php（sc_app>hkai>views>sidebar.php）
    // app 名由 route 的 route_path 推導（sc_app>hkai → hkai——route 即真相來源）；引用他域 view 用 sc_view() 全名
    public function get_view($template, $data = [])
    {
        
        // {route_path}>views>{template}.php（先正規化斜線：Windows 反斜線與正斜線統一後 explode 才能正確取 basename）
        $route_path = str_replace("\\", "/", $this->route["route_path"]);
        
        $template_path = $route_path . "/views/" . $template.".php";
        
        
        
        $view = sc_view($template_path, $data);
        $view->set_controller($this);
        return $view;
    }
    
    // 取得 app 內資源的 web 可達路徑（相對路徑 → /sc_contents/... URL）
    public function get_web_path($filepath)
    {
        // {route_path}>{filepath} → 磁碟路徑 → sc_to_web_path 轉 web URL
        $disk_path = $this->route["route_path"] . "/" . sc_trim($filepath, "/");
        return sc_to_web_path($disk_path);
    }
    
    // 取得本 app 的 storage 目錄（運行時資料：專案註冊表／chat 儲存——Laravel storage/ 對應）
    // 路徑：sc_storage>{app 名}（sc_app>hkai → sc_storage>hkai——與 app 同名）
    public function get_storage_dir()
    {
        // 設定有指定 app storage → 直接用（例：data——放 web root 外）
        $app_dir = sc_get_config("storage.app_dir");

        // 判定：有指定 → 回設定值
        if ($app_dir !== "") {
            return $app_dir;
        }

        // 先正規化斜線：Windows 反斜線與正斜線統一後 basename 才能正確取 app 名
        $route_path = str_replace("\\", "/", $this->route["route_path"]);
        $app_name   = basename($route_path);
        
        return SC_DIR_STORAGE . "/" . $app_name;
    }
    
    
    // 建立 JSON response（Laravel response()->json 對應）
    public function json($data, $status = null)
    {
        return sc_response()->json($data, $status);
    }
    
    // 建立重導向 response（Laravel redirect() 對應）
    public function redirect($url, $status = 302)
    {
        return sc_response()->redirect($url, $status);
    }
}
