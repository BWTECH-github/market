<template lang="pug">
	div
		.uk-position-fixed.uk-position-center(v-show="loading", uk-spinner, uk-icon="icon: spinner")
		.uk-card.uk-card-default(v-if="!failed").uk-animation-slide-top-small
			.uk-card-header
				div(uk-grid)
					.uk-width-expand
						h2.uk-h3.uk-margin-remove-bottom {{ t('Installed Apps') }}
						p.uk-text-meta.uk-margin-small-top {{ t('Apps currently present in this owncloud.online instance.') }}
					.uk-width-auto
						.uk-button-group
							button.uk-button.uk-button-small(:class="filterClass('all')", @click="filter = 'all'") {{ t('All') }}
							button.uk-button.uk-button-small(:class="filterClass('enabled')", @click="filter = 'enabled'") {{ t('Enabled') }}
							button.uk-button.uk-button-small(:class="filterClass('disabled')", @click="filter = 'disabled'") {{ t('Disabled') }}

			.uk-card-body
				table.uk-table.uk-table-hover.uk-table-divider.uk-table-middle(v-if="applications.length")
					thead
						tr
							th {{ t('App') }}
							th {{ t('Version') }}
							th {{ t('Author') }}
							th {{ t('State') }}
							th {{ t('Compatibility') }}
							th &nbsp;
					tbody
						tr(v-for="application in applications", :key="application.id")
							td
								strong {{ appName(application) }}
								div.uk-text-meta {{ application.id }}
							td {{ application.version || '-' }}
							td {{ author(application) }}
							td
								span.bwt-badge.bwt-badge--installed(v-if="application.active") {{ t('Enabled') }}
								span.bwt-badge.bwt-badge--disabled(v-else) {{ t('Disabled') }}
							td
								span(v-if="application.canInstall") {{ t('OK') }}
								span.uk-text-danger(v-else) {{ t('Missing dependencies') }}
								ul.uk-list.uk-list-collapse.uk-margin-small-top(v-if="application.missingDependencies && application.missingDependencies.length")
									li(v-for="dependency in application.missingDependencies") {{ dependency }}
							td.uk-text-right
								router-link.uk-button.uk-button-small.uk-button-secondary(
									v-if="catalogApplication(application.id)",
									:to="{ name: 'details', params: { id: application.id }}"
								) {{ t('Details') }}
								span.uk-text-meta(v-else) {{ t('Local only') }}

				.uk-text-center.uk-padding(v-else-if="!loading")
					p.uk-text-meta(v-if="searchQuery") {{ t('No installed apps match "%{query}"', { query: searchQuery }) }}
					p.uk-text-meta(v-else) {{ t('No installed apps found') }}
</template>

<script>
	// Modified by BW-Tech GmbH for owncloud.online (PHP 8.4).

	import Mixins from '../mixins.js'

	export default {
		mixins: [Mixins],
		data () {
			return {
				filter: 'all'
			}
		},
		mounted () {
			this.$store.dispatch('FETCH_LOCAL_APPS')
		},
		methods: {
			appName (application) {
				return application.name || application.id
			},
			author (application) {
				if (Array.isArray(application.author)) {
					return application.author.join(', ')
				}
				return application.author || '-'
			},
			catalogApplication (id) {
				return this.$store.getters.application(id)
			},
			filterClass (filter) {
				return this.filter === filter ? 'uk-button-primary' : 'uk-button-default'
			}
		},
		computed: {
			loading () {
				return this.$store.state.localApps.loading
			},
			failed () {
				return this.$store.state.localApps.failed
			},
			searchQuery () {
				return this.$store.getters.searchQuery
			},
			applications () {
				const apps = this.$store.getters.localApplications
				if (this.filter === 'enabled') {
					return apps.filter((application) => application.active)
				}
				if (this.filter === 'disabled') {
					return apps.filter((application) => !application.active)
				}
				return apps
			}
		}
	}
</script>

<style lang="scss" scoped>
	@import "../styles/variables-theme";

	.bwt-badge--disabled {
		background: rgba(100, 116, 139, 0.16);
		color: var(--bwt-muted);
	}

	.uk-table td {
		vertical-align: top;
	}
</style>
