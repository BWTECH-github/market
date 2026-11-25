<template lang="pug">
	#market-app
		//- Bis Einstellungen, Kategorien und Apps da sind, steht nur der Ladekreisel.
		//- Vorher kam jedes Stück für sich und schob das schon Gezeichnete weiter:
		//- die Kategorien die Einträge darunter (bei 390 px die ganze Liste), die
		//- Zähler die Seitenleiste, die Antworten die Kacheln (Layout-Verschiebung
		//- 0,07 bei 1440 px, 0,10 bei 390 px).
		.uk-position-fixed.uk-position-center(v-if="!ready", uk-spinner, uk-icon="icon: spinner")
		.bwt-shell(v-else)
			.bwt-shell__notice(v-if="showNotice").uk-animation-slide-top-small
				.uk-alert-primary(uk-alert)
					span.uk-alert-close(uk-close, @click.prevent="noticeDismissed = true")
					h1.uk-h5 {{ t("Installing and updating Apps is not supported!") }}
					p {{ t("This is a clustered setup or the web server has no permissions to write to the apps folder.") }}
			aside.bwt-shell__sidebar
				navigation
			main.bwt-shell__main
				router-view
</template>

<script>
	// Modified by BW-Tech GmbH for owncloud.online (PHP 8.4).

	import Mixins from './mixins.js'
	import Navigation from './components/Navigation.vue'

	export default {
		mixins: [Mixins],
		data () {
			return {
				"noticeDismissed" : false,
				ready: false
			}
		},
		mounted () {
			// Die Anfragen melden ihre Fehler selbst; hier zählt nur, dass sie
			// fertig sind. Antwortet der Marktplatz nicht, erscheint die Seite
			// nach vier Sekunden trotzdem - Navigation und Support-Vertrag
			// bleiben erreichbar, Nachzügler verschieben dann eben noch.
			const anfragen = [
				this.$store.dispatch('FETCH_CONFIG'),
				this.$store.dispatch('FETCH_CATEGORIES'),
				this.$store.dispatch('REFRESH_MARKET')
			].map((anfrage) => Promise.resolve(anfrage).catch(() => {}));
			const frist = setTimeout(() => { this.ready = true; }, 4000);
			Promise.all(anfragen).then(() => {
				clearTimeout(frist);
				this.ready = true;
			});

			this.$store.watch(
				(state)  => {
					return state.installed;
				},
				() => {
					this.$store.dispatch('REBUILD_NAVIGATION');
				}
			);
		},
		computed: {
			config () {
				return this.$store.getters.config
			},
			showNotice() {
				return this.noticeDismissed === false && this.config.canInstall === false
			}
		},
		methods: { },
		components: {
			Navigation
		}
	}
</script>

<style lang="scss" scoped>
	#market-app {
		min-height: 100%;
	}
</style>
