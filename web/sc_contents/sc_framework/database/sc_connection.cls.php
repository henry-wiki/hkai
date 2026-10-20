<?php
namespace sc\framework\database;

// == sc_connection：連線核心（對應 Laravel Connection——全部查詢走 prepared bindings，零字串拼接） ==
// == API：select/selectOne/statement/insert/affectingStatement/lastInsertId + 事務三件套 + transaction 包裝 ==
// == SQL 產生器：insert_sql/update_sql（陣列 → [sql, bindings]——glorymetal get_insert_sql 的現代化） ==
// == 建構時依 config["schema"] 自動確保表結構（driver 差異的 schema 由 config 宣告） ==

class sc_connection_Class
{
    private $pdo = null;
    
    // 語法物件（依 driver——識別符包裹/日期格式）
    private $grammar = null;
    
    // 驅動名（factory config 帶入——事務 primitives 分流用：sqlite 走 exec 形）
    private $driver = "";
    
    // 重連閉包（斷線重試用——由 factory 建立時注入）
    private $reconnector = null;
    
    // 查詢 log（enableQueryLog 後記錄 [query, bindings, time]）
    private $queryLog = [];
    private $loggingQueries = false;
    
    // 事務層級（0 = 無事務；>1 = 嵌套 savepoint）
    private $transactions = 0;
    
    // 斷線重試標記（單次查詢只重試一次）
    private $hasRetried = false;
    
    public function __construct($pdo, $config = [], $grammar = null)
    {
        $this->pdo = $pdo;
        $this->grammar = $grammar;
        $this->driver = isset($config["driver"]) ? $config["driver"] : "";
        
        // 建構時確保 schema（config["schema"] 有宣告才執行——CREATE TABLE IF NOT EXISTS 冪等）
        if (isset($config["schema"]) && is_array($config["schema"])) {
            // 逐條建立資料表
            foreach ($config["schema"] as $createSql) {
                $this->pdo->exec($createSql);
            }
        }
    }
    
    // 取得 PDO（低層操作用）
    public function getPdo()
    {
        return $this->pdo;
    }
    
    // ==== 查詢 ====
    
