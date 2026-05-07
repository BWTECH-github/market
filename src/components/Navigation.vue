<template lang="pug">
	.uk-card.uk-card-default.uk-margin-bottom
		.uk-card-header
			h1.uk-h3
				router-link(:to="{ name: 'index' }") {{ t('Market') }}

		.uk-card-body
			.bwt-search
				input(
					type="search",
					:value="searchQuery",
					:placeholder="t('Search apps…')",
					:aria-label="t('Search apps')",
					@input="onSearch"
				)

			ul.uk-nav-default.uk-nav-parent-icon(uk-nav, :v-if="!loading && !failed")
				li
					router-link(:to="{ name: 'index' }") {{ t('Show all') }}
				li
					router-link(:to="{ name: 'InstalledApps' }") {{ t('Installed Apps') }}
						span.uk-badge.uk-margin-small-left(v-if="localAppCount > 0") {{ localAppCount }}
				li
					router-link(:to="{ name: 'Bundles' }") {{ t('App Bundles') }}

				li.uk-nav-header {{ t('Categories') }}

				li(v-for="category in categories")
					router-link(:to="{ name: 'byCategory', params: { category: category.id }}") {{ categoryLabel(category) }}

				li(v-if="updateList.length > 0")
					router-link(:to="{ name: 'UpdateList' }") {{ t('Updates') }}
						span.uk-badge.uk-margin-small-left {{ updateList.length }}

				li.uk-nav-header {{ t('Settings') }}

				apiform

				li
					a(href="", @click.prevent="invalidateCache") {{ t('Clear cache') }}

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

	h1 {
		a {
			text-decoration: none;
			color: var(--bwt-text);
		}
	}

	.uk-badge {
		font-size: 0.75rem;
		background: var(--bwt-brand);
	}
</style>
