<?PHP

function array_find_duplicate($array_data,&$dup_keys) {

	$dup_array = array();
	$dup_keys = array();

    foreach ($array_data as $key => $val) {

		if(!@isset($dup_array[$val]))
		{
			$dup_array[$val] = 0;
		}
		else
		{
			$dup_keys[] = $key;
			$dup_array[$val] = $key;
		}
    }

	$dup_array = array_filter($dup_array);
	$dup_array = array_values($dup_array);

    return $dup_array;
}
