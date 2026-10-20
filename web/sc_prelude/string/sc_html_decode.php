<?PHP





function sc_html_decode($obj) 
{
	if(@is_array($obj))
	{
		foreach($obj as &$item)
		{
			if(is_string($item))
			{
				$item = htmlspecialchars_decode($item,ENT_QUOTES); 
			}
			else if(is_array($item))
			{
				$item = sc_html_encode($item);
			}
		}unset($item);
	}
	else if(is_string($obj))
	{
		$obj = htmlspecialchars_decode($obj,ENT_QUOTES); 
	}
	return $obj;
}

