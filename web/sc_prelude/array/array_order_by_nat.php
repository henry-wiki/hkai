<?PHP

function array_order_by_nat(&$rows, $orderByString)
{
    if (empty($rows) || empty(trim($orderByString))) {
        return;
    }

    // 解析排序規則
    $orderRules = [];
    $orderClauses = explode(",", trim($orderByString));

    foreach ($orderClauses as $clause) {
        $parts = preg_split('/\s+/', trim($clause));
        $column = trim($parts[0]);

        if (empty($column)) {
            continue;
        }

        $direction = 'ASC';
        if (isset($parts[1]) && strtoupper(trim($parts[1])) === 'DESC') {
            $direction = 'DESC';
        }

        $orderRules[] = [
            'column' => $column,
            'direction' => $direction
        ];
    }

    if (empty($orderRules)) {
        return;
    }

    // 使用 usort 進行自然排序
    usort($rows, function($a, $b) use ($orderRules) {
        foreach ($orderRules as $rule) {
            $column = $rule['column'];
            $direction = $rule['direction'];

            $valA = isset($a[$column]) ? $a[$column] : '';
            $valB = isset($b[$column]) ? $b[$column] : '';

            // 🔥 使用自然排序比較
            $result = strnatcasecmp($valA, $valB);

            if ($result !== 0) {
                return ($direction === 'DESC') ? -$result : $result;
            }
        }
        return 0;
    });
}
