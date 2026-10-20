// jsmods/chat_option/obs/model_info_observer.js —— chat options 變化觀察綁定（一檔一事）
// 職責：model_info_observer（觀察 .sc_composer 長駐外框——subtree 內 [data-model_field] 嘅 value 屬性變 → 交單一提交點；換對話重繪＝childList，唔喺過濾內唔誤觸）
// 結構：eager 單例 closure factory（具名 export——域接駁 obs 橋動態 import 解構後呼叫 init()）

class _model_info_observer {
    constructor() {
        // 觀察器（重掛先斷——唔殘留）
        this.mutation_observer = null;
    }

    // 建立＋觀察（可重複呼叫——重掛先斷；只建立，唔即刻派）
    init() {
        const thisObject = this;

        // 判定：舊觀察器存在 → 先斷（重掛唔殘留）
        if (thisObject.mutation_observer) {
            thisObject.mutation_observer.disconnect();
            thisObject.mutation_observer = null;
        }

        // 觀察目標（.sc_composer 頁面長駐——換對話只換內裏，觀察唔斷）
        const composer_element = jQuery(".sc_composer")[0];

        // 判定：容器唔存在 → 唔掛（維持原行為）
        if (!composer_element) {
            return;
        }

        // 觀察器（subtree、attributes、只認 value——[data-model_field] 欄位嘅 value 屬性先算）
        thisObject.mutation_observer = new MutationObserver(function (mutation_records) {
            thisObject.on_model_info_changed(mutation_records);
        });

        thisObject.mutation_observer.observe(composer_element, {
            subtree: true,
            attributes: true,
            attributeFilter: ["value"],
        });
    }

    // 變化回呼（見 [data-model_field] 欄位變 → 交單一提交點；去重喺提交點做）
    async on_model_info_changed(mutation_records) {
        // 判定：呢批紀錄有冇 [data-model_field] 欄位變
        let has_model_field_change = false;

        // 逐筆紀錄檢查
        for (let record_index = 0; record_index < mutation_records.length; record_index++) {
            const mutation_record = mutation_records[record_index];

            // 判定：目標帶 data-model_field → 算真變化來源
            if (mutation_record.target.dataset.model_field !== undefined) {
                has_model_field_change = true;
            }
        }

        // 判定：冇欄位變 → 唔理
        if (has_model_field_change === false) {
            return;
        }

        // 交單一提交點（同上次一樣＝提交點自己濾）
        const chat_option_domain = await registry().sc_ai_chats().chat_option();
        const submit_chat_options_instance = await chat_option_domain.act().submit_chat_options();
        await submit_chat_options_instance.submit_chat_options();
    }
}

export const model_info_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _model_info_observer());
