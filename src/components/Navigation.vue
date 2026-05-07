<template lang="pug">
	nav.bwt-sidebar(aria-label="Market navigation")
		.bwt-search
			input(
				type="search",
				:value="searchQuery",
				:placeholder="t('Search apps…')",
				:aria-label="t('Search apps')",
				@input="onSearch"
			)

		ul.bwt-sidebar__nav
			li
				router-link(:to="{ name: 'index' }", exact)
					span {{ t('Discover') }}
			li
				router-link(:to="{ name: 'InstalledApps' }")
					span {{ t('Installed Apps') }}
					span.bwt-sidebar__count(v-if="localAppCount > 0") {{ localAppCount }}
			li
				router-link(:to="{ name: 'Bundles' }")
					span {{ t('App Bundles') }}
			li(v-if="updateList.length > 0")
				router-link(:to="{ name: 'UpdateList' }")
					span {{ t('Updates') }}
					span.bwt-sidebar__count {{ updateList.length }}

			li.bwt-sidebar__section(v-if="!loading && !failed && categories.length") {{ t('Categories') }}

			li(v-for="category in categories")
				router-link(:to="{ name: 'byCategory', params: { category: category.id }}")
					span {{ categoryLabel(category) }}

			li.bwt-sidebar__section {{ t('Settings') }}

			apiform

			li
				a(href="", @click.prevent="invalidateCache")
					span {{ t('Clear cache') }}
</template>

<script>
	// Modified by BW-Tech GmbH for owncloud.online (PHP 8.4).

	import Apiform from './ApiForm.vue'

	export default {
		mounted () {
			this.$store.dispatch('FETCH_CATEGORIES')
		},
		methods: {
			t(string) {
				return this.$gettext(string);
			},
			invalidateCache () {
				this.$store.dispatch('INVALIDATE_CACHE')
			},
			onSearch (event) {
				this.$store.dispatch('UPDATE_SEARCH', event.target.value);
			},
			categoryLabel (category) {
				if (!category || !category.translations) {
					return ''
				}
				const locale = (typeof OC !== 'undefined' && OC.getLocale) ? OC.getLocale().slice(0, 2) : 'en';
				const t = category.translations[locale] || category.translations.en;
				return (t && t.name) ? t.name : category.id;
			}
		},
		computed: {
			loading() {
				return this.$store.state.categories.loading
			},
			failed() {
				return this.$store.state.categories.failed
			},
			categories() {
				if (this.loading || this.failed) {
					return []
				}
				return this.$store.state.categories.records
			},
			updateList() {
				return this.$store.getters.updateList
			},
			localAppCount() {
				return this.$store.getters.localApplications.length
			},
			searchQuery() {
				return this.$store.getters.searchQuery
			}
		},
		components: {
			Apiform
		}
	}
</script>

<style lang="scss" scoped>
	@import "../styles/variables-theme";

	.bwt-sidebar__section {
		display: block;
	}
</style>
