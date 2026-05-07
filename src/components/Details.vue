<template lang="pug">
	div
		.uk-position-fixed.uk-position-center(v-show="loading", uk-spinner, uk-icon="icon: spinner")
		.uk-card.uk-card-default.bwt-detail(v-if="!failed && application").uk-animation-slide-top-small
			.bwt-detail__hero(:style="heroStyle")
				.bwt-detail__hero-overlay
					router-link.bwt-detail__back(:to="{ name: 'index' }", :aria-label="t('Back')")
						span(uk-icon="icon: arrow-left")
					div.bwt-detail__hero-meta
						span.bwt-tile__category(v-if="application.categories && application.categories[0]")
							span(uk-icon="icon: tag; ratio: 0.7")
							| {{ application.categories[0] }}
						h1.bwt-detail__title {{ application.name }}
						.bwt-detail__rating
							rating(:rating="application.rating")

			.uk-card-body
				.article(v-html="markdown(application.description)")

				table.uk-table.uk-table-divider.uk-table-responsive.uk-table-justify(v-if="!processing && !loading")
					tr
						th
							span {{ t('Developer') }}

						th
							span {{ t('Version') }}

						th(v-if="details.created")
							span {{ t('Release date') }}

						th
							span {{ t('License') }}

					tr
						td
							a(v-if="publisher.isPagePublic", :href="publisher.url", target="_blank") {{ publisher.name }}
							span(v-else) {{ publisher.name }}

						td {{ details.version }}

						td(v-if="details.created") {{ details.created | formatDate }}

						td {{ license }}

				.uk-alert-primary(v-if="updateable && !processing && !loading", uk-alert)
					a.uk-alert-close.uk-close
					div(v-for="update in releases")
						strong {{ t('Version %{version} available', {version: update.version}) }}&nbsp;
						span {{ t('published on ') }} {{ update.created | formatDate }}.&nbsp;
						a(:href="application.marketplace", target="_blank") {{ t('Get more info') }}
						.uk-alert.uk-alert-danger(v-if="!update.canInstall")
							ul(v-if="!update.canInstall").uk-list
								li(v-for="dependency in update.missingDependencies")
									span(uk-icon="icon: warning; ratio: 0.75").uk-margin-small-right
									| {{ dependency }}
							p.uk-text-small
								t.missingDep

			.uk-card-footer
				div(v-if="processing || loading")
					button.uk-button.uk-button-primary.uk-align-right.uk-margin-remove-bottom.uk-margin-small-left.uk-position-relative(disabled)
						.uk-position-small.uk-position-center-left(uk-spinner, uk-icon="icon: spinner; ratio: 0.8")
						| &nbsp;&nbsp;&nbsp;&nbsp; {{ t('loading') }}

				div(v-else-if="!application.downloadable")
					a.uk-button.uk-button-secondary.uk-align-right.uk-margin-remove-bottom.uk-margin-small-left.uk-position-relative(:href="application.marketplace", target="_blank")
						| {{ t('view in marketplace') }}

				div(v-else)
					// Install
					div(v-if="!installed")
						button.uk-button.uk-button-primary.uk-align-right.uk-margin-remove-bottom.uk-margin-small-left.uk-position-relative(:disabled="processing || !installable", @click="install")
							| {{ t('install') }}

					// Uninstall
					div(v-else-if="application.id !== 'market'")
						button.uk-button.uk-button-default.uk-align-right.uk-margin-remove-bottom.uk-margin-small-left(:disabled="processing", @click="uninstall")
							| {{ t('uninstall') }}

					// Update
					div(v-if="updateable && releases.length > 1").uk-button-group.uk-align-right.uk-margin-remove-bottom.uk-margin-small-left.uk-position-relative
						button.uk-button.uk-button-primary._multiupdate-button(:disabled="processing", @click="update")
							| {{ t('Update to') }} {{ releases[updateVersion].version }}
						.uk-inline
							button.uk-button.uk-button-primary._multiupdate-dropdown(:disabled="processing")
								span(uk-icon='icon:  triangle-down')
							div(uk-dropdown='mode: click; boundary: ! .uk-button-group; boundary-align: true; pos: top-center;')._multiupdate-uikit-element
								ul.uk-nav.uk-dropdown-nav
									li(v-for="(release, rid) in releases")
										a(@click="setUpdateVersion(rid)") {{ t('version') }} {{ release.version }}

					div(v-else-if="updateable && releases.length === 1")
						button.uk-button.uk-button-primary.uk-align-right.uk-margin-remove-bottom.uk-margin-small-left.uk-position-relative(:disabled="processing", @click="update")
							| {{ t('update') }}

