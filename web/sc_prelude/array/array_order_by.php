<?PHP

/**
 * 根據給定的排序字串對多維陣列進行排序。
 *
 * @param array &$rows 要排序的陣列 (會被直接修改)。
 * @param string $orderByString 排序規則字串，例如 "age DESC, name ASC"。
 * @return void 函式直接修改傳入的陣列，無返回值。
 */
function array_order_by(&$rows, $orderByString)
{
    // 如果沒有資料或沒有排序規則，直接返回
    if (empty($rows) || empty(trim($orderByString))) {
        return;
    }

    $orderByString = trim($orderByString);
    $orderClauses = explode(",", $orderByString);

    $args = []; // 用來存放傳遞給 array_multisort 的所有參數

    foreach ($orderClauses as $clause) {
        // 使用正規表示式分割，可以處理多個空格，更健壯
        $parts = preg_split('/\s+/', trim($clause));

        $column = trim($parts[0]);
        if (empty($column)) {
            continue; // 如果沒有欄位名，跳過此規則
        }

        // 預設排序為 ASC
        $direction = 'ASC';
        if (isset($parts[1])) {
            $direction = strtoupper(trim($parts[1]));
            if ($direction !== 'DESC') {
                $direction = 'ASC';
            }
        }

        // 使用 array_column 取得要排序的欄位值陣列，更清晰高效
        $args[] = array_column($rows, $column);
        $args[] = ($direction === 'ASC') ? SORT_ASC : SORT_DESC;
    }

    // 最後，將要被排序的原始陣列以「引用」方式加入到參數列表的末尾
    $args[] = &$rows;

    // 使用 call_user_func_array 動態呼叫 array_multisort
    call_user_func_array('array_multisort', $args);
}
