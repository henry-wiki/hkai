<?PHP

/**
 * 取得完整 backtrace 的「檔案:行號」清單（不含自身）
 *
 * @return array 每項為 "檔案:行號"
 */
function sc_get_backtrace_data()
{
	$debug_backtrace = debug_backtrace();

	// == shift first one
	array_shift($debug_backtrace);

	$backtrace_data = array();

	if(@count($debug_backtrace))
	{
		foreach($debug_backtrace as $item)
		{

			$backtrace_data[] = $item["file"] . ":".$item["line"];
		}
	}

	return @$backtrace_data;
}
