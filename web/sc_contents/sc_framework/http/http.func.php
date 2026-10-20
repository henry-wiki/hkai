<?php

function sc_get_request_uri()
{
    if(IsPost() && isset($_POST["request_uri"]))
    {
        return $_POST["request_uri"];
    }
    else 
    {
        // 判定：非 Web 環境冇 REQUEST_URI → 空字串
        return isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "";
    }
    
}
