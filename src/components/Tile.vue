<template lang="pug">
	transition(name="fade")
		li(class='uk-width-1-2@m uk-width-1-3@xl', v-if="application").uk-animation-slide-top-small
			.uk-card.uk-card-default.bwt-tile
				router-link.bwt-tile__media(
					:to="{ name: 'details', params: { id: application.id }}",
					:style="mediaStyle",
					:aria-label="application.name"
				)
				.bwt-tile__body
					h3.bwt-tile__title
						router-link(:to="{ name: 'details', params: { id: application.id }}") {{ application.name }}

					p.bwt-tile__summary(v-if="application.summary || application.description") {{ truncatedSummary }}

					.bwt-tile__footer
						span.bwt-tile__category
							span(uk-icon="icon: tag; ratio: 0.7").uk-margin-xsmall-right
							| {{ primaryCategory }}

						span.bwt-badge.bwt-badge--update(v-if="application.updateInfo") {{ t('Update') }}
						span.bwt-badge.bwt-badge--installed(v-else-if="application.installed") {{ t('Installed') }}

						rating(v-if="!application.installed && !application.updateInfo", :rating="application.rating")
</template>

<script>
	import Rating from './Rating.vue';
	import Mixins from '../mixins';

	export default {
		mixins: [Mixins],
		components: {
			Rating
		},
		props: [
			'application'
		],
		computed: {
			screenshot () {
				const shots = this.application && this.application.screenshots;
				return Array.isArray(shots) && shots.length ? shots[0].url : null;
			},
			mediaStyle () {
				if (!this.screenshot) {
					return {
						background: 'linear-gradient(135deg, var(--bwt-brand) 0%, var(--bwt-accent) 100%)'
					}
				}
				return { backgroundImage: `url("${this.screenshot}")` }
			},
			primaryCategory () {
				const cats = this.application && this.application.categories;
				return Array.isArray(cats) && cats.length ? cats[0] : '';
			},
			truncatedSummary () {
				const text = this.application.summary || this.application.description || '';
				const stripped = String(text).replace(/[#*_`]/g, '').trim();
				return stripped.length > 140 ? stripped.slice(0, 137) + '…' : stripped;
			}
		}
	}
</script>

<style lang="scss" scoped>
	@import "../styles/variables-theme";

	.bwt-tile__category {
		text-transform: capitalize;
	}
</style>