</template>
<script>

	// Modified by BW-Tech GmbH for owncloud.online (PHP 8.4).

	import Mixins from '../mixins.js'
	import Rating from './Rating.vue'
	import _ from 'underscore'

	export default {
		mixins: [Mixins],
		components: {
			Rating
		},
		data () {
			return {
				// Key of releases array
				// if length exceeds 1
				updateVersion : 0
			}
		},
		computed: {
			loading() {
				return this.$store.state.applications.loading
			},
			failed() {
				return this.$store.state.applications.failed
			},
			application() {
				if (this.failed) {
					return []
				}
				return this.$store.getters.application(this.$route.params.id)
			},
			screenshot () {
				const shots = this.application && this.application.screenshots;
				return Array.isArray(shots) && shots.length ? shots[0].url : null;
			},
			heroStyle () {
				if (!this.screenshot) {
					return {
						background: 'linear-gradient(135deg, var(--bwt-brand) 0%, var(--bwt-accent) 100%)'
					}
				}
				return { backgroundImage: `url("${this.screenshot}")` }
			},
			publisher () {
				const publisher = this.application && this.application.publisher;
				return publisher || {
					name: this.t('Unknown'),
					url: '#',
					isPagePublic: false
				}
			},
			installed() {
				return this.application.installed && !this.processing
			},
			installable() {
				return this.application.release && this.application.release.canInstall && !this.installed && !this.processing
			},
			updateable() {
				return this.application.installed && this.application.updateInfo !== false
			},

			// Any kind of installing, updating or uninstalling process
			processing() {
				return _.contains(this.$store.state.processing, this.application.id)
			},

			details () {
				if (this.installed) {
					if (this.application.installInfo)
						return this.application.installInfo
					return false
				}
				else {
					if (this.application.release)
						return this.application.release
					return false
				}
			},

			license () {
				if (this.installed) {
					if (this.application.installInfo)
						return this.application.installInfo.licence;
					return false
				}
				else {
					if (this.application.release)
						return this.application.release.license;
					return false
				}
			},

			releases () {
				if (!this.updateable)
					return false;
				return _.filter([
					this.application.minorUpdate,
					this.application.majorUpdate
				], function (release) {
					return release !== false;
				});
			}
		},
		filters: {
			formatDate (unixtime) {
				return moment(unixtime).format('ll');
			}
		},
		methods: {
			install () {
				this.$store.dispatch('PROCESS_APPLICATION', [this.application.id, 'install'])
			},
			uninstall () {
				UIkit.modal.confirm(this.t('Are you sure you want to remove <strong>%{appName}</strong> from your system?', {appName : this.application.name }), {
					center : true,
					escClose : true
				}).then(() => {
					this.$store.dispatch('PROCESS_APPLICATION', [this.application.id, 'uninstall'])
				}, function () {
					return null
				});
			},
			update () {
				if (this.releases.length > 1) {
					this.$store.dispatch('PROCESS_APPLICATION', [this.application.id, 'update', {'version' : this.releases[this.updateVersion].version}]);
				} else {
					this.$store.dispatch('PROCESS_APPLICATION', [this.application.id, 'update']);
				}
			},
			setUpdateVersion (version) {
				UIkit.dropdown('._multiupdate-uikit-element').hide();
				this.updateVersion = parseInt(version);
			}
		}
	}
</script>

<style lang="scss" scoped>
	@import "../styles/variables-theme";

	main {
		position: relative;
	}

	.bwt-detail {
		width: 100%;
		max-width: none;
		margin: 0;
		overflow: hidden;
	}

	.bwt-detail__hero {
		position: relative;
		min-height: 240px;
		background-size: cover;
		background-position: center;
	}

	.bwt-detail__hero-overlay {
		position: relative;
		min-height: 240px;
		padding: 1.25rem 1.5rem 1.5rem;
		display: flex;
		flex-direction: column;
		justify-content: space-between;
		gap: 1rem;
		background: linear-gradient(180deg, rgba(15, 23, 42, 0.05) 0%, rgba(15, 23, 42, 0.55) 100%);
		color: #ffffff;
	}

	.bwt-detail__back {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 36px;
		height: 36px;
		border-radius: var(--bwt-radius-pill);
		background: rgba(255, 255, 255, 0.16);
		color: #ffffff;
		backdrop-filter: blur(6px);
		text-decoration: none;
		transition: background-color var(--bwt-transition);

		&:hover {
			background: rgba(255, 255, 255, 0.28);
			color: #ffffff;
		}
	}

	.bwt-detail__hero-meta {
		display: flex;
		flex-direction: column;
		gap: 0.4rem;
	}

	.bwt-detail__title {
		font-size: 2rem;
		font-weight: 700;
		letter-spacing: -0.02em;
		margin: 0;
		color: #ffffff;
	}

	.bwt-detail__hero-meta .bwt-tile__category {
		display: inline-flex;
		align-items: center;
		gap: 0.3rem;
		font-size: 0.72rem;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		font-weight: 600;
		color: rgba(255, 255, 255, 0.85);
	}

	.bwt-detail__rating {
		display: inline-flex;
	}

	.uk-label {
		font-size: .75rem;
	}

	._multiupdate-button {
		padding-right: 5px;
	}

	._multiupdate-dropdown {
		padding-left: 10px;
		padding-right: 10px;
	}
</style>
