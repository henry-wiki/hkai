<?PHP


function sc_stripslashes_deep($value)
{
	// sc_stripslashes_deep 的作用是將 : \'sample 變回 'sample
	// 8.0 起 get_magic_quotes_gpc() 已移除（magic quotes 自 5.4 已有冇）→ 直接做深層 stripslashes
	
    $value = is_array($value) ? array_map(__FUNCTION__, $value) : stripslashes($value);
		
    return $value;
}
