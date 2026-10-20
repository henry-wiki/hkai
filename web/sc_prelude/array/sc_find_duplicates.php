<?PHP

function sc_find_duplicates($rows, $columns)
{
   // 如果沒有資料或欄位，返回空陣列
   if (empty($rows) || empty($columns)) {
       return [];
   }

   // 確保 columns 是陣列
   if (!is_array($columns)) {
       $columns = [$columns];
   }

   $result = [];

   // 對每個指定的欄位進行檢查
   foreach ($columns as $column) {
       $seen = [];
       $duplicates = [];

       // 遍歷所有資料行
       foreach ($rows as $index => $row) {
           // 如果該欄位存在於當前行中
           if (isset($row[$column])) {
               $value = $row[$column];

               if (isset($seen[$value])) {
                   // 如果是第一次發現重複
                   if (!isset($duplicates[$value])) {
                       $duplicates[$value] = [
                           'indices' => [$seen[$value]], // 第一次出現的位置
                           'value' => $value
                       ];
                   }
                   // 加入當前位置
                   $duplicates[$value]['indices'][] = $index;
               } else {
                   // 記錄第一次出現的位置
                   $seen[$value] = $index;
               }
           }
       }

       // 如果有重複值，加入結果
       if (!empty($duplicates)) {
           $result[$column] = array_values($duplicates);
       }
   }

   return $result;
}
