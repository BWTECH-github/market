<template lang="pug">
	div
		.uk-position-fixed.uk-position-center(v-show="loading", uk-spinner, uk-icon="icon: spinner")

		//- Kopf wie in den übrigen Ansichten des Marktes; ohne ihn stand hier
		//- eine völlig leere Fläche.
		header.bwt-page-head(v-if="!loading")
			div
				h1.bwt-page-head__title {{ t('App Bundles') }}

		ul(v-if="!loading && !failed && bundles.length").uk-margin-remove.uk-padding-remove
			Tile(v-for="bundle in bundles", :bundle="bundle", :key="bundle.id")

		//- Der Leerzustand hing an „!bundles“ – eine leere Liste ist aber ein
		//- Array und damit wahr; der Hinweis erschien nie.
		transition(name="fade")
			.uk-card.uk-card-default.uk-card-body.bwt-empty(v-if="!loading && !bundles.length")
				p.uk-text-center {{ t('No Bundles') }}
</template>

<script>
	import Tile from './BundleTile.vue';
	import mixins from '../mixins.js'

	export default {
		mixins: [mixins],
		components: {
			Tile
		},
		mounted () {
			this.$store.dispatch('FETCH_BUNDLES');
		},
		computed: {
			loading() {
				return this.$store.state.bundles.loading
			},
			failed() {
				return this.$store.state.bundles.failed
			},
			bundles() {
				if (this.loading || this.failed) {
					return []
				} else {
					return this.$store.getters.bundles
				}
			}
		}
	}
</script>

<style lang="scss" scoped></style>
