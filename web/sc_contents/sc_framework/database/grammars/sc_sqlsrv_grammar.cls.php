<?php
namespace sc\framework\database\grammars;

// == sc_sqlsrv_grammar：SQL Server 語法（識別符用方括號——對應 Laravel SqlServerGrammar） ==

class sc_sqlsrv_grammar_Class extends sc_grammar_Class
{
    protected $wrapChars = ['[', ']'];
}
