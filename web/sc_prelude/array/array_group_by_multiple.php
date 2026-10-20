<?php

function array_group_by_multiple($array=array(),$group_bys=array(),$singleRecord=false,$captureColumns=null)
{
	$new_array = $array;

	if(!@is_array($group_bys))
		$group_bys = array($group_bys);

	$keys = array_keys($group_bys);
	$last_key = array_pop($keys);

	$group_by = array_shift($group_bys);

	if(!@count($group_bys))
	{
		$new_array = array_group_by($array,$group_by,$singleRecord,$captureColumns);
	}
	else
	{
		$new_array = array_group_by($array,$group_by,false,null);

		if(@count($new_array))
		{
			foreach($new_array as &$item)
			{
				$item = array_group_by_multiple($item,$group_bys,$singleRecord,$captureColumns);
			}unset($item);
		}

	}

	return $new_array;
}
