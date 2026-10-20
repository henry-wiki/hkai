<?php
namespace sc\framework\foundation\plugin;

// == sc_plugin：plugin 管理器＋plugin instance（對稱 sc_controller 模式） ==
// == 主 plugin（singleton）＝管理器：持有 plugin instance list，依 plugin_id 建立/取回（createPlugin） ==
// == plugin instance（每次 new）＝獨立 plugin：plugin_id＋base_path（plugin 根目錄） ==
// == 命名空間 sc\framework\foundation\plugin → autoload 對映 sc_framework/foundation/plugin/sc_plugin.cls.php ==

class sc_plugin_Class
{
    // 主 plugin 管理器單例（bridging sc_plugin() 無參數時回傳——持有 plugin instance list）
    private static $instance = null;
    
    // plugin instance list（plugin_id → instance——每請求生命週期；以 sc_plugin($plugin_id) 建立/取回）
    private $pluginInstances = [];
    
    // plugin 身份資料（instance 用）
    public $plugin_id;
    public $plugin_dir;
    
    // plugin 啟用狀態（public——getEnabledPlugins 過濾用；由 setSettings 同步 settings["enabled"]）
    public $enabled = false;
    
    // plugin settings（缺省值合併後——plugin class 自持）
    private $plugin_settings = [];
    
    private $request_actions = [];
    
    // 取得主 plugin 管理器（singleton——createPlugin 的入口）
    public static function getInstance($plugin_id = null, $plugin_dir = null, $plugin_settings = [])
    {
        // 主管理器單例（必須存回——否則每次 new，plugin instance list 永遠空）
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        $rootPlugin = self::$instance;
        
        // 有 plugin_id → 依 id 建立/取回 plugin instance
        if ($plugin_id) {
            return $rootPlugin->createPlugin($plugin_id, $plugin_dir, $plugin_settings);
        }
        
        return $rootPlugin;
    }
    
    public function __construct()
    {
    }
    
    public function add_request_action($action,$filepath)
    {
        $this->request_actions[$action] = $filepath;
        
        return $this;
    }
    
    public function get_request_actions()
    {
        return $this->request_actions;
    }
    
    // 依 plugin_id 建立/取回 plugin instance（存在直接回傳；不存在 new＋加入 list）
    public function createPlugin($plugin_id, $plugin_dir = null, $plugin_settings = [])
    {
        $plugin_id = sc_trim($plugin_id, "/");
        
        if (isset($this->pluginInstances[$plugin_id])) {
            return $this->pluginInstances[$plugin_id];
        }
        
        $pluginInstance = new self();
        $pluginInstance->setPluginData($plugin_id, $plugin_dir);
        $pluginInstance->setSettings($plugin_settings);
        
        $this->pluginInstances[$plugin_id] = $pluginInstance;
        return $pluginInstance;
    }
    
    // 設定 plugin 身份資料（createPlugin 建立時呼叫）
    public function setPluginData($plugin_id, $plugin_dir)
    {
        $this->plugin_id  = $plugin_id;
        $this->plugin_dir = $plugin_dir;
    }
    
    // 設定 plugin settings（缺省值合併——全可選；plugin class 自持）
    // 同步公開屬性 $enabled（getEnabledPlugins 過濾用——與 settings["enabled"] 單一來源）
    public function setSettings($plugin_settings = [])
    {
        $default_settings = [
            "name"        => $this->plugin_id,
            "version"     => "",
            "description" => "",
            "author"      => "",
            "settings"    => [],
            "enabled"     => false,
            "asset"       => [],
        ];
        $this->plugin_settings = array_replace($default_settings, $plugin_settings);
        
        // 同步公開屬性（settings["enabled"] 為唯一來源）
        $this->enabled = (bool)$this->plugin_settings["enabled"];
    }
    
    // 取得 plugin settings（合併後完整結構）
    public function getSettings()
    {
        return $this->plugin_settings;
    }
    
    // 取得 plugin id
    public function getPluginId()
    {
        return $this->plugin_id;
    }
    
    
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
    }
    
    // 取得 plugin 宣告的 assets（磁碟路徑 → web path 清單；無宣告回空陣列）
    public function get_assets()
    {
        $assetList = $this->plugin_settings["asset"];
        if (!is_array($assetList)) {
            return [];
        }
        
        // 磁碟路徑 → web path（register_assets 的 src 原樣輸出——需 URL）
        $webPathList = [];
        foreach ($assetList as $assetPath) {
            $webPathList[] = sc_to_web_path($assetPath);
        }
        return $webPathList;
    }
    
    
    // 是否已註冊該 plugin
    public function has_plugin($plugin_id)
    {
        return isset($this->pluginInstances[sc_trim($plugin_id, "/")]);
    }
    
    // 取得 plugin instance list（全部——管理器查詢用）
    public function getPlugins()
    {
        return $this->pluginInstances;
    }
}
