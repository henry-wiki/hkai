<?php

// == i18n 對外 API（bridging：回傳 class 單例） ==

use sc\framework\i18n;

/**
 * 取得語系（locale）管理單例
 * @return i18n\sc_locale_Class
 */
function sc_locale()
{
    return i18n\sc_locale_Class::getInstance();
}

/**
 * 取得翻譯（translator）單例
 * @return i18n\sc_i18n_Class
 */
function sc_i18n()
{
    return i18n\sc_i18n_Class::getInstance();
}
