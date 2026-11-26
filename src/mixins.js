import MarkdownIt from "markdown-it";
import { gettext, hasTranslation } from "./gettext";

// Kategorien des Marktplatzes. categories.json liefert nur Kennung und
// englischen Namen, Apps tragen dazu Kennungen, die dort fehlen (storage,
// productivity). Diese Namen übersetzt l10n/translations.json; eine Kennung,
// die hier fehlt, behält den Namen aus dem Katalog.
const CATEGORY_NAMES = {
	"automation": "Automation",
	"collaboration": "Collaboration",
	"customization": "Customization",
	"files": "Files",
	"games": "Games",
	"integration": "Integration",
	"multimedia": "Multimedia",
	"office": "Office",
	"productivity": "Productivity",
	"security": "Security",
	"storage": "Storage",
	"tools": "Tools",
	"user-authentication": "User Authentication"
};

// Sprache der Oberfläche ohne Region (de_DE -> de)
function uiLanguage () {
	return (typeof OC !== "undefined" && OC.getLocale) ? String(OC.getLocale() || "en").slice(0, 2) : "en";
}

// Englischer Name einer bekannten Kennung, sonst null.
function knownName (category) {
	return Object.prototype.hasOwnProperty.call(CATEGORY_NAMES, category.id) ? CATEGORY_NAMES[category.id] : null;
}

// Name in der Sprache der Oberfläche: aus dem Katalog (Format mit
// translations wie der lokale Katalog), sonst die Übersetzung der bekannten
// Kennung. null, wenn es keinen gibt.
function translatedName (category) {
	const katalog = category.translations && category.translations[uiLanguage()];
	if (katalog && katalog.name) {
		return String(katalog.name);
	}
	const msgid = knownName(category);
	return (msgid && hasTranslation(msgid)) ? gettext(msgid) : null;
}

// Markdown des Marktplatzes (description, changelog): CommonMark ohne rohes
// HTML - Auszeichnung im Text erscheint als Text, nicht als Element.
const converter = new MarkdownIt("commonmark", { html: false });

// Positivliste als zweite Lage hinter dem Wandler: Es bleibt nur, was für
// Markdown entsteht, ohne style, class und id.
const ALLOWED_TAGS = [
	"P", "BR", "HR", "EM", "STRONG", "B", "I", "DEL", "S", "SUP", "SUB",
	"CODE", "PRE", "BLOCKQUOTE", "UL", "OL", "LI",
	"H1", "H2", "H3", "H4", "H5", "H6",
	"A", "IMG", "TABLE", "THEAD", "TBODY", "TR", "TH", "TD"
];

// Elemente, deren Inhalt auch als Text nichts in der Beschreibung verloren hat.
const DROPPED_TAGS = [
	"SCRIPT", "STYLE", "TEMPLATE", "NOSCRIPT", "IFRAME", "OBJECT", "EMBED",
	"SVG", "MATH", "TEXTAREA", "TITLE", "XMP", "NOEMBED", "NOFRAMES", "PLAINTEXT"
];

// Erlaubte Attribute je Element; alle übrigen fallen weg.
const ALLOWED_ATTRIBUTES = {
	A: ["href", "title"],
	IMG: ["src", "alt", "title"]
};

// Ziele: Links über http(s) oder mailto, Bilder nur über https.
function isAllowedUrl (tag, value) {
	const url = String(value).replace(/[\x00-\x20]/g, "");
	if (tag === "IMG") {
		return /^https:\/\//i.test(url);
	}
	return /^(https?:\/\/|mailto:)/i.test(url);
}

// Filtert in einem Dokument ohne Browsing-Kontext (createHTMLDocument): dort
// lädt kein Bild und läuft kein Skript. Nicht erlaubte Elemente werden durch
// ihren reinen Text ersetzt, DROPPED_TAGS ganz entfernt.
function sanitizeHtml (html) {
	const doc = document.implementation.createHTMLDocument("");
	doc.body.innerHTML = html;

	// Rückwärts: Kinder vor ihren Eltern, dann bleibt beim Ersetzen eines
	// Elternteils durch Text nichts Ungeprüftes übrig.
	const elements = Array.prototype.slice.call(doc.body.querySelectorAll("*")).reverse();
	elements.forEach(function (element) {
		const tag = element.nodeName.toUpperCase();
		if (!element.parentNode) {
			return;
		}
		if (DROPPED_TAGS.indexOf(tag) !== -1) {
			element.parentNode.removeChild(element);
			return;
		}
		if (ALLOWED_TAGS.indexOf(tag) === -1) {
			element.parentNode.replaceChild(doc.createTextNode(element.textContent), element);
			return;
		}
		const allowed = ALLOWED_ATTRIBUTES[tag] || [];
		Array.prototype.slice.call(element.attributes).forEach(function (attribute) {
			const name = attribute.name.toLowerCase();
			if (allowed.indexOf(name) === -1
				|| ((name === "href" || name === "src") && !isAllowedUrl(tag, attribute.value))) {
				element.removeAttribute(attribute.name);
			}
		});
		if (tag === "A" && element.hasAttribute("href")) {
			element.setAttribute("rel", "noopener noreferrer");
		}
	});

	return doc.body.innerHTML;
}

export default {
	methods: {
		// Name einer Kategorie für Seitenleiste, Kacheln, Kopf der Kategorieansicht
		// und Detailseite. Reihenfolge: Name in der Sprache der Oberfläche
		// (translatedName), englischer Name aus dem Katalog, englischer Name der
		// bekannten Kennung, Kennung mit großem Anfangsbuchstaben („Storage“
		// statt „storage“).
		categoryLabel (category) {
			if (!category) {
				return '';
			}
			const uebersetzt = translatedName(category);
			if (uebersetzt) {
				return uebersetzt;
			}
			const englisch = category.translations && category.translations.en;
			const name = (englisch && englisch.name) || category.name || category.displayName || category.label
				|| knownName(category);
			if (name) {
				return String(name);
			}
			const kennung = String(category.id || '').replace(/[-_]+/g, ' ').trim();
			return kennung.charAt(0).toUpperCase() + kennung.slice(1);
		},
		// Sprache eines Kategorienamens (WCAG 3.1.2): Ohne Übersetzung in die
		// Sprache der Oberfläche bleibt der Name englisch und wird als englisch
		// ausgezeichnet. undefined lässt das Attribut weg.
		categoryLang (category) {
			if (uiLanguage() === 'en' || !category) {
				return undefined;
			}
			return translatedName(category) ? undefined : 'en';
		},
		t (string, interpolation) {
			if (!interpolation) {
				return this.$gettext(string);
			}
			else {
				// Erst übersetzen, dann %{platzhalter} füllen: $gettextInterpolate
				// allein übersetzt nicht.
				return this.$gettextInterpolate(this.$gettext(string), interpolation);
			}
		},
		// mailto-Link für „Support-Vertrag anfragen“; der Host im Text sagt dem
		// Support, um welches System es geht.
		supportMailto (support, subject) {
			const email = (support && support.email) || "support@owncloudonline.com";
			const body = "System: " + window.location.host;
			return "mailto:" + email
				+ "?subject=" + encodeURIComponent(subject)
				+ "&body=" + encodeURIComponent(body);
		},
		markdown (string) {
			if (!string) {
				return "";
			}

			return sanitizeHtml(converter.render(string));
		}
	}
}
