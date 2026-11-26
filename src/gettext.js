import translations from '../l10n/translations.json'

// Übersetzungen des Markts ohne vue-gettext (das Plugin gibt es nur für Vue 2).
// Die Kataloge bleiben, wie sie sind: l10n/translations.json, je Sprache
// msgid -> Text. Gebraucht werden $gettext und $gettextInterpolate.

// Sprache aus <html lang>: der Kern schreibt sie mit Bindestrich ("de-DE",
// "de-AT"), die Kataloge heißen "de_DE" usw. Erst gilt der Katalog der Region,
// dann der der Sprache (de_AT -> de): Auch ein einzelner Text, der in de_CH
// fehlt, kommt so deutsch statt englisch.
const language = (OC.getLocale() || 'en').replace(/-/g, '_')
const catalogs = [...new Set([translations[language], translations[language.split('_')[0]]])].filter(Boolean)

const HTML_ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }

function lookup (msgid) {
	const key = msgid.trim()
	for (const catalog of catalogs) {
		if (catalog[key]) {
			return catalog[key]
		}
	}
	return null
}

// Text in der Sprache der Oberfläche; ohne Übersetzung der Text selbst.
export function gettext (msgid) {
	if (!msgid) {
		return ''
	}
	return lookup(msgid) || msgid
}

// Gibt es den Text in der Sprache der Oberfläche? Ohne Übersetzung bleibt er
// englisch und wird so ausgezeichnet (lang="en").
export function hasTranslation (msgid) {
	return !!msgid && lookup(msgid) !== null
}

// Füllt %{name} aus values. Die Werte werden für HTML maskiert, weil einige
// Texte über v-html in die Seite gehen.
export function interpolate (text, values) {
	return text.replace(/%\{((?:.|\n)+?)\}/g, (match, token) => {
		const name = token.trim()
		const value = values[name] === undefined ? name : values[name]
		return String(value).replace(/[&<>"']/g, (char) => HTML_ESCAPES[char])
	})
}

export default {
	install (app) {
		app.config.globalProperties.$gettext = gettext
		app.config.globalProperties.$gettextInterpolate = interpolate
	}
}
