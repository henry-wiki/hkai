<?PHP

function sc_get_options_by_range_html($min,$max,$selectedValue=null)
{
	$selecedValue = @intval($selecedValue);

	$option_html = "";

		for($n=$min;$n<=$max;$n++)
		{
			$value 	= @$n;

			$selected = $value === $selecedValue ? "selected" : "";

			$option_html .= "<option value='".$value."' ".$selected.">".$value."</option>";
		}



	return $option_html;
}
