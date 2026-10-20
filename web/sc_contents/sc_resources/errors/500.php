<?php
// == sc_resources/errors/500.php：500 錯誤頁（對應 Laravel resources/views/errors/500.blade.php） ==
// == 呼叫方式：http_response_code(500) 後 require 本檔（可先設 $errorPageMessage 顯示自訂訊息） ==
// == 設計：Bootstrap 樣式（與全站一致）；未設訊息 → 預設文字 ==

$errorPageMessage = @$errorPageMessage;
if ($errorPageMessage === null || $errorPageMessage === "") {
    $errorPageMessage = "Something went wrong on the server.";
}

$outputLines = [];
$outputLines[] = "<!DOCTYPE html>";
$outputLines[] = "<html lang='zh-Hant'>";
$outputLines[] = "<head>";
$outputLines[] = "<meta charset='UTF-8'>";
$outputLines[] = "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
$outputLines[] = "<title>500 - Internal Server Error | hkai</title>";
$outputLines[] = "<link rel='icon' href='data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 16 16\"><rect width=\"16\" height=\"16\" rx=\"3\" fill=\"%23dc3545\"/><path d=\"M4 6h8M4 9h5\" stroke=\"white\" stroke-width=\"1.5\" stroke-linecap=\"round\"/></svg>'>";
$outputLines[] = "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
$outputLines[] = "</head>";
$outputLines[] = "<body class='bg-light'>";
$outputLines[] = "<div class='container py-5'>";
$outputLines[] = "<div class='card shadow-sm mx-auto' style='max-width:520px;'>";
$outputLines[] = "<div class='card-body p-5 text-center'>";
$outputLines[] = "<h1 class='display-1 fw-bold text-danger mb-0'>500</h1>";
$outputLines[] = "<p class='lead mb-4'>Internal Server Error</p>";
$outputLines[] = "<p class='text-muted mb-4'>" . htmlspecialchars($errorPageMessage) . "</p>";
$outputLines[] = "<a href='/' class='btn btn-primary'>返回首頁</a>";
$outputLines[] = "</div>";
$outputLines[] = "</div>";
$outputLines[] = "</div>";
$outputLines[] = "</body>";
$outputLines[] = "</html>";
echo implode("\r\n", $outputLines);
