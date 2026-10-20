<?php
namespace sc\framework\foundation\http\middleware;

// == sc_trim_strings_middleware：輸入消毒（對應 Laravel TrimStrings） ==
// == 功用：自動移除輸入前後空白（例：使用者輸入 "  abc  " → "abc"，避免存到髒資料） ==
// == 範圍：$_GET / $_POST / $_COOKIE 的值（字串才處理） ==

class sc_trim_strings_middleware_Class
{
    private static $instance = null;
    
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
    
    // 管線執行點：對輸入陣列遞迴 trim
    public function handle()
    {
        $_GET = $this->trim_deep($_GET);
        $_POST = $this->trim_deep($_POST);
        $_COOKIE = $this->trim_deep($_COOKIE);
    }
    
    // 遞迴去除字串值的前後空白（陣列逐層處理，保留結構）
    private function trim_deep($inputArray)
    {
        if (!is_array($inputArray)) {
            return $inputArray;
        }
        
        $resultArray = array();
        foreach ($inputArray as $inputKey => $inputValue) {
            // 字串 → trim；陣列 → 遞迴
            if (is_string($inputValue)) {
                $resultArray[$inputKey] = sc_trim($inputValue);
            } elseif (is_array($inputValue)) {
                $resultArray[$inputKey] = $this->trim_deep($inputValue);
            } else {
                $resultArray[$inputKey] = $inputValue;
            }
        }
        
        return $resultArray;
    }
}
