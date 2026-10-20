<?php
namespace sc\framework\foundation\http\middleware;

// == sc_convert_empty_strings_middleware：空字串轉 null（對應 Laravel ConvertEmptyStringsToNull） ==
// == 功用：空字串輸入統一轉 null（例：表單沒填的欄位存 "" 還是 null——統一 null 較乾淨） ==

class sc_convert_empty_strings_middleware_Class
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
    
    // 管線執行點：對輸入陣列遞迴轉換
    public function handle()
    {
        $_GET = $this->convert_deep($_GET);
        $_POST = $this->convert_deep($_POST);
        $_COOKIE = $this->convert_deep($_COOKIE);
    }
    
    // 遞迴把空字串轉 null（陣列逐層處理，保留結構）
    private function convert_deep($inputArray)
    {
        if (!is_array($inputArray)) {
            return $inputArray;
        }
        
        $resultArray = array();
        foreach ($inputArray as $inputKey => $inputValue) {
            // 空字串 → null；陣列 → 遞迴；其餘原樣
            if ($inputValue === "") {
                $resultArray[$inputKey] = null;
            } elseif (is_array($inputValue)) {
                $resultArray[$inputKey] = $this->convert_deep($inputValue);
            } else {
                $resultArray[$inputKey] = $inputValue;
            }
        }
        
        return $resultArray;
    }
}
