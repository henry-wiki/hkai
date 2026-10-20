<?PHP

function sc_get_radios_html($rows,$fieldname,$fieldvalue=null,$option_text=null,$selectedValue=null,$datavalue=null)
{

	$radios_html = "";
	if(@count($rows) && @is_array($rows))
	{
		foreach($rows as $item)
		{
			$value 	= (isset($item[$fieldvalue]) ? $item[$fieldvalue] : null);

			if(@is_callable($option_text))
			{
				$text = $option_text($item);
			}
			else
				$text = sc_replace_tags($option_text,$item,1);

			if(@is_array($selectedValue))
			{
				$selected = @in_array($value,$selectedValue) ? "checked" : "";
			}else{
				$selected = $value == $selectedValue ? "checked" : "";
			}

			$attr = "";
			if($datavalue)
				$attr .= " data-value='".$item[$datavalue]."'";


			$radios_html .= "
				<label class='radio_label'><input type='radio' name='".$fieldname."' ".$attr." value='".$value."' ".$selected." style='position:relative;top:2px;' /> ".$text."</label>";
		}

	}

	return $radios_html;
}
