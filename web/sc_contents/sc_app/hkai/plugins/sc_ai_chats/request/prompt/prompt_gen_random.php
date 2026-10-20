<?php
// == sc_ai_chats/request/prompt/prompt_gen_random.php：取得 sample questions（題庫讀 sc_config/hkai/sample_question.php——唯一來源；inspiration 按鈕取用） ==

// 題庫資料源（manifest 唯一來源——路徑由入口定義、core 供給；跟 about 同形）
$sample_data = require hkai__core()->get_sample_question_file();

// shuffle 隨機排序（每次請求順序不同——前端取第一個即隨機）
shuffle($sample_data);

return sc_response()->json(["ok" => true, "questions" => $sample_data]);
