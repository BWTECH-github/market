import "core-js/stable";
require('./styles/theme.scss');

// -------------------------------------------------------- Uikit components ---
import UIkit from 'uikit';
import Icons from 'uikit/dist/js/uikit-icons';

// Modified by BW-Tech GmbH for owncloud.online (PHP 8.4).

UIkit.use(Icons);

// ------------------------------------------------------------- Vue plugins ---

import Vue       from 'vue'
import VueRouter from 'vue-router';

Vue.use(VueRouter);

import GetTextPlugin from 'vue-gettext'
import translations from '../l10n/translations.json'

Vue.use(GetTextPlugin, {translations: translations})
// Sprache aus <html lang>: der Kern schreibt sie mit Bindestrich ("de-DE",
// "de-AT"), die Kataloge heißen "de_DE" usw. vue-gettext fällt bei unbekannter
// Kennung nur auf den Teil vor "_" zurück (de_AT -> de), nicht vor "-".
Vue.config.language = (OC.getLocale() || 'en').replace(/-/g, '_')

// TODO: Write plugin for global t() method

// --------------------------------------------------------------- Vue setup ---

import App         from './App.vue'
import Details     from './components/Details.vue'
import List        from './components/List.vue'
import BundlesList from './components/BundlesList.vue'
import UpdateList  from './components/UpdateList.vue'
import InstalledApps from './components/InstalledApps.vue'

// Store
import store from './store'

// Routing
const routes = [
    {
        path: '/app/:id',
        component: Details,
        name: 'details'
    }, {
        path: '/by/category/:category',
        component: List,
        name: 'byCategory'
    }, {
        path: '/installed',
        component: InstalledApps,
        name: 'InstalledApps'
    }, {
        path: '/bundles',
        component: BundlesList,
        name: 'Bundles'
    }, {
        path: '/updates',
        component: UpdateList,
        name: 'UpdateList'
    }, {
        path: '/',
        component: List,
        name: 'index'
    }
];

const router = new VueRouter({
    routes
});

// Nach einem Ansichtswechsel (Kachel, „Zurück“, Kategorie) stand der Fokus auf dem
// Dokument, der nächste Tab begann am Logo (WCAG 2.4.3). Er geht an die Überschrift der
// neuen Ansicht, sobald sie gezeichnet ist (Details lädt nach). Der Fenstertitel nennt
// die Ansicht (WCAG 2.4.2), auch beim ersten Aufruf.
const grundTitel = document.title;

function ueberschriftAbwarten (handler) {
    const finden = () => document.querySelector('#market-app .bwt-shell__main h1, #market-app .bwt-shell__main h2');
    const sofort = finden();
    if (sofort) {
        handler(sofort);
        return;
    }
    const wache = new MutationObserver(() => {
        const h = finden();
        if (h) {
            wache.disconnect();
            clearTimeout(frist);
            handler(h);
        }
    });
    // documentElement statt body: Den ersten Routenwechsel meldet der Router schon beim
    // Erzeugen der App im Kopf der Seite, dann gibt es body noch nicht.
    wache.observe(document.documentElement, { childList: true, subtree: true });
    const frist = setTimeout(() => wache.disconnect(), 10000);
}

router.afterEach((to, from) => {
    const fokus = from.matched.length > 0;
    Vue.nextTick(() => ueberschriftAbwarten((h) => {
        const name = h.textContent.trim();
        if (name) {
            document.title = name + ' - ' + grundTitel;
        }
        if (fokus) {
            h.setAttribute('tabindex', '-1');
            h.focus();
        }
    }));
});

// The App itself
const MarketApp = new Vue({
    router,
    store,
    render: h => h(App)
});

// --------------------------------------------------------------- Vue mount ---

// Need to wait for window to load
window.onload = () => MarketApp.$mount('.app-market');
