// sc_app/shared/jsmods/sc_http.js —— app 級共用 URL 工具（ESM 模組＋掛全域 sc_url）
// 載入：hkai/jsmods/bootstrap.js → registry.js → shared/jsmods/shared.js（ESM 鏈；同時掛全域——既有呼叫點零改動）
// 取用：共用域經 shared().sc_http()；既有 plugin 仍直接 sc_url.xxx()
// 職責：URL query string 增刪（add_qs／del_qs）＋只寫 URL 唔導航（shallow_replace_url）＋URL 變化監聽（on_url_change／off_url_change——劫持 pushState／replaceState ＋ popstate／hashchange；中央派發去重，同一條 URL 唔重複報；派發帶變化類型 navigation_type：push／replace／traverse／hash／shallow）

// 取 URL 去掉 query 的路徑段（無 ? → 原樣）
function get_url_path(url) {
    // 判定：無 query → 原樣回傳
    if (url.indexOf("?") < 0) {
        return url;
    }

    return url.substr(0, url.indexOf("?"));
}

// query string → object（值以 decodeURIComponent 還原——jQuery.param 為 encode 端）
function search_to_object(url) {
    const query_object = {};
    const url_path     = get_url_path(url);

    // 判定：無 query（路徑＝全字串）→ 回空物件
    if (url_path === url) {
        return query_object;
    }

    const query_string = url.substr(url_path.length + 1);
    const query_pairs  = query_string.split("&");

    // 逐段解析 key=value
    for (let pair_index = 0; pair_index < query_pairs.length; pair_index++) {
        const pair_text = query_pairs[pair_index];

        // 判定：空段 → 跳過
        if (!pair_text) {
            continue;
        }

        const pair_parts = pair_text.split("=");
        const pair_key   = decodeURIComponent(pair_parts[0]);
        const pair_value = pair_parts[1] ? decodeURIComponent(pair_parts[1]) : "";

        query_object[pair_key] = pair_value;
    }

    return query_object;
}

class _sc_http {
    constructor() {
        // 訂閱者清單（callback 身份——Set 保證唯一；同一條只收一次事件）
        this.subscribers = new Set();

        // 現時 URL（最後派過嘅一條——同一條再嚟＝冇變，唔重複派）
        this.current_url = location.href;

        // 啟動底層監聽（全域只做一次）
        this.init_url_monitor();
    }

    // 加／改 query 參數（回傳新 URL；url 省略＝目前頁面）
    add_qs(url, name, value) {
        // 判定：未帶 url → 用目前頁面
        if (!url) {
            url = document.location.href;
        }

        url = url + "";

        // 去掉 #fragment
        url = url.replace(/[#]+?[^\r\n]*$/, "");

        const url_path     = get_url_path(url);
        const query_object = search_to_object(url);
        query_object[name] = value;

        const query_string = jQuery.param(query_object);
        const new_url      = url_path + "?" + query_string;

        return new_url;
    }

    // 批次加／改多個 query 參數（literal object——逐項呼叫 add_qs；url 省略＝目前頁面）
    add_multi_qs(url, query_metadata) {
        // 判定：無 query 物件（null／undefined）→ 原樣回傳 url
        if (!query_metadata) {
            return url;
        }

        // 判定：query 物件非 object 型別 → 原樣回傳 url
        if (typeof query_metadata !== "object") {
            return url;
        }

        const query_keys = Object.keys(query_metadata);
        let new_url      = url;

        // 逐項附加 query 參數（一一併入）
        for (let query_index = 0; query_index < query_keys.length; query_index++) {
            const query_key = query_keys[query_index];
            new_url = this.add_qs(new_url, query_key, query_metadata[query_key]);
        }

        return new_url;
    }

    // 刪 query 參數（回傳新 URL；url 省略＝目前頁面）
    del_qs(url, name) {
        // 判定：未帶 url → 用目前頁面
        if (!url) {
            url = document.location.href;
        }

        url = url + "";

        // 去掉 #fragment
        url = url.replace(/[#]+?[^\r\n]*$/, "");

        const url_path     = get_url_path(url);
        const query_object = search_to_object(url);

        // 判定：該參數存在 → 刪除
        if (query_object[name]) {
            delete query_object[name];
        }

        const query_string = jQuery.param(query_object);
        const new_url      = url_path + "?" + query_string;

        return new_url;
    }

    // 只寫 URL 唔導航（shallow——繞原生 replaceState 直接寫（唔經劫持）；寫完照派、但類型係 shallow——訂閱者自行決定要唔要當真變化；current_url 照對齊）
    shallow_replace_url(url) {
        this.original_replace_state.apply(history, [null, "", url]);

        this.dispatch_url_change("shallow");
    }

    // 底層監聽（全域只做一次——劫持 history 兩個方法 ＋ 瀏覽器兩個事件；URL 一變即同步派——帶變化類型）
    init_url_monitor() {
        const thisObject = this;

        const original_push_state    = history.pushState;
        const original_replace_state = history.replaceState;

        // 存起原生 replaceState（shallow_replace_url 用——繞過劫持直接寫，唔當真變化）
        thisObject.original_replace_state = original_replace_state;

        // 劫持 pushState（SPA 轉向——先做原生效，再做去重派發（類型 push））
        history.pushState = function () {
            const result = original_push_state.apply(this, arguments);

            thisObject.dispatch_url_change("push");

            return result;
        };

        // 劫持 replaceState（SPA 換 URL／無痕替換——同理（類型 replace））
        history.replaceState = function () {
            const result = original_replace_state.apply(this, arguments);

            thisObject.dispatch_url_change("replace");

            return result;
        };

        // 監聽上一頁／下一頁（瀏覽器自己嘅 history 走動——類型 traverse）
        window.addEventListener("popstate", function () {
            thisObject.dispatch_url_change("traverse");
        });

        // 監聽錨點變化（#hash——類型 hash）
        window.addEventListener("hashchange", function () {
            thisObject.dispatch_url_change("hash");
        });
    }

    // 派 URL 變化（中央派發——同一條 URL 冇變就唔派；帶變化類型（navigation_type——業界 Navigation API 詞彙 push／replace／traverse／hash；本 app 加 shallow＝只寫 URL 唔導航）畀訂閱者）
    dispatch_url_change(navigation_type) {
        const changed_url = location.href;

        // 判定：同一條 URL 冇變 → 唔係變化（app 自己 replace 嘅 no-op——唔報）
        if (changed_url === this.current_url) {
            return;
        }

        const previous_url = this.current_url;
        this.current_url = changed_url;

        // 逐個訂閱者派（callback 身份——同註冊時一樣；第一個參數＝變化類型）
        for (const callback of this.subscribers) {
            callback(navigation_type, changed_url, previous_url);
        }
    }

    // 監聽 URL 變化（按「有冇真變」派——同一條 URL 唔重複報；目的地交呼叫端自己解析）
    on_url_change(callback) {
        this.subscribers.add(callback);
    }

    // 取消監聽 URL 變化（傳返同一個 callback——認身份刪；未註冊過＝唔做）
    off_url_change(callback) {
        this.subscribers.delete(callback);
    }
}

// 出口（factory——同 hkai/jsmods/registry.js 模式；shared().sc_http() 取單例）
const sc_http = (function (instance) {
    return function () {
        return instance;
    };
})(new _sc_http());

export default sc_http;

// 掛全域（既有呼叫點 sc_url.xxx() 零改動）
window.sc_url = sc_http();
