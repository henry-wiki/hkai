// jsmods/about/about.js —— about 域（唯一入口——出入口掛喺 prototype）
// 用途：(await (await registry().sc_ai_settings()).about()).act().about() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act 接喺度；每條自載）

class _about {
    constructor() {
    }
}

// 連接（出入口掛上 prototype）
_about.prototype.act = function () { return new _about_act_bridge(); };

export const about = (function (instance) {
    return function () {

        return instance;
    };
})(new _about());

// ── 動作表（bridge——接駁 act 目錄嘅 function；每條自載）──

// act（動作——開單叫工作）
class _about_act_bridge {
    constructor() {}
    init() {

    }
    async about()  { return (await import("./act/about.js")).about(); }
}
