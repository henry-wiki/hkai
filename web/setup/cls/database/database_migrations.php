<?php

namespace sc\setup\database;

// == setup database migrations：版本記錄與升級（單元——class；呼叫形 \sc\setup\setup()->database_migrations()）
// == 職責：列出 migration 檔、讀已套用清單、套用缺的檔、記錄每一檔（檔名＝主鍵；版本由 DB.VERSION 管）
// == 業界對照：Laravel／Flyway 以「檔名」為版本識別；同一版本可有多個檔（0002_a、0002_b）
// == 上游：database 格新建庫（db_ready）、舊庫匯入（importer）
// == 下游：storage.migrations_dir（*.sql.php）、migrations 表

class database_migrations_Class
{
    private static $instance = null;

    /**
     * 單例（系統唯一入口）
     *
     * @return \sc\setup\database\database_migrations_Class
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    // 列出 migration 檔（按版本號、檔名排序；每項 name／version／path）
    public function list_migration_files()
    {
        $migrations_dir = sc_get_config("storage.migrations_dir");

        $migration_paths = glob($migrations_dir . "/*.sql.php");

        // 判定：冇檔 → 空
        if (is_array($migration_paths) === false) {
            return [];
        }

        $migration_files = [];

        foreach ($migration_paths as $migration_path) {
            $migration_name = basename($migration_path);

            $migration_files[] = [
                "name" => $migration_name,
                "path" => $migration_path,
            ];
        }

        // 按檔名排序（檔名前綴＝次序）
        usort($migration_files, function ($left_file, $right_file) {
            return strcmp($left_file["name"], $right_file["name"]);
        });

        return $migration_files;
    }

    // 讀已套用檔名（回 name => applied_at）
    public function read_applied_migrations($pdo_connection)
    {
        $has_migrations_table = $this->has_migrations_table($pdo_connection);

        // 判定：冇 migrations 表 → 空
        if ($has_migrations_table === false) {
            return [];
        }

        $applied_migrations = [];

        foreach ($pdo_connection->query("SELECT migration_name, migration_applied_at FROM migrations") as $migration_row) {
            $applied_migrations[$migration_row["migration_name"]] = $migration_row["migration_applied_at"];
        }

        return $applied_migrations;
    }

    // 套用缺的 migration（逐檔執行＋記錄；回已套用檔名清單）
    public function apply_pending_migrations($pdo_connection)
    {
        $applied_migrations = $this->read_applied_migrations($pdo_connection);

        $pending_files = [];

        foreach ($this->list_migration_files() as $migration_file) {
            // 判定：已套用過 → 跳
            if (isset($applied_migrations[$migration_file["name"]]) === true) {
                continue;
            }

            $pending_files[] = $migration_file;
        }

        // 先執行全部 SQL（migrations 表可能由其中一檔建立）
        foreach ($pending_files as $migration_file) {
            $migration_sql = require $migration_file["path"];

            $pdo_connection->exec($migration_sql);
        }

        $applied_names = [];

        // 再逐檔記錄（表已存在）
        foreach ($pending_files as $migration_file) {
            $this->insert_migration_row($pdo_connection, $migration_file);

            $applied_names[] = $migration_file["name"];
        }

        return $applied_names;
    }

    // 記錄全部為已套用（新裝：schema 檔已建最新結構，只補記錄、不執行）
    public function record_all_as_applied($pdo_connection)
    {
        $applied_migrations = $this->read_applied_migrations($pdo_connection);

        foreach ($this->list_migration_files() as $migration_file) {
            // 判定：已記錄 → 跳
            if (isset($applied_migrations[$migration_file["name"]]) === true) {
                continue;
            }

            $this->insert_migration_row($pdo_connection, $migration_file);
        }
    }

    // 有冇 migrations 表
    public function has_migrations_table($pdo_connection)
    {
        $driver_name = $pdo_connection->getAttribute(\PDO::ATTR_DRIVER_NAME);

        // 判定：SQLite → 查 sqlite_master
        if ($driver_name === "sqlite") {
            $table_names = $pdo_connection->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(\PDO::FETCH_COLUMN);

            return in_array("migrations", $table_names, true);
        }

        $statement = $pdo_connection->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_name = :table_name");

        $statement->execute([":table_name" => "migrations"]);

        return (int)$statement->fetchColumn() > 0;
    }

    // 寫一行記錄（檔名＝主鍵；有就更新 checksum）
    private function insert_migration_row($pdo_connection, $migration_file)
    {
        $migration_checksum = hash_file("sha256", $migration_file["path"]);

        $insert_statement = $pdo_connection->prepare(
            "INSERT INTO migrations (migration_name, migration_checksum, migration_applied_at)"
            . " VALUES (:name, :checksum, CURRENT_TIMESTAMP)"
            . " ON CONFLICT(migration_name) DO UPDATE SET migration_checksum = :checksum"
        );

        $insert_statement->execute([
            ":name"     => $migration_file["name"],
            ":checksum" => $migration_checksum,
        ]);
    }
}
