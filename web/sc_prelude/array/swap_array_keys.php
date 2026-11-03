<?PHP

/**

[sort] => Array
	(
		[0] => 
		[1] => 
		[2] => 
	)

[name] => Array
	(
		[0] => apple
		[1] => mary
		[2] => sam
	)

==== convert to ==================================
Array
	(
		[0] => array("sort"=>"",name=>"apple")
		[1] => array("sort"=>"",name=>"mary")
		[2] => array("sort"=>"",name=>"sam") 
	)


**
 * 將「欄位 => 值清單」陣列轉成「逐行關聯陣列」
 *
 * @param array $array 以欄位名為 key、值清單為 value 的陣列
 * @return array|null 轉換後嘅逐行陣列；空輸入回 null
 */

function swap_array_keys($array=array())
{
	if(!@$array)
		return null;
	
	$new_array = array();
	$first_item 	= current($array);
	$first_columns 	= array_keys($array);
	
	foreach($array as $column_name => $items)
	{
		foreach($items as $idx => $value)
		{
			if(!@is_array($new_array[$idx]))	$new_array[$idx] = array();
			$new_array[$idx][$column_name] = $value;
		}
	}
	
	return $new_array;
}
