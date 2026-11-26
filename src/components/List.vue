<template lang="pug">
	div
		.uk-position-fixed.uk-position-center(v-show="loading", uk-spinner, uk-icon="icon: spinner")

		//- Trefferzahl der Suche für Sprachausgaben (WCAG 4.1.3); die Liste ändert sich
		//- beim Tippen, der Fokus bleibt im Suchfeld
		p.hidden-visually(role="status") {{ searchStatus }}

		header.bwt-page-head(v-if="!loading && !failed")
			div
				h1.bwt-page-head__title(:lang="pageTitleLang") {{ pageTitle }}
				p.bwt-page-head__subtitle(v-if="searchQuery") {{ t('Showing results for "%{query}"', { query: searchQuery }) }}
				p.bwt-page-head__subtitle(v-else-if="category") {{ t('Apps in %{category}', { category: categoryDisplayName }) }}
				p.bwt-page-head__subtitle(v-else) {{ t('Browse and install apps for your owncloud.online') }}

		//- Kacheln blenden aus und ein, wenn Suche oder Kategorie die Liste ändern -
		//- nicht beim ersten Aufbau der Ansicht (appear erst danach).
		transition-group.bwt-app-grid(v-if="!loading && !failed && applications.length", name="fade", tag="ul", :appear="mounted")
			Tile(v-for="application in applications", :application="application", :key="application.id")

		transition(name="fade")
			.uk-card.uk-card-default.uk-card-body.bwt-empty(v-if="applications.length === 0 && !loading && !failed")
				p.uk-text-center(v-if="searchQuery") {{ t('No apps match "%{query}"', { query: searchQuery }) }}
				p.uk-text-center(v-else) {{ t('No apps in %{category}', { category: categoryDisplayName }) }}
</template>

<script>
	import Mixins from '../mixins';
	import Tile from './Tile.vue';

	export default {
		mixins: [Mixins],
		components: {
			Tile
		},
		data () {
			return {
				mounted: false
			}
		},
		mounted () {
			this.mounted = true
		},
		computed: {
			loading() {
				return this.$store.state.applications.loading
			},
			failed() {
				return this.$store.state.applications.failed
			},
			applications() {
				if (this.loading || this.failed) {
					return []
				}
				return this.$store.getters.applications(this.category)
			},
			category() {
				return this.$route.params.category
			},
			searchQuery () {
				return this.$store.getters.searchQuery
			},
			pageTitle () {
				if (this.searchQuery) {
					return this.t('Search')
				}
				if (this.category) {
					return this.categoryDisplayName
				}
				return this.t('Discover')
			},
			categoryRecord () {
				return this.category ? this.$store.getters.category(this.category) : null
			},
			categoryDisplayName () {
				return this.categoryLabel(this.categoryRecord || { id: this.category })
			},
			pageTitleLang () {
				return (this.category && !this.searchQuery) ? this.categoryLang(this.categoryRecord || { id: this.category }) : undefined
			},
			searchStatus () {
				if (!this.searchQuery || this.loading || this.failed) {
					return ''
				}
				return this.t('Results: %{count}', { count: this.applications.length })
			}
		},
	}
</script>

<style lang="scss" scoped>
	@import "../styles/variables-theme";
</style>
