<?php
namespace sc\framework\database;

// == sc_query_exception：查詢例外（對應 Laravel QueryException——錯誤訊息附上 SQL 與 bindings，debug 用） ==

class sc_query_exception_Class extends \Exception
{
    private $sql;
    private $bindings;
    
    public function __construct($sql, $bindings, $previous)
    {
        $this->sql = $sql;
        $this->bindings = $bindings;
        
        $message = "SQL query error: " . $previous->getMessage()
                 . " [sql: " . $sql . "]";
        
        parent::__construct($message, 0, $previous);
    }
    
    // 出錯的 SQL
    public function getSql()
    {
        return $this->sql;
    }
    
    // 出錯時的 bindings
    public function getBindings()
    {
        return $this->bindings;
    }
}
