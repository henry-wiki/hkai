<?php
namespace sc\framework\routing\router;

use sc\framework;

class sc_router_Class
{
    private static $instance = null;
    
    // 單一路由表（method → 固定 path → 路由記錄）
    private $routes = [];
    
    // 路由註冊 log（debug 用——誰在何處註冊了什麼路由；status：ok/fail）
    private $route_logs = [];
    
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
    
    
    // 註冊路由（單一表；route_path 依組別語意：web = app 目錄（執行慣例檔 index.php）、api = handler 檔）
    // $group：middleware 分組（"app"/"api"——必填；對應 Laravel routes 歸組；執行時依組掛 middleware）
    // $method：HTTP 方法（"GET"/"POST"/"PUT" 等——由 register_routes() 的 options 級決定）
    // $route_data：路由固定資料（執行時以 $route_data 提供給被 include 的檔）
    // url_path：固定結構 URL path（精確比對、無正則；未命中 → 404）
    // route_path：一律絕對路徑（disk path）——web = app 目錄、api = handler 檔（宣告者自給，router 不組前綴）
    // 用法：add_route("app", "GET", "/", SC_DIR_APP . "/hkai")——app 組傳 app 目錄絕對路徑
    //       add_route("api", "GET", "apis/status", SC_DIR_CONTENTS . "/sc_api/handlers/status_get", [])
    // 注意：路由宣告一律用 register_routes()——add_route 供 register_routes()/register 檔內部使用
    // group 驗證：不存在的組（kernel middlewareGroups 無此組）→ 忽略註冊＋記失敗 log
    // route_path：宣告者自給絕對路徑（web = app 目錄、api = handler 檔）；寫錯由執行時 require 自然 Error
    private function add_route($group, $method, $url_path, $route_path, $route_data = [])
    {
        $method = strtolower($method);
        
        // url_path 正規化：固定 path 去兩側斜線（"/" → ""、"/abc" → "abc"）——精確比對 key
        $url_path = sc_trim($url_path, "/");
        
        // group 驗證：kernel 無此 middleware 組 → 拒絕註冊（記失敗 log）
        if (!sc_kernel()->has_middleware_group($group)) {
            $this->route_logs[] = [
                "group"           => $group,
                "method"          => $method,
                "status"          => "fail",
                "url_path"        => $url_path,
                "route_path"      => $route_path,
                "reason"          => "unknown middleware group: [" . $group . "]",
                "registered_from" => sc_get_backtrace_pathinfo(2),
                "time"            => date("Y-m-d H:i:s"),
            ];
            return;
        }
        
        // 記錄改為含 url_path 的清單（method → list——前綴匹配需 for loop 掃描，hash 表無法）
        $this->routes[$method][] = [
            "url_path"   => $url_path,
            "route_path" => $route_path,
            "group"      => $group,
            "route_data" => $route_data,
        ];
        
        // 記錄註冊 log（成功）
        $this->route_logs[] = [
            "group"           => $group,
            "method"          => $method,
            "status"          => "ok",
            "url_path"        => $url_path,
            "route_path"      => $route_path,
            "registered_from" => sc_get_backtrace_pathinfo(2),
            "time"            => date("Y-m-d H:i:s"),
        ];
    }
    
    // 讀取路由 log（可依 group 篩選——傳 "api" 只回 api 組；status 篩選：ok/fail）
    public function get_route_logs($group = null, $status = null)
    {
        // 無篩選 → 全數回傳
        if ($group === null && $status === null) {
            return $this->route_logs;
        }
        
        $filtered_logs = [];
        foreach ($this->route_logs as $route_log) {
            // 依 group 篩選（指定才比對）
            if ($group !== null && $route_log["group"] !== $group) {
                continue;
            }
            // 依 status 篩選（指定才比對）
            if ($status !== null && $route_log["status"] !== $status) {
                continue;
            }
            $filtered_logs[] = $route_log;
        }
        return $filtered_logs;
    }
    
