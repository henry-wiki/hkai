<?php
namespace sc\framework\foundation\http\kernel;

// == sc_kernel：HTTP Kernel（引導 + 分發合體——對應 Laravel Foundation\Http\Kernel） ==
// == handle() 唯一入口：bootstrap（引導）→ middleware 鏈 → 路由匹配 → 分派 ==

class sc_kernel_Class
{
    private static $instance = null;
    
    // 管線：全局 middleware（對應 Laravel $middleware 屬性——class 名直接寫死，不需外部檔掛載）
    // 執行：所有請求都跑（trust proxies → CORS → 維護 → POST 大小 → trim → 空字串轉 null）
    private $middlewares = [
        \sc\framework\foundation\http\middleware\sc_trust_proxies_middleware_Class::class,
        \sc\framework\foundation\http\middleware\sc_cors_middleware_Class::class,
        \sc\framework\foundation\http\middleware\sc_maintenance_middleware_Class::class,
        \sc\framework\foundation\http\middleware\sc_validate_post_size_middleware_Class::class,
        \sc\framework\foundation\http\middleware\sc_trim_strings_middleware_Class::class,
        \sc\framework\foundation\http\middleware\sc_convert_empty_strings_middleware_Class::class,
    ];
    
    // 分組 middleware（對應 Laravel $middlewareGroups：app/api 各組自己的清單）
    // 執行時 = 全局 + 該 route 的組（路由帶 group 參數即歸組）
    private $middlewareGroups = [
        "app" => [
            \sc\framework\foundation\http\middleware\sc_session_middleware_Class::class,
            \sc\framework\foundation\http\middleware\sc_csrf_middleware_Class::class,
        ],
        "request" => [
            \sc\framework\foundation\http\middleware\sc_session_middleware_Class::class,
            \sc\framework\foundation\http\middleware\sc_csrf_middleware_Class::class,
        ],
        "api" => [
            // 依 IP 限流（Laravel throttle:api 對應——默認 60 次/分鐘）
            \sc\framework\foundation\http\middleware\sc_throttle_middleware_Class::class,
        ],
    ];
    
    // 終結回呼清單（response 送出後執行——對應 Laravel app->terminating）
    private $terminatingCallbacks = [];
    
    // 可插拔引導清單（對應 Laravel Kernel::$bootstrappers——依序執行；加步驟 = 陣列加一行）
    private $bootstrappers = [
        \sc\framework\foundation\bootstrap\sc_boot_load_framework_Class::class,
        \sc\framework\foundation\bootstrap\sc_boot_register_error_handler_Class::class,
        \sc\framework\foundation\bootstrap\sc_boot_load_routes_Class::class,
    ];
    
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
    
    // 掛載 middleware（全局——所有請求都跑）
    public function add_middleware($middleware)
    {
        $this->middlewares[] = $middleware;
    }
    
    // 掛載 middleware 進分組（對應 Laravel：$middlewareGroups['app'] = [...]）
    // 用法：$kernel->add_middleware_to_group("app", sc_csrf_middleware_Class::getInstance());
    // 執行時機：該 route 屬於此組時（歸組接線——未來：add_route 帶組別 + handle 依組執行）
    public function add_middleware_to_group($group, $middleware)
    {
        // 組不存在 → 建組
        if (!isset($this->middlewareGroups[$group])) {
            $this->middlewareGroups[$group] = [];
        }
        
        $this->middlewareGroups[$group][] = $middleware;
    }
    
    // 取得合法 group 清單（路由允許的組——add_route 驗證 group 存在用；Laravel $middlewareGroups 對應）
    public function get_middleware_groups()
    {
        return array_keys($this->middlewareGroups);
    }
    
    // 檢查 group 是否合法（路由註冊時驗證——不存在的組 → 拒絕註冊）
    public function has_middleware_group($group)
    {
        return isset($this->middlewareGroups[$group]);
    }
    
    // 唯一入口（Laravel Kernel handle() 對應：引導 → 分發 → 回傳 Response 物件）
    public function handle($request = null)
    {
        // ① 引導（載入元件/註冊/初始化；Laravel Kernel bootstrap 同構）
        // 必須在 request 操作之前：sc_request() 等 bridging 函數依賴此步載入
        $this->bootstrap();
        
        // ② 未傳入 Request → 自行 capture（對齊 Laravel：$request = Request::capture()）
        if ($request === null) {
            $request = sc_request()->capture();
        }
        
        // ③ 統一例外捕捉（對應 Laravel Kernel::handle 的 try/catch——404/403/413/429/503＋未預期錯誤 全走此管道）
        try {
            $response = $this->dispatch($request);
        } catch (\sc\framework\foundation\http\exception\sc_http_exception $httpException) {
            // HTTP 錯誤 → 統一渲染 errors/{code}.php（Laravel ExceptionHandler::render 對應）
            $response = $this->render_http_error($httpException);
        } catch (\Throwable $uncaughtThrowable) {
            // 未預期錯誤（DB 鎖／程式錯等）→ 記 log ＋ 統一 500 頁（免成 PHP fatal 裸輸出）
            $uncaughtSummary = "Uncaught " . get_class($uncaughtThrowable) . ": " . $uncaughtThrowable->getMessage()
                . " in " . $uncaughtThrowable->getFile() . ":" . $uncaughtThrowable->getLine()
                . "\n" . $uncaughtThrowable->getTraceAsString();
            error_log($uncaughtSummary);
            $response = $this->render_http_error(new \sc\framework\foundation\http\exception\sc_http_exception(500, $uncaughtThrowable->getMessage()));
        }
        
        return $response;
    }
    
