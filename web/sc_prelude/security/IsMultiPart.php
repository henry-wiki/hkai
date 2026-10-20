<?PHP

function IsMultiPart()
{
	// 判定：非 POST（或 CGI 未提供）→ 冇 CONTENT_TYPE，當空字串（唔讀未定義索引）
	$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

    return strpos($contentType, 'multipart/form-data') !== false;
}
