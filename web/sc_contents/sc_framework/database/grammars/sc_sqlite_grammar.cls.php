<?php
namespace sc\framework\database\grammars;

// == sc_sqlite_grammar：SQLite 語法（識別符用雙引號——對應 Laravel SQLiteGrammar） ==

class sc_sqlite_grammar_Class extends sc_grammar_Class
{
    protected $wrapChars = ['"', '"'];
}
