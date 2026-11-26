import translations from '../l10n/translations.json'

// Übersetzungen des Markts ohne vue-gettext (das Plugin gibt es nur für Vue 2).
// Die Kataloge bleiben, wie sie sind: l10n/translations.json, je Sprache
// msgid -> Text. Gebraucht werden $gettext und $gettextInterpolate.

// Sprache aus <html lang>: der Kern schreibt sie mit Bindestrich ("de-DE",
// "de-AT"), die Kataloge heißen "de_DE" usw. Fehlt der Katalog der Region,
// gilt der der Sprache (de_AT -> de).
const language = (OC.getLocale() || 'en').replace(/-/g, '_')
const catalog = translations[language] || translations[language.split('_')[0]] || {}

const HTML_ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }

// Text in der Sprache der Oberfläche; ohne Übersetzung der Text selbst.
export function gettext (msgid) {
	if (!msgid) {
		return ''
	}
	return catalog[msgid.trim()] || msgid
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
