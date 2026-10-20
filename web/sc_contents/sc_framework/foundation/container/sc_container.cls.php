<?php
namespace sc\framework\foundation\container;

// == sc_container：依賴注入容器（對應 Laravel Illuminate\Container\Container——走過場版含自動注入） ==
// == 作用：統一「建立與領取」服務——解耦（不知道實作）/ 自動注入依賴 / 單例管理 / 可替換 ==
// == 對應 Laravel：bind / singleton / instance / make / resolve（Reflection 自動注入） ==

class sc_container_Class
{
    private static $instance = null;
    
    // 綁定表：abstract → array("concrete" => 閉包或 class 名, "shared" => 是否單例)
    private $bindings = array();
    
    // 已解析實例（singleton 快取 + instance 直接給的）
    private $instances = array();
    
    public static function getInstance()
    {
        if (!@isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }
    
    public function __construct()
    {
    }
    
    // === bind：綁定（每次 make 都是新實例） ===
    // 對應 Laravel：bind($abstract, $concrete)
    // 例子：「綁定『訂單處理器』——每次領都是新的一個（不共享狀態，各處理器獨立）」
    public function bind($abstract, $concrete)
    {
        $this->bindings[$abstract] = array(
            "concrete" => $concrete,
            "shared"   => false,
        );
    }
    
    // === singleton：綁定（全請求同一個實例） ===
    // 對應 Laravel：singleton($abstract, $concrete)
    // 例子：「綁定『設定』——全請求只用同一份（共享狀態，改了大家都看到）」
    public function singleton($abstract, $concrete)
    {
        $this->bindings[$abstract] = array(
            "concrete" => $concrete,
            "shared"   => true,
        );
    }
    
    // === instance：直接給實例（繞過解析——最高優先） ===
    // 對應 Laravel：instance($abstract, $instance)
    // 例子：「測試時把真 Mailer 換成假 Mailer（Laravel 的 Mail::fake() 就是這招）」
    public function instance($abstract, $instance)
    {
        $this->instances[$abstract] = $instance;
    }
    
    // === make：解析（領取服務） ===
    // 對應 Laravel：make($abstract, $parameters)
    // 例子：「容器，給我『訂單處理器』」——容器依綁定表決定給哪個實作（呼叫端不知道實作）
    public function make($abstract, $parameters = array())
    {
        // 已直接給實例（instance）→ 回傳（最高優先——測試替換機制）
        if (@isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }
        
        // 未綁定 → 視為 class 名直接解析（自動注入）
        $binding = (isset($this->bindings[$abstract]) ? $this->bindings[$abstract] : null);
        if (!$binding) {
            return $this->resolve_class($abstract, $parameters);
        }
        
        $concrete = $binding["concrete"];
        $isShared = $binding["shared"];
        
        // 閉包綁定 → 呼叫（傳入 container 自身——Laravel 閉包接收 $app）
        if ($concrete instanceof \Closure) {
            $resolved = $concrete($this);
        } else {
            // class 名綁定 → 依賴注入解析
            $resolved = $this->resolve_class($concrete, $parameters);
        }
        
        // 單例綁定 → 快取（下次 make 回傳同一個）
        if ($isShared) {
            $this->instances[$abstract] = $resolved;
        }
        
        return $resolved;
    }
    
    // === resolve_class：Reflection 依賴注入解析（Laravel 精華——走過場實作版） ===
    // 對應 Laravel：resolveClass（容器內部）
    // 流程：檢查建構參數 → class 型別遞迴 make（依賴自動注入）→ 預設值/null → newInstanceArgs
    // 例子：「make(UserController) → 容器看它的建構需要 Mailer → 自動遞迴 make(Mailer) → new UserController($mailer)」
    private function resolve_class($className, $parameters)
    {
        $reflectionClass = new \ReflectionClass($className);
        
        // 不可實例化（interface/abstract）→ 錯誤（明確勝於隱藏）
        if (!$reflectionClass->isInstantiable()) {
            sc_trigger_error("sc_container : not instantiable : [".$className."]");
        }
        
        $constructor = $reflectionClass->getConstructor();
        
        // 無建構子 → 直接 new
        if ($constructor === null) {
            return new $className();
        }
        
        $resolvedParams = array();
        $constructorParams = $constructor->getParameters();
        
        foreach ($constructorParams as $paramIndex => $param) {
            // 呼叫端指定參數 → 優先使用
            if (array_key_exists($paramIndex, $parameters)) {
                $resolvedParams[] = $parameters[$paramIndex];
                continue;
            }
            
            $paramType = $param->getType();
            
            // 有 class 型別 → 遞迴 make（依賴自動注入——Container 的核心價值）
            if ($paramType && !$paramType->isBuiltin()) {
                $resolvedParams[] = $this->make($paramType->getName());
                continue;
            }
            
            // 有預設值 → 用預設
            if ($param->isDefaultValueAvailable()) {
                $resolvedParams[] = $param->getDefaultValue();
                continue;
            }
            
            // 可空參數 → null
            if ($param->allowsNull()) {
                $resolvedParams[] = null;
                continue;
            }
            
            // 無法解析 → 錯誤（明確勝於隱藏）
            sc_trigger_error("sc_container : unresolvable param : [".$param->getName()."]");
        }
        
        return $reflectionClass->newInstanceArgs($resolvedParams);
    }
}
