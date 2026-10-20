<?PHP

function sc_html_encode($obj)
{
	if(@is_array($obj))
	{
		foreach($obj as &$item)
		{
			if(is_string($item))
			{
				$item = sc_escape_special_chars($item);
			}
			else if(is_array($item))
			{
				$item = sc_html_encode($item);
			}
		}unset($item);
	}
	else if(is_string($obj))
	{
		$obj = sc_escape_special_chars($obj);
	}
	return $obj;
}