    // 寫入路由 log 檔（debug 工具——每請求自動由 kernel terminate 呼叫；亦可手動）
    // 用法：write_route_logs()——全量；write_route_logs("fail")——只寫失敗
    // 路徑：SC_LOG_DIR/route_logs.{Ymd}.log（每日一檔——追加保留多請求軌跡）
    // 格式：文字可讀（請求標頭＋每筆一行）；回傳寫入位元組數
    public function write_route_logs($status = null, $group = null)
    {
        // 取得（可篩選）log 清單
        $logsToWrite = $this->get_route_logs($group, $status);
        
        // 確保 log 目錄存在（SC_LOG_DIR 可能尚未建立）
        if (!is_dir(SC_LOG_DIR)) {
            mkdir(SC_LOG_DIR, 0777, true);
        }
        
        // 每日一檔（SC_LOG_DIR 已定義——_log/）
        $logFile = SC_LOG_DIR . "/route_logs." . date("Ymd") . ".log";
        
        // 請求標頭（含解析路徑——sc_request 已 capture）
        $request = sc_request();
        
        $outputLines   = [];
        $outputLines[] = "[" . date("Y-m-d H:i:s") . "] === REQUEST " . $request->method() . " " . $request->path() . " ===";
        
        foreach ($logsToWrite as $route_log) {
            $logLine = "[" . (isset($route_log["time"]) ? $route_log["time"] : null) . "] [" . $route_log["status"] . "] " . $route_log["method"];
            
            // 匹配鍵（registered：宣告的固定 url_path）或請求 path（matched/missed：request_url_path）
            if (isset($route_log["url_path"])) {
                $logLine .= " " . $route_log["url_path"];
            } elseif (isset($route_log["request_url_path"])) {
                $logLine .= " " . $route_log["request_url_path"];
            }
            
            // route_target（執行目標：app 目錄或 handler 檔）
            if (isset($route_log["route_path"])) {
                $logLine .= " → " . $route_log["route_path"];
            }
            
            // 組別
            if (isset($route_log["group"])) {
                $logLine .= " [" . $route_log["group"] . "]";
            }
            
            // 失敗原因（fail）
            if (isset($route_log["reason"])) {
                $logLine .= " (" . $route_log["reason"] . ")";
            }
            
            // 註冊來源（registered/fail）
            if (isset($route_log["registered_from"])) {
                $logLine .= " from:" . $route_log["registered_from"];
            }
            
            $outputLines[] = $logLine;
        }
        
        // 追加寫入（保留多請求軌跡）
        return file_put_contents($logFile, implode("\r\n", $outputLines) . "\r\n", FILE_APPEND | LOCK_EX);
    }
    
    // 取得匹配路由（依 request；回傳 [route_path, url_path, request_url_path, group, route_data] 或 null）
    // 前綴匹配模型：收集所有前綴命中 → 最長 url_path 勝出（同長度保留先宣告者）
    // 段邊界：docs 匹配 docs／docs/xxx，不匹配 docsx；"" 根路由匹配一切（任何 path 的前綴）
    public function get_route($request)
    {
        $method           = strtolower($request->method());
        $request_url_path = sc_trim($request->path(), "/");
        
        $routes_for_method = (isset($this->routes[$method]) ? $this->routes[$method] : null);
        
        // 該 method 無任何註冊路由 → 未命中（開發者設定問題）
        if (!@$routes_for_method) {
            $this->route_logs[] = [
                "method"           => $method,
                "status"           => "fail",
                "request_url_path" => $request_url_path,
                "reason"           => "no route registered for method",
                "time"             => date("Y-m-d H:i:s"),
            ];
            return null;
        }
        
        // ① 收集所有前綴匹配候選（宣告順序保留）
        $matched_routes = [];
        foreach ($routes_for_method as $route_item) {
            // 前綴匹配才收集
            if ($this->is_path_match($route_item["url_path"], $request_url_path)) {
                $matched_routes[] = $route_item;
            }
        }
        
        // ② 最長 url_path 勝出（手動掃描——同長度保留先宣告者；避免 usort 非穩定）
        $best_route  = null;
        $best_length = -1;
        foreach ($matched_routes as $matched_route) {
            $route_length = strlen($matched_route["url_path"]);
            // 更長才取代（同長度維持宣告順序）
            if ($route_length > $best_length) {
                $best_route  = $matched_route;
                $best_length = $route_length;
            }
        }
        
        // ③ 無任何匹配 → 記 missed log 回 null（kernel 404）
        if ($best_route === null) {
            $this->route_logs[] = [
                "method"           => $method,
                "status"           => "fail",
                "request_url_path" => $request_url_path,
                "reason"           => "no route matched",
                "time"             => date("Y-m-d H:i:s"),
            ];
            return null;
        }
        
        // 命中 → 記 ok log 並回傳（附原始 path——app 自行解析動態段）
        $best_route["request_url_path"] = $request_url_path;
        
        $this->route_logs[] = [
            "group"            => $best_route["group"],
            "method"           => $method,
            "status"           => "ok",
            "request_url_path" => $request_url_path,
            "route_path"       => $best_route["route_path"],
            "time"             => date("Y-m-d H:i:s"),
        ];
        
        return $best_route;
    }
    
