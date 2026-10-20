<?php
namespace sc\framework\foundation\application;

// == sc_application：應用根物件（容器——對齊 Laravel Application / Yii2 Application） ==
// == 用法：sc_bootstrap/app.php → $app = sc_app(); 綁定 → return；入口：index.php（HTTP）／sc_console/app.php（CLI） ==

class sc_application_Class
{
    private static $instance = null;
    
    private $basePath;
    private $bindings  = [];   // 綁定表（singleton 概念）
    private $instances = [];  // 單例快取
    
    public static function getInstance($basePath = null)
    {
        if (!@isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class($basePath);
        }
        return self::$instance;
    }
    
    public function __construct($basePath = null)
    {
        // 未指定 → 用 SC_DOCUMENT_ROOT（CONFIG 層已定義）
        if ($basePath === null) {
            $basePath = SC_DOCUMENT_ROOT;
        }
        
        $this->basePath = $basePath;
    }
    
    // 應用根路徑（對應 Laravel base_path()）
    public function basePath()
    {
        return $this->basePath;
    }
    
    // 綁定單例（容器輕量版——對應 Laravel singleton()）
    // 用法：$app->singleton("kernel", function () { return sc_kernel(); });
    //       → 之後 $app->make("kernel") 每次回傳「同一個」實例（resolver 只執行一次）
    // 參數：$instance_id = 綁定的識別名（字串——make 時用同一名字領取）
    //       $resolver    = 閉包（「怎麼建立」——首次 make 時才執行——延遲）
    public function singleton($instance_id, $resolver)
    {
        $this->bindings[$instance_id] = $resolver;
    }
    
    // 解析綁定（單例快取——對應 Laravel make()）
    // 用法：$kernel = $app->make("kernel");（領取綁定的實例——未綁定則視為 class 名直接 new）
    // 參數：$instance_id = 綁定識別名 或 class 完整名
    public function make($instance_id)
    {
        // 已實例化 → 直接回傳（同一實例）
        if (@isset($this->instances[$instance_id])) {
            return $this->instances[$instance_id];
        }
        
        // 未綁定 → 立即錯誤（明確勝於隱藏）
        if (!@isset($this->bindings[$instance_id])) {
            sc_trigger_error("sc_application : unbound instance_id : [".$instance_id."]");
        }
        
        $resolver                    = $this->bindings[$instance_id];
        $this->instances[$instance_id] = $resolver();
        
        return $this->instances[$instance_id];
    }
}