    // 分發（引導後的核心流程——Laravel sendRequestThroughRouter 對應）
    // 流程：全局 middleware → 匹配 → 分組 middleware → Router 全包（執行+轉換）
    private function dispatch($request)
    {
        // ② 管線：全局 middleware 依序執行（Laravel $middleware 屬性對應——所有請求必過的統一安檢門）
        $this->run_middlewares();
        
        // ③ 匹配（唯一一次——未命中由 kernel 統一 throw 404）
        $route = sc_router()->get_route($request);
        
        // 未命中 → 提早 throw 404（統一 404 出口——render_http_error 在此 catch）
        if ($route === null) {
            throw new \sc\framework\foundation\http\exception\sc_http_exception(
                404,
                "The page you are looking for could not be found."
            );
        }
        
        // ④ 分組 middleware（依 route 的組執行——run_group_middlewares 內部已有「無組則跳過」防護）
        // 例：api 組（throttle 等）、app 組（CSRF 等）——route 無組則跳過
        $group = isset($route["group"]) ? $route["group"] : null;
        $this->run_group_middlewares($group);
        
        // ⑤ 進 Router（Laravel dispatchToRouter 對應——傳入已匹配結果，避免重複匹配；執行+轉換全包）
        return sc_router()->dispatch($request, $route);
    }
    
    // 渲染 HTTP 錯誤（Laravel ExceptionHandler::render 對應——errors/{code}.php，缺檔用 404 兜底）
    private function render_http_error($httpException)
    {
        $statusCode = $httpException->getStatusCode();
        $errorPage  = SC_DIR_RESOURCES . "/errors/" . $statusCode . ".php";
        
        // 指定 code 頁不存在 → 用 404 頁兜底（避免 500 顯示裸錯誤）
        if (!@file_exists($errorPage)) {
            $errorPage = SC_DIR_RESOURCES . "/errors/404.php";
        }
        
        http_response_code($statusCode);
        $errorPageMessage = $httpException->getMessage();
        require $errorPage;
        return sc_response()->body("");
    }
    
    // 引導（對應 Laravel Kernel::bootstrap() = bootstrapWith([...])——依 $bootstrappers 陣列依序執行）
    // 對應 Laravel：每個 bootstrapper 一個職責（LoadEnvironmentVariables≈load_framework、
    //              HandleExceptions≈register_error_handler、RegisterProviders≈load_routes）
    public function bootstrap()
    {
        // 依序執行每個 bootstrapper（各 bootstrapper 內為其單一職責）
        foreach ($this->bootstrappers as $bootstrapperClass) {
            $bootstrapper = new $bootstrapperClass();
            $bootstrapper->bootstrap();
        }
    }
    
    // 實例化 middleware（class 名 → 優先 getInstance 單例，無則 new；物件 → 直接用——相容 add_middleware 傳物件）
    private function instantiate_middleware($middleware)
    {
        // class 名字串 → 優先走單例（對齊 bridging 慣例），無 getInstance 才 new
        if (is_string($middleware)) {
            if (method_exists($middleware, "getInstance")) {
                return call_user_func([$middleware, "getInstance"]);
            }
            return new $middleware();
        }
        // 已是物件 → 直接使用
        return $middleware;
    }
    
    // 管線執行（全局 middleware 依序）
    private function run_middlewares()
    {
        foreach ($this->middlewares as $middleware) {
            $middlewareInstance = $this->instantiate_middleware($middleware);
            $middlewareInstance->handle();
        }
    }
    
    // 分組管線執行（對應 Laravel：route 的組 middleware——歸組接線後由 handle 呼叫）
    // 用法：$this->run_group_middlewares("app");（該請求的 route 屬 app 組時）
    private function run_group_middlewares($group)
    {
        $groupMiddlewares = (isset($this->middlewareGroups[$group]) ? $this->middlewareGroups[$group] : null);
        
        // 組不存在 → 跳過
        if (!@$groupMiddlewares) {
            return;
        }
        
        foreach ($groupMiddlewares as $middleware) {
            $middlewareInstance = $this->instantiate_middleware($middleware);
            $middlewareInstance->handle();
        }
    }
    
    // 註冊終結回呼（對應 Laravel app->terminating）
    // 用法：$kernel->register_terminating(function ($request, $response) { /* 寫日誌/關連線 */ });
    // 執行時機：response send() 之後（使用者已看到頁面——後台慢慢收尾）
    public function register_terminating($callback)
    {
        $this->terminatingCallbacks[] = $callback;
    }
    
    // 送客後打掃（對應 Laravel Kernel::terminate——index.php 最後一行呼叫）
    // 執行：依序執行終結回呼（route log 寫檔／session 寫回／日誌——由 bootstrap/debug.php 等選擇性註冊）
    public function terminate($request = null, $response = null)
    {
        foreach ($this->terminatingCallbacks as $callback) {
            $callback($request, $response);
        }
    }
}
