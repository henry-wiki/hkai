<?PHP

function sc_filter_by_keys($input, $keys = array())
{

		$new_item = array();
		foreach($keys as $key)
		{
			$new_item[$key] = $input[$key];
		}
	return $new_item;
}
