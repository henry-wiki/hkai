<?php
namespace sc\framework\database\grammars;

// == sc_mysql_grammar：MySQL 語法（識別符用反引號——對應 Laravel MySqlGrammar） ==

class sc_mysql_grammar_Class extends sc_grammar_Class
{
    protected $wrapChars = ['`', '`'];
}
