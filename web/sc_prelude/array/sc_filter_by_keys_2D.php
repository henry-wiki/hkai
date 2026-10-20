<?PHP

function sc_filter_by_keys_2D($data, $keys = array())
{

	if(!@$keys)
		return $data;

	array_walk($data,function(&$item) use($keys) {

		$item = sc_filter_by_keys($item,$keys);

	});

	return $data;

}
