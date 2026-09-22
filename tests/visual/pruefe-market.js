/**
 * Markt im Redesign, Ende zu Ende.
 *
 * Keine Testdaten nötig; die Probe ändert an der Instanz nur den Markt-Cache
 * („Cache leeren“). Installieren und Entfernen werden nicht echt ausgeführt –
 * das tauschte Apps der Testinstanz gegen Katalogstände. Der Neuaufbau der
 * Seitenleiste danach wird stattdessen mit einer umgelenkten Antwort von
 * navigationdetect.php durchgespielt (eine App mehr, eine weniger).
 *
 * Geprüft wird (1440 px):
 *   - Entdecken, Suche, Kategorie, Details, Installierte Apps, Updates,
 *     App-Bundles laden ohne Fehler
 *   - Neuaufbau der Seitenleiste nach Installieren/Entfernen: #apps (Start,
 *     Dateien) bleibt unberührt, die Gruppe „Menü“ bekommt die neue App bzw.
 *     verliert die entfernte, Theming bleibt, nichts steht doppelt
 *   - API-Schlüssel-Dialog liegt über allem
 *   - „Cache leeren“ meldet sich sichtbar
 * und (400 px):
 *   - Meldung steht über der Reiterleiste, nicht dahinter
 *   - Dialog liegt über der Reiterleiste
 *   - kein waagerechtes Rollen, keine Konsolenfehler
 *
 * Aufruf: OC_PASSWORD=... node tests/visual/pruefe-market.js
 *   OC_URL (Standard http://127.0.0.1:18130), OC_USER (Standard admin)
 *
 * @copyright Copyright (c) 2026, BW-Tech GmbH
 * @license AGPL-3.0
 */
'use strict';

let chromium;
try {
	({ chromium } = require('playwright'));
} catch (e) {
	({ chromium } = require('C:/git/owncloud.online-redesign/node_modules/playwright'));
}

const BASIS = process.env.OC_URL || 'http://127.0.0.1:18130';
const BENUTZER = process.env.OC_USER || 'admin';
const PASSWORT = process.env.OC_PASSWORD;
if (!PASSWORT) {
	console.error('OC_PASSWORD fehlt.');
	process.exit(2);
}

const ergebnisse = [];
function pruefe(name, ok, zusatz) {
	ergebnisse.push({ name, ok: ok === true, zusatz: zusatz === undefined ? '' : String(zusatz) });
}

function seitenfehler(e) {
	const stelle = ((e.stack || '').split('\n')[1] || '').trim().replace(BASIS, '').slice(0, 140);
	return 'Seitenfehler: ' + e.message.slice(0, 160) + (stelle ? ' ' + stelle : '');
}

async function seiteOeffnen(browser, breite, hoehe) {
	const kontext = await browser.newContext({ locale: 'de-DE', viewport: { width: breite, height: hoehe } });
	const seite = await kontext.newPage();
	const fehler = [];
	seite.on('console', (m) => {
		if (m.type() === 'error') {
			fehler.push(m.text().slice(0, 160) + ' @ ' + (m.location().url || '').replace(BASIS, '').slice(0, 100));
		}
	});
	seite.on('pageerror', (e) => fehler.push(seitenfehler(e)));
	await seite.goto(BASIS + '/index.php/login', { waitUntil: 'domcontentloaded' });
	await seite.fill('#user', BENUTZER);
	await seite.fill('#password', PASSWORT);
	await Promise.all([seite.waitForNavigation({ timeout: 30000 }).catch(() => {}), seite.click('#submit, button[type=submit]')]);
	await seite.goto(BASIS + '/index.php/apps/market/', { waitUntil: 'load' });
	await seite.waitForSelector('#market-app .bwt-shell', { timeout: 30000 });
	await seite.waitForTimeout(2500);
	return { kontext, seite, fehler };
}

function seitenleiste(seite) {
	return seite.evaluate(() => ({
		fest: Array.from(document.querySelectorAll('#apps ul > li')).map((l) => ({
			id: l.getAttribute('data-id'),
			aktuell: l.classList.contains('oco-current-app'),
			symbol: (l.querySelector('img') || {}).src || '',
		})),
		menue: Array.from(document.querySelectorAll('#oco-apps-liste > li')).map((l) => ({
			name: l.textContent.trim(),
			href: (l.querySelector('a') || {}).getAttribute ? l.querySelector('a').getAttribute('href') : '',
			aktiv: !!l.querySelector('a.active'),
			symbol: !!l.querySelector('img[alt=""]'),
		})),
	}));
}

