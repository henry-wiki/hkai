<?PHP

function IsAjax()
{
	// 判定：請求冇帶 X-Requested-With（8.0 起直接讀未設索引＝Warning）→ 唔係 Ajax
	if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']))
	{
		return false;
	}

	return strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}
