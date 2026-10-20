<?PHP

/**
 * 取得指定層數呼叫點的「檔案:行號」
 *
 * @param int   $no_of_index  回溯層數（0 = 直接呼叫者所在行）
 * @param mixed &$debug_item  依引用回傳該層 debug_backtrace item
 * @return string 檔案:行號
 */
function sc_get_backtrace_pathinfo($no_of_index=0,&$debug_item="")
{
	// 依索引取層（limit = 目標層 + 1——確保目標層存在於回傳清單）
	$debug_backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT,$no_of_index+1);

	// 層數不足（目標層超出實際深度）→ 取最後一層（最外層呼叫者）
	if (!isset($debug_backtrace[$no_of_index])) {
		$no_of_index = count($debug_backtrace) - 1;
	}

	$debug_item = $debug_backtrace[$no_of_index];

	$debug_file = sc_directory()->get_absolute_path($debug_item["file"]);


	$debug_str = $debug_file . ":".$debug_item["line"];

	return $debug_str;
}
