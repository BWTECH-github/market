<template lang="pug">
	div
		.uk-position-fixed.uk-position-center(v-show="loading", uk-spinner, uk-icon="icon: spinner")
		ul.uk-grid.uk-grid-match.uk-child-width-1-2\@m.uk-child-width-1-3\@xl(v-if="!loading && !failed && applications.length", uk-grid)
			Tile(v-for="application in applications", :application="application", :key="application.id")

		transition(name="fade")
			.uk-card.uk-card-default.uk-card-body.uk-position-center(v-if="applications.length === 0 && !loading && !failed")
				p.uk-text-center(v-if="searchQuery") {{ t('No apps match "%{query}"', { query: searchQuery }) }}
				p.uk-text-center(v-else) {{ t('No apps in %{category}', { category }) }}
</template>

<script>
	import Mixins from '../mixins';
	import Tile from './Tile.vue';

	export default {
		mixins: [Mixins],
		components: {
			Tile
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
			}
		}
	}
</script>

<style lang="scss" scoped>
	@import "../styles/variables-theme";

	aside {
		width: 260px;
	}

	.market {
		padding: $global-gutter;
	}
</style>
