import showdown from "showdown";

// Positivliste für Marketplace-Markdown (description/changelog). showdown
// reicht rohes HTML ungefiltert durch; eine Sperrliste ließ Inline-Stile,
// fremde Attribute, noscript- und SVG-Tricks stehen. Jetzt bleibt nur, was
// showdown für Markdown selbst erzeugt, ohne style, class und id.
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
		// und Detailseite - vorher stand dieselbe Funktion viermal in leicht
		// verschiedener Form da. Kategorien, die der Marktplatz an Apps vergibt,
		// aber nicht in seiner Liste führt (storage, productivity), kamen dabei
		// als Rohkennung „storage“ heraus; jetzt mit großem Anfangsbuchstaben wie
		// die übrigen Namen („Storage“). Sie sind englisch wie diese (categoryLang).
		categoryLabel (category) {
			if (!category) {
				return '';
			}
			if (category.translations) {
				const sprache = (typeof OC !== 'undefined' && OC.getLocale) ? OC.getLocale().slice(0, 2) : 'en';
				const uebersetzt = category.translations[sprache] || category.translations.en;
				if (uebersetzt && uebersetzt.name) {
					return uebersetzt.name;
				}
			}
			const name = category.name || category.displayName || category.label;
			if (name) {
				return String(name);
			}
			const kennung = String(category.id || '').replace(/[-_]+/g, ' ').trim();
			return kennung.charAt(0).toUpperCase() + kennung.slice(1);
		},
		// Sprache eines Kategorienamens (WCAG 3.1.2): fehlt die Übersetzung in die
		// Sprache der Oberfläche, kommt der Name englisch aus dem Marktplatz und wird
		// als englisch ausgezeichnet. undefined lässt das Attribut weg.
		categoryLang (category) {
			const sprache = (typeof OC !== 'undefined' && OC.getLocale) ? OC.getLocale().slice(0, 2) : 'en';
			if (sprache === 'en' || !category) {
				return undefined;
			}
			const uebersetzt = category.translations && category.translations[sprache];
			return (uebersetzt && uebersetzt.name) ? undefined : 'en';
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

			let converter = new showdown.Converter();
			let markdown  = converter.makeHtml(string);

			return sanitizeHtml(markdown);
		}
	}
}
