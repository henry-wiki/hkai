<?php

function sc_attrs_to_array($input)
{
	// /(\w+)(?:\s*=\s*(?:'([^']*)'|\"([^\"]*)\"|(\S+)))?/s

	// (\w+)           - 捕獲屬性名
	// (?:             - 非捕獲群組（整個 = value 部分）
	  // \s*=\s*       - 等號，前後可有空格
	  // (?:           - 非捕獲群組，匹配值：
		// '([^']*)'   - 單引號值
		// |           - 或
		// \"([^\"]*)\" - 雙引號值
		// |           - 或
		// (\S+)       - 無引號值
	  // )
	// )?              - 整個 = value 部分可選（處理無值屬性）
	// /s              - 讓 . 匹配換行

	$input = @trim($input);


	$attrs = array();

    // 單一正則處理所有情況
    $pattern = "/([\w\-\_]+)(?:\s*=\s*(?:'([^']*)'|\"([^\"]*)\"|(\S+)))?/s";

    preg_match_all($pattern, $input, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $attrName = trim($match[1]);

        if (empty($attrName)) continue;

        // 檢查是否有值
        if (isset($match[2]) && $match[2] !== '') {
            $attrValue = $match[2];  // 單引號
        } elseif (isset($match[3]) && $match[3] !== '') {
            $attrValue = $match[3];  // 雙引號
        } elseif (isset($match[4]) && $match[4] !== '') {
            $attrValue = $match[4];  // 無引號
        } else {
            $attrValue = true;       // 無值屬性
        }

        $attrs[$attrName] = $attrValue;
    }

    return $attrs;

}
