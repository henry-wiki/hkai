<?php
namespace sc\framework\foundation\http\exception;

// == sc_http_exception：HTTP 錯誤例外（對應 Laravel Symfony\Component\HttpKernel\Exception\HttpException 家族） ==
// == 用途：404/403/413/429/503 等 HTTP 錯誤統一拋出——由 kernel handle 捕捉後渲染 errors/{code}.php ==
// == 取代：各 middleware 的 die() 直接輸出（錯誤處理分散）——統一走例外管道 ==

class sc_http_exception extends \Exception
{
    // HTTP 狀態碼（404/403/413/429/503）
    private $statusCode;
    
    public function __construct($statusCode, $message = "", $code = 0, $previous = null)
    {
        $this->statusCode = $statusCode;
        parent::__construct($message, $code, $previous);
    }
    
    // 取得狀態碼（kernel 渲染 errors/{code}.php 用）
    public function getStatusCode()
    {
        return $this->statusCode;
    }
}
