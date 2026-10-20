<?php

function array_group_by($array=array(),$group_by=null,$singleRecord=false,$captureColumns=null)
{
	$new_array = array();
	if(!@is_array($array)) return $new_array;
	if(@count($array))
	{
		foreach($array as $k=> $item)
		{
			// 判定：分組值先讀落變數（下面 captureColumns 會 unset 欄位——之後唔可以再讀 $item[$group_by]）
			$groupValue = $item[$group_by];
			if(!$singleRecord)
			{
				// 判定：此組第一次出現（key 未建立）→ 開空組（先 isset，唔讀未定義索引）
				if(!isset($new_array[$groupValue]))
				$new_array[$groupValue] = array();

				if(is_array($captureColumns))
				{
					foreach($item as $capture_key => $subitem)
					{
						if(!in_array($capture_key,$captureColumns))
						{
							unset($item[$capture_key]);
						}

					}


					$captureValue = $item;
				}
				else if(is_string($captureColumns))
				{
					$captureValue = $item[$captureColumns];
				}
				else
					$captureValue = $item;

				$new_array[$groupValue][$k] = $captureValue;
			}
			else
			{
				if(is_array($captureColumns))
				{
					foreach($item as $capture_key => $subitem)
					{
						if(!in_array($capture_key,$captureColumns))
						{
							unset($item[$capture_key]);
						}

					}


					$captureValue = $item;
				}
				else if(is_string($captureColumns))
				{
					$captureValue = $item[$captureColumns];
				}
				else
					$captureValue = $item;

				$new_array[$groupValue] = $captureValue;
			}

		}
	}
	return $new_array;
}
