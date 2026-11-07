(function (config) {
    if (!config || !window.ReactNativeWebView) return;
    if (!config.signedIn) {
        window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'website.push.logout' }));
        return;
    }
    window.addEventListener('appnatively.push.challenge', async function (event) {
        try {
            const response = await fetch(config.endpoint, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
                body: JSON.stringify(event.detail)
            });
            if (!response.ok) return;
            window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'website.push.identity', proof: await response.json() }));
        } catch (error) { /* A future identity challenge can retry. */ }
    });
})(window.crafAppnaNotifications);
