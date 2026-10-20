<?php

function get_normalize_date($date, $inputFormat = 'auto', $outputFormat = 'Y-m-d')
{
    // 檢查空值
	if(@empty($date))
		return '';

	if(!is_string($date) && !is_numeric($date))
		return '';

    // 檢查是否為 0000-00-00 或類似格式
	if (preg_match('/^0{4}[-\/]0{2}[-\/]0{2}/', $date) ||
		preg_match('/^0{2}[-\/]0{2}[-\/]0{4}/', $date))
	{
		return '';
	}


    // 檢查輸出格式是否有效
    if (empty($outputFormat))
	{
        $outputFormat = 'Y-m-d'; // 預設值
    }

	// 處理 Unix timestamp
    if (is_numeric($date) && $date > 0)
	{
        return date($outputFormat, $date);
    }


    // 如果指定了輸入格式，直接使用
    if ($inputFormat !== 'auto')
	{
        $dateObj = DateTime::createFromFormat($inputFormat, $date);

		if($dateObj === false)
			return '';

		$year 	= (int)$dateObj->format('Y');
		$month 	= (int)$dateObj->format('m');
		$day 	= (int)$dateObj->format('d');

		if($year < 1990)
			return '';

		if(!checkdate($month, $day, $year))
			return '';

        return $dateObj->format($outputFormat);
    }


    // === auto mode : 自動偵測 ===
    $commonFormats = [
        'Y-m-d',
        'Y/m/d',
        'Y.m.d',
        'd/m/Y',
        'm/d/Y',
        'd-m-Y',
        'm-d-Y',
        'd.m.Y',
        'm.d.Y',
        'Ymd',
        'Y-m-d H:i:s',
        'Y/m/d H:i:s',
        'Y-m-d H:i',
        'Y/m/d H:i',
        'd/m/Y H:i:s',
        'm/d/Y H:i:s',
        'd-m-Y H:i:s',
        'm-d-Y H:i:s',
        'Y-m-d\TH:i:s',    // ISO 8601
        'Y-m-d\TH:i:sP',   // ISO 8601 with timezone
        'Y-m-d\TH:i:s.uP', // ISO 8601 with microseconds
    ];

    // 嘗試每個格式
    foreach ($commonFormats as $format)
	{
        $dateObj = DateTime::createFromFormat($format, $date);

		if($dateObj !== false)
		{
			$year 	= (int)$dateObj->format('Y');
			$month 	= (int)$dateObj->format('m');
			$day 	= (int)$dateObj->format('d');

			if($year < 1990)
				return '';

			if(!checkdate($month, $day, $year))
				return '';

			return $dateObj->format($outputFormat);

			break;
		}
		else
			$dateObj = null;


    }

    return false;
}
