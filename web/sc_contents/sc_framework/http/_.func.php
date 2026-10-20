<?php

// == http 對外 API（bridging：回傳 class 單例） ==
// == 結構定型：http/ 域 = Laravel Illuminate\Http 對應（request/response 元件） ==
// == kernel/middleware 已遷 foundation/http/（Laravel Foundation\Http 對應——引導+分發屬 Foundation 層） ==
// == 即：元件（Illuminate\Http）與指揮（Foundation\Http）分層——對齊 Laravel 組織 ==

use sc\framework\http\request;
use sc\framework\http\response;


function sc_request()
{    
    return request\sc_request_Class::getInstance();
}

function sc_response()
{
    return response\sc_response_Class::getInstance();
}
