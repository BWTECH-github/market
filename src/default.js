import "core-js/stable";
require('./styles/theme.scss');

// -------------------------------------------------------- Uikit components ---
import UIkit from 'uikit';
import Icons from 'uikit/dist/js/uikit-icons';

// Modified by BW-Tech GmbH for owncloud.online (PHP 8.4).

UIkit.use(Icons);

// UIkit bringt eigene englische Texte mit: die Knöpfe seiner Dialoge („Ok“,
// „Cancel“) und die Beschriftung der Schließen-Kreuze („Close“, auch an jeder
// Meldung unten rechts). Sie kommen aus denselben Katalogen wie der Rest.
import { gettext as t } from './gettext';

UIkit.modal.i18n = { ok: t('OK'), cancel: t('Cancel') };
UIkit.mixin({ i18n: { label: t('Close') } }, 'close');

// ------------------------------------------------------------- Vue plugins ---

import { createApp, nextTick } from 'vue'
import { createRouter, createWebHashHistory } from 'vue-router'

import gettext from './gettext'

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

const router = createRouter({
    history: createWebHashHistory(),
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

router.afterEach((to, from, failure) => {
    // Ein Klick auf die Ansicht, die schon offen ist, wechselt nichts: Fokus und
    // Titel bleiben.
    if (failure) {
        return;
    }
    const fokus = from.matched.length > 0;
    nextTick(() => ueberschriftAbwarten((h) => {
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
const MarketApp = createApp(App)
    .use(router)
    .use(store)
    .use(gettext);

// --------------------------------------------------------------- Vue mount ---

// Need to wait for window to load
// Die Anwendung ersetzt ihr Ziel (#content, der Kern gibt ihm die Klasse
// app-market), wie es Vue 2 tat: Gerüst und Stile des Kerns rechnen mit
// #content-wrapper > #market-app. Vue 3 hängt sie sonst in das Ziel hinein.
window.onload = () => {
    const halter = document.createDocumentFragment();
    MarketApp.mount(halter);
    document.querySelector('.app-market').replaceWith(halter);
};
