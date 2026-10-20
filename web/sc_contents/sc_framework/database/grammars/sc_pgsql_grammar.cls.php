<?php
namespace sc\framework\database\grammars;

// == sc_pgsql_grammar：PostgreSQL 語法（識別符用雙引號——對應 Laravel PostgresGrammar） ==

class sc_pgsql_grammar_Class extends sc_grammar_Class
{
    protected $wrapChars = ['"', '"'];
}
