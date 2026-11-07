/*
 * AnalyticsBundle JavaScript SDK.
 *
 * Privacy-first: keine Cookies, kein LocalStorage, keine Session-ID,
 * keine automatischen Click-Listener. Bevorzugt navigator.sendBeacon(),
 * mit fetch als Fallback. DNT und Sec-GPC werden respektiert: Bei einem
 * Opt-out-Signal wird nichts gesendet.
 *
 * Einbindung (page_view_mode "client" oder "manual"):
 *
 *   <script src="/assets/analytics.js"></script>
 *   <script>
 *       JenssmitAnalytics.trackPageView();
 *       // SPA-Navigation:
 *       JenssmitAnalytics.setPage('/neue-route');
 *       JenssmitAnalytics.trackPageView();
 *       // Custom Events:
 *       JenssmitAnalytics.trackEvent('newsletter_signup');
 *   </script>
 *
 * Optionale Konfiguration vor dem Einbinden:
 *
 *   window.JenssmitAnalyticsConfig = {
 *       endpoint: '/analytics/collect',
 *       autoPageView: false
 *   };
 */
(function (global) {
    'use strict';

    var config = global.JenssmitAnalyticsConfig || {};
    var endpoint = config.endpoint || '/analytics/collect';
    var autoPageView = config.autoPageView === true;
    var currentPage = null;

    function privacyOptOut() {
        var dnt = global.navigator && global.navigator.doNotTrack;
        var gpc = global.navigator && global.navigator.globalPrivacyControl;

        return dnt === '1' || dnt === 1 || gpc === '1' || gpc === true;
    }

    function readCampaign() {
        var params = new URLSearchParams(global.location.search);
        var campaign = {};
        var keys = ['utm_source', 'utm_medium', 'utm_campaign'];
        var hasValue = false;

        for (var i = 0; i < keys.length; i += 1) {
            var value = params.get(keys[i]);
            if (value) {
                campaign[keys[i]] = value;
                hasValue = true;
            }
        }

        return hasValue ? campaign : null;
    }

    function send(url, payload) {
        var body = JSON.stringify(payload);

        if (
            global.navigator &&
            typeof global.navigator.sendBeacon === 'function' &&
            global.navigator.sendBeacon(url, body)
        ) {
            return;
        }

        if (typeof global.fetch === 'function') {
            global.fetch(url, {
                method: 'POST',
                keepalive: true,
                headers: { 'Content-Type': 'application/json' },
                body: body
            })['catch'](function () {});
        }
    }

    function currentPath() {
        return currentPage || global.location.pathname;
    }

    var api = {
        setPage: function (path) {
            if (typeof path === 'string' && path.charAt(0) === '/') {
                currentPage = path;
            }
        },
        trackPageView: function () {
            if (privacyOptOut()) {
                return;
            }

            var payload = { path: currentPath() };
            var referrer = global.document.referrer;
            var campaign = readCampaign();

            if (referrer) {
                payload.referrer = referrer;
            }

            if (campaign) {
                payload.campaign = campaign;
            }

            send(endpoint, payload);
        },
        trackEvent: function (name, value, category) {
            if (privacyOptOut() || typeof name !== 'string' || !name) {
                return;
            }

            var payload = { name: name, path: currentPath() };

            if (typeof category === 'string' && category) {
                payload.category = category;
            }

            if (value !== undefined && value !== null) {
                payload.value = value;
            }

            send(endpoint + '/event', payload);
        }
    };

    global.JenssmitAnalytics = api;

    if (autoPageView && global.document.readyState === 'complete') {
        api.trackPageView();
    } else if (autoPageView) {
        global.addEventListener('load', function () {
            api.trackPageView();
        });
    }
})(typeof window !== 'undefined' ? window : this);
