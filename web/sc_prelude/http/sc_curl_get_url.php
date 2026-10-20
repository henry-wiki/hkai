<?PHP

function sc_curl_get_url($url,$newParams)
{
	// 將現有參數解析到一個陣列中
	parse_str(parse_url($url, PHP_URL_QUERY), $existingParams);

	// 合併現有參數和新參數
	$params = array_merge($existingParams, $newParams);

	// 使用 http_build_query 函數將整個陣列轉換為查詢字串
	$queryString = http_build_query($params);

	// 將查詢字串添加到 URL 中
	$url = strtok($url, '?') . '?' . $queryString;

	return $url;
}