    // 前綴匹配（段邊界：docs 匹配 docs／docs/xxx，不匹配 docsx；"" 根路由匹配一切）
    private function is_path_match($routeUrlPath, $requestUrlPath)
    {
        // 根路由：任何 path 皆匹配
        if ($routeUrlPath === "") {
            return true;
        }
        
        // 完全相同
        if ($requestUrlPath === $routeUrlPath) {
            return true;
        }
        
        // 段邊界前綴（url_path/ 開頭）
        return strpos($requestUrlPath, $routeUrlPath . "/") === 0;
    }
    
    // 分發（對應 Laravel Router::dispatch——執行+轉換；匹配由 kernel 完成並傳入）
    // $route：必填——kernel 已 get_route 並保證非 null（未命中由 kernel 提早 throw 404）
    // 註：無 ob——route_path 必須 return（Laravel 同款；SSE 等直接輸出型 route_path 自行管理輸出）
    public function dispatch($request, $route)
    {
        // 執行 route_path → 原始結果（include 檔——一律 return）
        $result = $this->run_route($route);
        
        // 統一轉 Response（Laravel prepareResponse 對應）
        return $this->prepare_response($result);
    }
    
    // 執行 route_path（對應 Laravel Route::run）
    // route_path 一律 = 目錄（app 目錄／handler 目錄）——執行慣例檔 {dir}/index.php 由 controller 組
    // 執行語境：每次建立新 controller instance（非共用）——include 檔內 $this = controller（統一語境，api/web 皆同）
    // 動態解析：router 不處理 pattern——原始 path 以 request_path 提供，由 app/handler 自行解析
    private function run_route($route)
    {
        $route_path = $route["route_path"];
        
        // 統一：建立語境 controller（dirPath = route_path 目錄）＋執行慣例檔（controller 內組 index.php）
        $controller = sc_controller($route);
        
        return $controller->run();
    }
    
    // 統一轉 Response（對應 Laravel Router::prepareResponse——四種結果處理）
    // Response 物件 → 原樣回傳；array → JSON；View 物件 → 延遲渲染；string/null → (string) 強轉 body
    // 註：無 __toString 的 object → PHP 原生 Error（Laravel 同款——不設防呆分支）
    private function prepare_response($result)
    {
        // response：需自訂 status／header（如 502 錯誤）時用 sc_response()->json(...)／body(...) return——原樣送出
        if ($result instanceof \sc\framework\http\response\sc_response_Class) {
            return $result;
        }
        
        // array → JSON：正常 API 資料直接 return array——自動轉 JSON＋200（免手動 json_encode）
        if (is_array($result)) {
            return sc_response()->json($result);
        }
        
        // View 物件 → 延遲渲染（Laravel view response 對應）
        if ($result instanceof \sc\framework\view\sc_view_Class) {
            return sc_response()->body($result->render());
        }
        
        // string/null → 強制轉字串作 body（null → "" 空 200；對齊 Laravel toResponse 尾段 (string) 強轉）
        return sc_response()->body((string) $result);
    }
    
    // 註冊路由清單（選項 + 自訂路由清單——每條 [url_path, route_path] 或 [url_path, route_path, route_data]）
    // 用法：sc_router()->register_routes(["group" => "app"], [
    //            ["/", SC_DIR_APP . "/hkai"],
    //            ["chats/main", SC_DIR_APP . "/sc_ai_chats"],
    //            ["apis/status", SC_DIR_CONTENTS . "/sc_api/handlers/status_get"],
    //            ["projects/p1/conv42", SC_DIR_APP . "/hkai"],   // 固定結構 path（精確比對——未命中 → 404）
    //            ["apis/agent", SC_DIR_CONTENTS . "/sc_api/handlers/agent_get", ["format" => "html"]],   // 第三元素 = route_data
    //        ]);
    // 選項：group 必填（缺失 → null → add_route 驗證失敗記 failed log）；method 可選（預設 get——組內全部同 method）
    // url_path：固定結構 URL path（精確比對、無正則；未命中 → 404）
    // route_path 依組別語意（一律絕對路徑）：web = app 目錄（執行慣例檔 index.php）；api = handler 檔
    // route_data：路由固定資料（執行時以 $route_data 提供給被 include 的檔）
    public function register_routes($options, $custom_routes)
    {
        // 選項解析：group 必填、method 可選（預設 get）
        $group  = (isset($options["group"]) ? $options["group"] : null);
        $method = isset($options["method"]) ? strtolower($options["method"]) : "get";
        
        // 逐條註冊（每條 [url_path, route_path]；第三元素可選——route_data 固定資料）
        foreach ($custom_routes as $custom_route_item) {
            $url_path   = $custom_route_item[0];
            $route_path = $custom_route_item[1];
            $route_data = isset($custom_route_item[2]) ? $custom_route_item[2] : [];
            $this->add_route($group, $method, $url_path, $route_path, $route_data);
        }
    }
    
    // ==================================================================================================
}