    // 執行 SELECT → 多行（純關聯陣列——欄位名索引）
    public function select($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $statement = $this->prepared($query, $bindings);
            $statement->execute();
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        });
    }
    
    // 執行 SELECT → 單行（無結果回 null）
    public function selectOne($query, $bindings = [])
    {
        $rows = $this->select($query, $bindings);
        return isset($rows[0]) ? $rows[0] : null;
    }
    
    // ==== 寫入 ====
    
    // 執行無回傳值語句（INSERT/UPDATE/DELETE/DDL）→ bool
    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $statement = $this->prepared($query, $bindings);
            return $statement->execute();
        });
    }
    
    // 執行 INSERT → bool（取最後插入 id 用 lastInsertId()）
    public function insert($query, $bindings = [])
    {
        return $this->statement($query, $bindings);
    }
    
    // 執行 UPDATE/DELETE → 受影響行數
    public function affectingStatement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $statement = $this->prepared($query, $bindings);
            $statement->execute();
            return $statement->rowCount();
        });
    }
    
    // 最後插入 id（MySQL AUTO_INCREMENT / SQLite rowid）
    public function lastInsertId()
    {
        return (int)$this->pdo->lastInsertId();
    }
    
    // ==== 執行內部（對應 Laravel run——prepare + 型別綁定 + 錯誤包裝 + 斷線重試 + 查詢 log） ====
    
    // 準備語句並綁定型別（int → PARAM_INT；其餘 PARAM_STR——對應 Laravel bindValues）
    private function prepared($query, $bindings)
    {
        $statement = $this->pdo->prepare($query);
        
        // 逐筆綁定並標明型別
        foreach ($bindings as $key => $value) {
            $paramType = is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $statement->bindValue($key + 1, $value, $paramType);
        }
        
        return $statement;
    }
    
    // 正規化 bindings（DateTime → 字串、bool → int——對應 Laravel prepareBindings）
    private function prepareBindings($bindings)
    {
        // 逐筆轉換型別
        foreach ($bindings as $key => $value) {
            // DateTime → 依 grammar 日期格式
            if ($value instanceof \DateTimeInterface) {
                $bindings[$key] = $value->format($this->grammar->getDateFormat());
            } elseif (is_bool($value)) {
                $bindings[$key] = (int)$value;
            }
        }
        return $bindings;
    }
    
    // 統一執行包裝（對應 Laravel Connection::run——執行 + 錯誤包裝 + 斷線重試 + 查詢 log）
    private function run($query, $bindings, $callback)
    {
        $bindings = $this->prepareBindings($bindings);
        $start = microtime(true);
        
        try {
            $result = $callback($query, $bindings);
        } catch (\PDOException $exception) {
            // 斷線錯誤（2006/2013）且未重試過 → 重連後重試一次
            if ($this->is_lost_connection_error($exception) && !$this->hasRetried) {
                $this->hasRetried = true;
                $this->reconnect();
                $result = $callback($query, $bindings);
            } else {
                // 包裝為查詢例外（附 SQL 與 bindings）
                throw new sc_query_exception_Class($query, $bindings, $exception);
            }
        }
        
        $this->logQuery($query, $bindings, microtime(true) - $start);
        return $result;
    }
    
    // 判斷是否為斷線錯誤（MySQL 2006/2013——SQLite 無此錯誤）
    private function is_lost_connection_error($exception)
    {
        $errorInfo = $exception->errorInfo;
        return isset($errorInfo[1]) && in_array($errorInfo[1], [2006, 2013]);
    }
    
    // 重連（依 reconnector 閉包重建 PDO——由 factory 注入）
    public function reconnect()
    {
        if ($this->reconnector === null) {
            return;
        }
        
        $this->pdo = call_user_func($this->reconnector);
    }
    
    // 注入重連閉包（factory 建立連線時呼叫）
    public function set_reconnector($reconnector)
    {
        $this->reconnector = $reconnector;
    }
    
    // ==== 查詢 log（預設關閉——debug 時 enableQueryLog） ====
    
    // 開啟查詢 log
    public function enableQueryLog()
    {
        $this->loggingQueries = true;
    }
    
    // 取得查詢 log（[query, bindings, time]）
    public function getQueryLog()
    {
        return $this->queryLog;
    }
    
    // 記錄一筆查詢（僅開啟 log 時）
    public function logQuery($query, $bindings, $time)
    {
        if ($this->loggingQueries) {
            $this->queryLog[] = ["query" => $query, "bindings" => $bindings, "time" => $time];
        }
    }
    
    // ==== 事務（對應 Laravel ManagesTransactions——嵌套 = savepoint；deadlock 可重試） ====
    
    // 事務包裝（closure 內全部成功才 COMMIT；例外自動 ROLLBACK 並重拋；死鎖可依 attempts 重試）
    public function transaction($callback, $attempts = 1)
    {
        // 依 attempts 嘗試執行（死鎖重試用）
        for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
            $this->beginTransaction();
            
            try {
                $result = $callback($this);
                $this->commit();
                return $result;
            } catch (sc_query_exception_Class $exception) {
                // 死鎖（MySQL 1213 / PostgreSQL 40001）且未達上限 → 回滾後重試
                if ($this->is_deadlock_error($exception) && $currentAttempt < $attempts) {
                    $this->rollBack();
                    continue;
                }
                // 其他錯誤或已達上限 → 回滾並重拋
                $this->rollBack();
                throw $exception;
            }
        }
    }
    
    // 開始事務（已有事務時建立 savepoint——嵌套支援）
    public function beginTransaction()
    {
        // 已在外層事務內 → 建立 savepoint
        if ($this->transactions > 0) {
            $this->create_savepoint($this->transactions + 1);
        } else {
            // 判定：sqlite → BEGIN IMMEDIATE（一開波攞寫鎖——先讀後寫都唔會撞快照；衝突變排隊等鎖）
            if ($this->driver === "sqlite") {
                $this->pdo->exec("BEGIN IMMEDIATE");
            } else {
                $this->pdo->beginTransaction();
            }
        }
        
        $this->transactions++;
        return true;
    }
    
    // 提交（嵌套時只釋放 savepoint，外層才真正 COMMIT）
    public function commit()
    {
        // 內層事務 → 釋放 savepoint
        if ($this->transactions > 1) {
            $this->release_savepoint($this->transactions);
        } else {
            // 判定：sqlite → exec(COMMIT)（配返 exec 開嘅事務——PHP 7.3 PDO 唔認 exec 開嘅事務）
            if ($this->driver === "sqlite") {
                $this->pdo->exec("COMMIT");
            } else {
                $this->pdo->commit();
            }
        }
        
        $this->transactions--;
        return true;
    }
    
    // 回滾（可回滾到指定層級——對應 Laravel rollBack($toLevel)）
    public function rollBack($toLevel = null)
    {
        // 未指定 → 回滾到上一層（最內層）
        if ($toLevel === null) {
            $toLevel = $this->transactions - 1;
        }
        
        // 目標層級低於目前 → 執行回滾
        if ($toLevel < $this->transactions) {
            $this->perform_rollback($toLevel);
        }
    }
    
    // 目前事務層級
    public function transactionLevel()
    {
        return $this->transactions;
    }
    
    // 建立 savepoint（SAVEPOINT trans{n}）
    private function create_savepoint($level)
    {
        $this->pdo->exec("SAVEPOINT trans" . $level);
    }
    
    // 釋放 savepoint（RELEASE SAVEPOINT trans{n}）
    private function release_savepoint($level)
    {
        $this->pdo->exec("RELEASE SAVEPOINT trans" . $level);
    }
    
    // 執行回滾（到指定層級——ROLLBACK TO SAVEPOINT 或完整 ROLLBACK）
    private function perform_rollback($toLevel)
    {
        // 回到指定層級：層級 > 0 → 回滾到該 savepoint；層級 0 → 完整回滾
        if ($toLevel > 0) {
            $this->pdo->exec("ROLLBACK TO SAVEPOINT trans" . ($toLevel + 1));
        } else {
            // 判定：sqlite → exec(ROLLBACK)（配返 exec 開嘅事務——PHP 7.3 PDO 唔認 exec 開嘅事務）
            if ($this->driver === "sqlite") {
                $this->pdo->exec("ROLLBACK");
            } else {
                $this->pdo->rollBack();
            }
        }
        
        $this->transactions = $toLevel;
    }
    
    // 判斷是否為死鎖錯誤（MySQL 1213 / PostgreSQL 40001）
    private function is_deadlock_error($exception)
    {
        $errorInfo = $exception->getPrevious() !== null ? $exception->getPrevious()->errorInfo : null;
        return isset($errorInfo[1]) && in_array($errorInfo[1], [1213, 40001]);
    }
    
    // ==== SQL 產生器（陣列 → [sql, bindings]——prepared，零字串拼接） ====
    
    // 產生 INSERT SQL（回傳 [sql, bindings]）
    public function insert_sql($tablename, $dataPairs)
    {
        $columns = array_keys($dataPairs);
        $bindings = array_values($dataPairs);
        
        $quotedColumns = [];
        // 逐欄加引號（依 grammar 包裹符）
        foreach ($columns as $column) {
            $quotedColumns[] = $this->grammar->wrap($column);
        }
        
        $placeholders = array_fill(0, count($columns), "?");
        
        $sql = "insert into " . $this->grammar->wrap($tablename)
             . " (" . implode(",", $quotedColumns) . ") values (" . implode(",", $placeholders) . ")";
        
        return [$sql, $bindings];
    }
    
    // 產生 UPDATE SQL（回傳 [sql, bindings]；whereSql 為「已含 ? 佔位」的條件字串）
    public function update_sql($tablename, $dataPairs, $whereSql, $whereBindings = [])
    {
        $setPairs = [];
        $bindings = [];
        
        // 逐欄產生 set 片段與 bindings
        foreach ($dataPairs as $column => $value) {
            $setPairs[] = $this->grammar->wrap($column) . " = ?";
            $bindings[] = $value;
        }
        
        $bindings = array_merge($bindings, $whereBindings);
        
        $sql = "update " . $this->grammar->wrap($tablename)
             . " set " . implode(", ", $setPairs)
             . " where " . $whereSql;
        
        return [$sql, $bindings];
    }
    
}
