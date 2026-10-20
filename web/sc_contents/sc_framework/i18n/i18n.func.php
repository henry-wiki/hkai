<?php

// == i18n 全域函數（翻譯） ==
// == sc_translate($key, $group)：查字典；未命中回傳 key 本身 ==

function sc_translate($key, $group = null)
{
    return sc_i18n()->translate($key, $group);
}
