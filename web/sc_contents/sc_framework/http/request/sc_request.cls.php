<?php
namespace sc\framework\http\request;

// sc_request：封裝 HTTP 請求（method／path／query／body／網域路徑剖析）——對齊 Laravel Request 概念
// capture 於建構時從 PHP 全域快照；各層統一由此讀取，不直接碰 $_SERVER

class sc_request_Class
{
    
    private static $instance = null;
    
    private $method = null;
    private $path;
    private $query;
    private $input;
    private $cookies;
    private $files;
    private $headers = array();
    private $ip = null;
    private $rawContent = null;
    private $jsonBody = null;
    
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
        $this->capture();
    }
    
    // 封裝請求（對應 Laravel Request::capture——從 PHP 全域快照；各層統一由此讀取）
    public function capture()
    {
        // HTTP 方法（預設 GET）
        $this->method = isset($_SERVER["REQUEST_METHOD"]) ? $_SERVER["REQUEST_METHOD"] : "GET";
        
        // 路徑（去掉 query 字串）
        $parsedUrl  = parse_url($_SERVER["REQUEST_URI"]);
        $this->path = isset($parsedUrl["path"]) ? $parsedUrl["path"] : "/";
        
        // query 參數
        $this->query = $_GET;
        
        // 表單 POST body（對應 Laravel request 類——$_POST）
        $this->input = $_POST;
        
        // cookies（$_COOKIE）
        $this->cookies = $_COOKIE;
        
        // 上傳檔案（$_FILES——對應 Laravel files 類）
        $this->files = $_FILES;
        
        // headers（從 $_SERVER 收集 HTTP_* 前綴）
        $this->headers = array();
        foreach ($_SERVER as $serverKey => $serverValue) {
            // 僅收集 HTTP_ 開頭（HTTP_HEADER_NAME → Header-Name）
            if (strpos($serverKey, "HTTP_") === 0) {
                $headerName = str_replace("_", "-", substr($serverKey, 5));
                $this->headers[$headerName] = $serverValue;
            }
        }
        
        // 客戶 IP
        $this->ip = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : null;
        
        // raw content（php://input 只能讀一次——capture 時讀好快取，json()/raw_content() 共用）
        $this->rawContent = file_get_contents("php://input");
        
        return $this;
    }
    
    // HTTP 方法
    public function method()
    {
        return $this->method;
    }
    
    // 路徑
    public function path()
    {
        return $this->path;
    }
    
    // query 參數（無 key 回全量）
    public function query($key = null, $default = null)
    {
        if ($key === null) {
            return $this->query;
        }
        return isset($this->query[$key]) ? $this->query[$key] : $default;
    }
    
    // 表單 POST body（無 key 回全量——對應 Laravel input()）
    public function input($key = null, $default = null)
    {
        if ($key === null) {
            return $this->input;
        }
        return isset($this->input[$key]) ? $this->input[$key] : $default;
    }
    
    // cookies（無 key 回全量——對應 Laravel cookie()）
    public function cookies($key = null, $default = null)
    {
        if ($key === null) {
            return $this->cookies;
        }
        return isset($this->cookies[$key]) ? $this->cookies[$key] : $default;
    }
    
    // 上傳檔案（無 key 回全量——對應 Laravel file()）
    public function files($key = null, $default = null)
    {
        if ($key === null) {
            return $this->files;
        }
        return isset($this->files[$key]) ? $this->files[$key] : $default;
    }
    
    // 單一 header（無 name 回全量）
    public function header($name = null, $default = null)
    {
        if ($name === null) {
            return $this->headers;
        }
        return isset($this->headers[$name]) ? $this->headers[$name] : $default;
    }
    
    // 客戶 IP（REMOTE_ADDR）
    public function ip()
    {
        return $this->ip;
    }
    
    // raw content（php://input 原始內容——capture 時已快取；非 JSON 場景用）
    public function raw_content()
    {
        return $this->rawContent;
    }
    
    // JSON body（解析 rawContent——已快取，不重複讀 php://input）
    public function json()
    {
        if ($this->jsonBody === null) {
            $decoded = json_decode($this->rawContent, true);
            // 非陣列時視為空
            if (!is_array($decoded)) {
                $decoded = [];
            }
            $this->jsonBody = $decoded;
        }
        return $this->jsonBody;
    }
}
