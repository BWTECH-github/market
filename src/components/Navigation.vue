<template lang="pug">
	nav.bwt-sidebar(:aria-label="t('Market navigation')")
		.bwt-search
			input(
				type="search",
				:value="searchQuery",
				:placeholder="t('Search apps...')",
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
			li
				router-link(:to="{ name: 'UpdateList' }")
					span {{ t('Updates') }}
					span.bwt-sidebar__count(v-if="updateList.length > 0") {{ updateList.length }}

			li.bwt-sidebar__section(v-if="!loading && !failed && categories.length") {{ t('Categories') }}

			li(v-for="category in categories", :key="category.id")
				router-link(:to="{ name: 'byCategory', params: { category: category.id }}")
					span(:lang="categoryLang(category)") {{ categoryLabel(category) }}

			li.bwt-sidebar__section {{ t('Settings') }}

			apiform

			li
				//- WCAG 4.1.2: leeres href="" zeigt auf die aktuelle URL und kann bei
				//- ausbleibendem preventDefault einen Reload auslösen. href="#" + @click.prevent
				//- hält die vorhandene Sidebar-Link-Optik, ohne ein gültiges Ziel vorzutäuschen.
				a(href="#", role="button", @click.prevent="invalidateCache")
					span {{ t('Clear cache') }}
</template>

<script>
	// Modified by BW-Tech GmbH for owncloud.online (PHP 8.4).

	import Apiform from './ApiForm.vue'
	import Mixins from '../mixins.js'

	export default {
		// categoryLang (lang="en" an Kategorienamen ohne Übersetzung) kommt aus dem Mixin
		// Die Kategorien lädt App.vue mit den übrigen Daten, bevor sie die Seite zeigt.
		mixins: [Mixins],
		methods: {
			t(string) {
				return this.$gettext(string);
			},
			invalidateCache () {
				this.$store.dispatch('INVALIDATE_CACHE')
			},
			onSearch (event) {
				this.$store.dispatch('UPDATE_SEARCH', event.target.value);
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
				const records = this.$store.state.categories.records || []
				return Array.isArray(records) ? records : Object.keys(records).map((key) => records[key])
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
