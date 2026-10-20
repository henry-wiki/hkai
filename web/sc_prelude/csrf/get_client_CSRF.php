<?php


function get_client_CSRF()
{
	// 判定：表單 POST 未帶 CSRF_Token（如 JSON 請求 $_POST 為空）→ 唔讀未定義索引
	$csrf = isset($_POST["CSRF_Token"]) ? $_POST["CSRF_Token"] : null;
	
	if(@$csrf)
		return $csrf;
	
	$csrf = get_request_header("X-CSRF-Token");
	
	if(@$csrf)
		return $csrf;
	
	return null;
}
