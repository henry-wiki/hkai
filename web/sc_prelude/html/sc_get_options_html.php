<?PHP

function sc_get_options_html($rows,$option_value=null,$option_text=null,$selectedValue=null,$datavalue=null,$attrlist=array())
{

	$option_html = "";
	if(@count($rows) && @is_array($rows))
	{
		foreach($rows as $item)
		{
			$value 	= (isset($item[$option_value]) ? $item[$option_value] : null);

			if(@is_callable($option_text))
			{
				$text = $option_text($item);
			}
			else
				$text = sc_replace_tags($option_text,$item,1);

			if(@is_array($selectedValue))
			{
				$selected = @in_array($value,$selectedValue) ? "selected" : "";
			}else{
				$selected = $value == $selectedValue ? "selected" : "";
			}

			$attr = "";
			if($datavalue)
				$attr .= " data-value='".$item[$datavalue]."'";

			if(@is_array($attrlist))
			{
				foreach($attrlist as $attritem)
				{
					if(@isset($item[$attritem]))
					$attr .= " data-".$attritem."='".$item[$attritem]."'";
				}
			}


			$option_html .= "<option ".$attr." value='".$value."' ".$selected.">".$text."</option>";
		}

	}

	return $option_html;
}
