<?php
namespace sc\framework\database\grammars;

// == sc_grammar：語法基底（對應 Laravel Grammar——driver 差異：識別符包裹符、日期格式） ==
// == 子類（mysql/sqlite/pgsql/sqlsrv）只覆寫包裹符與日期格式——Connection 以 grammar->wrap() 產生識別符 ==

class sc_grammar_Class
{
    // 識別符包裹符（左右一對）——子類覆寫
    protected $wrapChars = ['"', '"'];
    
    // 日期格式（bindings 內 DateTime 轉換用）——子類可覆寫
    protected $dateFormat = 'Y-m-d H:i:s';
    
    // 包裹識別符（表名/欄名——內嵌包裹符轉義）
    public function wrap($identifier)
    {
        $left  = $this->wrapChars[0];
        $right = $this->wrapChars[1];
        return $left . str_replace($right, $right . $right, $identifier) . $right;
    }
    
    // 日期格式（prepareBindings 轉換 DateTime 用）
    public function getDateFormat()
    {
        return $this->dateFormat;
    }
}