function neuaufbau(seite) {
	return seite.evaluate(() => new Promise((fertig) => {
		const store = document.querySelector('#market-app').__vue__.$store;
		store.dispatch('REBUILD_NAVIGATION');
		setTimeout(fertig, 1500);
	}));
}

// Liegt an diesen Punkten das Element (oder etwas darin) obenauf?
function obenauf(seite, wahl, punkte) {
	return seite.evaluate(({ w, p }) => {
		const ziel = document.querySelector(w);
		if (!ziel) {
			return 'fehlt';
		}
		const r = ziel.getBoundingClientRect();
		const orte = p || [[r.left + r.width / 2, r.top + r.height / 2]];
		return orte.every((o) => {
			const e = document.elementFromPoint(o[0], o[1]);
			return !!e && ziel.contains(e);
		});
	}, { w: wahl, p: punkte });
}

(async () => {
	const browser = await chromium.launch();

	// ======================================================================
	// 1440 px
	// ======================================================================
	const g = await seiteOeffnen(browser, 1440, 900);
	const s = g.seite;
	const kacheln = await s.locator('#market-app .bwt-shell__main .bwt-tile').count();
	pruefe('Entdecken zeigt Apps', kacheln > 0, kacheln + ' Kacheln');

	// Suche
	const suche = s.locator('#market-app input[type="search"], #market-app input[type="text"]').first();
	await suche.fill('audit');
	await s.waitForTimeout(1200);
	const treffer = await s.evaluate(() => document.querySelector('#market-app .bwt-shell__main').textContent);
	pruefe('Suche filtert (Audit Log gefunden)', /Audit Log/.test(treffer) && !/2-Factor Authentication/.test(treffer), treffer.replace(/\s+/g, ' ').slice(0, 120));
	await suche.fill('');
	await s.waitForTimeout(800);

	// Kategorie
	await s.locator('#market-app .bwt-shell__sidebar a:has-text("Security")').first().click();
	await s.waitForTimeout(1500);
	const kat = await s.evaluate(() => document.querySelector('#market-app .bwt-shell__main').textContent);
	pruefe('Kategorie „Security“ listet Apps', /Brute-Force|Anti-Virus|2-Factor/.test(kat), kat.replace(/\s+/g, ' ').slice(0, 100));

	// Details
	await s.locator('#market-app .bwt-shell__main .bwt-tile__overlay').first().click();
	await s.waitForTimeout(2000);
	const details = await s.evaluate(() => {
		const m = document.querySelector('#market-app .bwt-shell__main');
		return { titel: (m.querySelector('h1, h2') || {}).textContent, knoepfe: Array.from(m.querySelectorAll('button, .uk-button')).map((b) => b.textContent.trim()).join('/') };
	});
	pruefe('Details einer App mit Aktion', /2-Factor/.test(details.titel || '') && /Deinstallieren|Installieren|Aktualisieren|Uninstall|Install|Update/.test(details.knoepfe), JSON.stringify(details));

	// Installierte Apps, Updates, Bundles
	await s.locator('#market-app .bwt-shell__sidebar a:has-text("Installierte Apps")').first().click();
	await s.waitForTimeout(2500);
	const zeilen = await s.locator('#market-app .bwt-shell__main tbody tr').count();
	pruefe('Installierte Apps: Tabelle gefüllt', zeilen > 10, zeilen + ' Zeilen');
	await s.locator('#market-app .bwt-shell__sidebar a:has-text("Updates")').first().click();
	await s.waitForTimeout(2500);
	const upd = await s.evaluate(() => document.querySelector('#market-app .bwt-shell__main').textContent.replace(/\s+/g, ' '));
	pruefe('Updates lädt', /Updates/.test(upd) && /aktuell|neuere|up to date|newer/i.test(upd), upd.slice(0, 120));
	await s.locator('#market-app .bwt-shell__sidebar a:has-text("App-Bundles")').first().click();
	await s.waitForTimeout(2500);
	const bnd = await s.evaluate(() => {
		const m = document.querySelector('#market-app .bwt-shell__main');
		return { kopf: (m.querySelector('.bwt-page-head__title') || {}).textContent, text: m.textContent.replace(/\s+/g, ' ').slice(0, 120), buendel: m.querySelectorAll('.bwt-bundle').length };
	});
	pruefe('App-Bundles: Kopf und Bundles oder Leerhinweis', !!bnd.kopf && (bnd.buendel > 0 || /Keine Bundles|No Bundles/.test(bnd.text)), JSON.stringify(bnd));

	// --- Neuaufbau der Seitenleiste ---------------------------------------
	const vorher = await seitenleiste(s);
	const echteAntwort = await s.evaluate(async () => (await fetch(OC.filePath('settings', 'ajax', 'navigationdetect.php'), { headers: { requesttoken: OC.requestToken } })).json());
	await neuaufbau(s);
	const unveraendert = await seitenleiste(s);
	pruefe('Neuaufbau: #apps (Start, Dateien) bleibt unberührt', JSON.stringify(unveraendert.fest) === JSON.stringify(vorher.fest), JSON.stringify(unveraendert.fest));
	pruefe('Neuaufbau: Gruppe „Menü“ unverändert (gleiche Einträge, Reihenfolge)', JSON.stringify(unveraendert.menue.map((m) => m.name)) === JSON.stringify(vorher.menue.map((m) => m.name)), unveraendert.menue.map((m) => m.name).join(','));
	pruefe('Neuaufbau: Markt aktiv, Symbole wie im Kern', unveraendert.menue.filter((m) => m.aktiv).map((m) => m.name).join() === 'Markt' && unveraendert.menue.every((m) => m.symbol), JSON.stringify(unveraendert.menue.filter((m) => m.aktiv)));
	const festeIds = vorher.fest.map((f) => f.id);
	const doppelt = unveraendert.menue.filter((m) => /\/apps\/(dashboard|files)\/?$/.test(m.href || ''));
	pruefe('Neuaufbau: Start und Dateien stehen nicht doppelt', doppelt.length === 0 && festeIds.join() === 'dashboard,files', doppelt.map((m) => m.name).join(','));

	// „Installieren“: eine App mehr, „Entfernen“: Galerie weg
	const eintraege = echteAntwort.nav_entries;
	const neu = { id: 'probeapp', name: 'Probe-App', href: '/index.php/apps/probeapp/', icon: eintraege[eintraege.length - 1].icon, order: 99 };
	await s.route('**/settings/ajax/navigationdetect.php*', (r) => r.fulfill({ contentType: 'application/json', body: JSON.stringify({ status: 'success', data: { nav_entries: eintraege.concat([neu]) }, nav_entries: eintraege.concat([neu]) }) }));
	await neuaufbau(s);
	const mitNeu = await seitenleiste(s);
	pruefe('Neuaufbau nach Installieren: neue App in „Menü“, Theming bleibt hinten', mitNeu.menue.some((m) => m.name === 'Probe-App') && mitNeu.menue[mitNeu.menue.length - 1].name === 'Theming', mitNeu.menue.map((m) => m.name).join(','));
	await s.unroute('**/settings/ajax/navigationdetect.php*');
	const ohneGalerie = eintraege.filter((e) => e.id !== 'gallery');
	await s.route('**/settings/ajax/navigationdetect.php*', (r) => r.fulfill({ contentType: 'application/json', body: JSON.stringify({ status: 'success', data: { nav_entries: ohneGalerie }, nav_entries: ohneGalerie }) }));
	await neuaufbau(s);
	const ohne = await seitenleiste(s);
	pruefe('Neuaufbau nach Entfernen: App verschwindet aus „Menü“', !ohne.menue.some((m) => m.name === 'Galerie' || m.name === 'Gallery') && !ohne.menue.some((m) => m.name === 'Probe-App'), ohne.menue.map((m) => m.name).join(','));
	pruefe('Neuaufbau: #apps auch danach unberührt', JSON.stringify(ohne.fest) === JSON.stringify(vorher.fest), JSON.stringify(ohne.fest));
	await s.unroute('**/settings/ajax/navigationdetect.php*');
	await neuaufbau(s);

	// --- API-Schlüssel-Dialog ---------------------------------------------------
	await s.locator('#market-app .bwt-shell__sidebar a:has-text("API")').first().click();
	await s.waitForSelector('#edit-api-key.uk-open', { timeout: 5000 }).catch(() => {});
	await s.waitForTimeout(600);
	pruefe('API-Schlüssel-Dialog öffnet und liegt obenauf', await obenauf(s, '#edit-api-key .uk-modal-dialog') === true);
	await s.keyboard.press('Escape');
	await s.waitForTimeout(600);

	// --- Cache leeren -------------------------------------------------------------
	await s.locator('#market-app .bwt-shell__sidebar a:has-text("Cache")').first().click();
	await s.waitForSelector('.uk-notification-message', { timeout: 10000 }).catch(() => {});
	await s.waitForTimeout(400);
	pruefe('„Cache leeren“: Meldung sichtbar und obenauf', await obenauf(s, '.uk-notification-message') === true);
	pruefe('keine Konsolenfehler (1440)', g.fehler.length === 0, g.fehler.join(' | '));
	await g.kontext.close();

	// ======================================================================
	// 400 px
	// ======================================================================
	const m = await seiteOeffnen(browser, 400, 800);
	const t = m.seite;
	const breite = await t.evaluate(() => document.documentElement.scrollWidth);
	pruefe('400 px: kein waagerechtes Rollen', breite <= 400, breite);
	// Meldung auslösen wie der Markt selbst (Cache leeren sitzt weit unten in
	// der Seitenleiste des Marktes)
	await t.locator('#market-app .bwt-shell__sidebar a:has-text("Cache")').first().scrollIntoViewIfNeeded();
	await t.locator('#market-app .bwt-shell__sidebar a:has-text("Cache")').first().click();
	await t.waitForSelector('.uk-notification-message', { timeout: 10000 }).catch(() => {});
	await t.waitForTimeout(400);
	const lage = await t.evaluate(() => {
		const n = document.querySelector('.uk-notification-message');
		const l = document.querySelector('.oco-tabbar');
		return n && l ? { unten: Math.round(n.getBoundingClientRect().bottom), leiste: Math.round(l.getBoundingClientRect().top) } : null;
	});
	pruefe('400 px: Meldung über der Reiterleiste', !!lage && lage.unten <= lage.leiste, JSON.stringify(lage));
	pruefe('400 px: Meldung obenauf', await obenauf(t, '.uk-notification-message') === true);
	const ebenen = await t.evaluate(() => {
		const leiste = parseInt(getComputedStyle(document.querySelector('.oco-tabbar')).zIndex, 10);
		return ['uk-dropdown', 'uk-tooltip', 'uk-modal'].map((k) => {
			const e = document.createElement('div');
			e.className = k;
			document.body.appendChild(e);
			const z = parseInt(getComputedStyle(e).zIndex, 10);
			e.remove();
			return { k, z, ueber: z > leiste };
		});
	});
	pruefe('400 px: Aufklapper, Hinweisblasen und Dialoge über der Reiterleiste', ebenen.every((e) => e.ueber), JSON.stringify(ebenen));
	await t.waitForTimeout(5500);
	await t.locator('#market-app .bwt-shell__sidebar a:has-text("API")').first().scrollIntoViewIfNeeded();
	await t.locator('#market-app .bwt-shell__sidebar a:has-text("API")').first().click();
	await t.waitForSelector('#edit-api-key.uk-open', { timeout: 5000 }).catch(() => {});
	await t.waitForTimeout(600);
	// Mitten in der Reiterleiste darf nur noch der Dialog (samt Abdunklung) liegen
	pruefe('400 px: Dialog liegt über der Reiterleiste', await obenauf(t, '#edit-api-key', [[200, 790], [30, 790]]) === true);
	await t.keyboard.press('Escape');
	pruefe('keine Konsolenfehler (400)', m.fehler.length === 0, m.fehler.join(' | '));
	await m.kontext.close();

	await browser.close();
	let fehler = 0;
	for (const e of ergebnisse) {
		console.log((e.ok ? 'OK    ' : 'FEHL  ') + e.name + (e.zusatz ? '  (' + e.zusatz + ')' : ''));
		if (!e.ok) {
			fehler++;
		}
	}
	console.log('\n' + (ergebnisse.length - fehler) + '/' + ergebnisse.length + ' bestanden');
	process.exit(fehler === 0 ? 0 : 1);
})().catch((e) => {
	console.error(e);
	process.exit(2);
});
