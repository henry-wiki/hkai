<?php

// == database 對外 API（bridging——集中於域根） ==
// == 集中：sc_db（SQLite 連線單例） ==

use sc\framework\database\sc_db_Class;

/**
 * 取得 SQLite 連線單例
 * @return sc_db_Class
 */
function sc_db()
{
    return sc_db_Class::getInstance();
}
