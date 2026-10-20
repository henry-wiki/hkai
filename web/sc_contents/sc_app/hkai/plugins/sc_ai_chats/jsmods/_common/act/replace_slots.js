// jsmods/_common/act/replace_slots.js —— 槽位替換共用原語（act 層——一檔一事）
// 職責：replace_slots（[data-slot='key'] 空容器 → 換成 slots[key] 真容器（自帶容器）；空值或找不到目標就略過，不破壞畫面）
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _replace_slots {
    constructor() {}

    // 槽位替換（j_scope＝外殼範圍；slots＝後台動態內容表——template ＋ slots 統一結構共用）
    replace_slots(j_scope, slots) {
        // 判定：無槽位資料 → 不做
        if (!slots) {
            return;
        }

        const slot_keys = Object.keys(slots);

        for (let slot_key_index = 0; slot_key_index < slot_keys.length; slot_key_index++) {
            const slot_key = slot_keys[slot_key_index];
            const slot_html = slots[slot_key];

            // 判定：空內容 → 略過（保留原容器，下一輪仍能填入）
            if (!slot_html) {
                continue;
            }

            const j_target = j_scope.find("[data-slot='" + slot_key + "']");

            // 判定：找不到目標 → 略過
            if (!j_target.length) {
                continue;
            }

            j_target.replaceWith(slot_html);
        }
    }
}

export const replace_slots = (function (instance) {
    return function () {

        return instance;
    };
})(new _replace_slots());
