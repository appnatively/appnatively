(function (config) {
    if (!config || !window.ReactNativeWebView) return;
    let pending = null;
    let completed = null;
    let logoutDocument = null;
    let active = true;
    const documentId = () => window.AppNativelyPush?.documentId;
    const cancel = () => { pending?.controller.abort(); pending = null; };
    const post = (type, id, detail = {}) => {
        if (active && id && id === documentId()) {
            window.ReactNativeWebView.postMessage(JSON.stringify({ type, documentId: id, ...detail }));
        }
    };
    const ready = () => {
        const id = documentId();
        if (!active || !id) return;
        if (pending && pending.documentId !== id) cancel();
        if (!config.signedIn) {
            cancel();
            if (logoutDocument !== id) { post('website.push.logout', id); logoutDocument = id; }
        } else post('website.push.ready', id);
    };
    window.addEventListener('appnatively.push.ready', ready);
    window.addEventListener('appnatively.push.challenge', function (event) {
        const { documentId: id, ...challenge } = event.detail || {};
        if (!active || !config.signedIn || !id || id !== documentId() || typeof challenge.challenge !== 'string' ||
            (completed?.documentId === id && completed.challenge === challenge.challenge) ||
            (pending?.documentId === id && pending.challenge === challenge.challenge)) return;
        cancel();
        const request = { documentId: id, challenge: challenge.challenge, controller: new AbortController() };
        pending = request;
        const timer = setTimeout(() => request.controller.abort(), 10000);
        void (async () => {
            const response = await fetch(config.endpoint, {
                method: 'POST', credentials: 'same-origin', redirect: 'error', signal: request.controller.signal,
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
                body: JSON.stringify(challenge)
            });
            if (!response.ok) return;
            const proof = await response.json();
            if (!active || pending !== request || request.controller.signal.aborted || id !== documentId()) return;
            completed = { documentId: id, challenge: challenge.challenge };
            post('website.push.identity', id, { proof });
        })().catch(() => { /* A later readiness/challenge can retry. */ })
            .finally(() => { clearTimeout(timer); if (pending === request) pending = null; });
    });
    window.addEventListener('pagehide', () => { active = false; cancel(); });
    window.addEventListener('pageshow', () => { active = true; ready(); });
    ready();
})(window.crafAppnaNotifications);
