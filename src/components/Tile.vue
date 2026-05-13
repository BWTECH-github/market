<template lang="pug">
	transition(name="fade")
		li(v-if="application").bwt-app-grid__item.uk-animation-slide-top-small
			.uk-card.uk-card-default.bwt-tile
				router-link.bwt-tile__media(
					:to="{ name: 'details', params: { id: application.id }}",
					:style="mediaStyle",
					:class="{ 'bwt-tile__media--placeholder': !screenshot }",
					:aria-label="application.name"
				)
					span.bwt-tile__initial(v-if="!screenshot") {{ initial }}
				.bwt-tile__body
					h3.bwt-tile__title
						router-link(:to="{ name: 'details', params: { id: application.id }}") {{ application.name }}

					p.bwt-tile__summary(v-if="application.summary || application.description") {{ truncatedSummary }}

					.bwt-tile__footer
						span.bwt-tile__category(v-if="primaryCategory")
							span(uk-icon="icon: tag; ratio: 0.7")
							| {{ primaryCategoryLabel }}
						span.bwt-tile__category(v-else) &nbsp;

						span.bwt-badge.bwt-badge--update(v-if="application.updateInfo") {{ t('Update') }}
						span.bwt-badge.bwt-badge--installed(v-else-if="application.installed") {{ t('Installed') }}
						rating(v-else, :rating="application.rating")
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
					return { background: this.placeholderGradient }
				}
				return { backgroundImage: `url("${this.screenshot}")` }
			},
			initial () {
				const source = this.application && (this.application.name || this.application.id) || '';
				return String(source).trim().charAt(0).toUpperCase() || '?';
			},
			placeholderGradient () {
				// Deterministic per-app hue so the placeholders are not all the same blue.
				const source = this.application && (this.application.id || this.application.name) || '';
				let hash = 0;
				for (let i = 0; i < source.length; i++) {
					hash = (hash * 31 + source.charCodeAt(i)) | 0;
				}
				const hue = Math.abs(hash) % 360;
				const hueB = (hue + 45) % 360;
				return `linear-gradient(135deg, hsl(${hue}, 60%, 52%) 0%, hsl(${hueB}, 65%, 42%) 100%)`;
			},
			primaryCategory () {
				const cats = this.application && this.application.categories;
				return Array.isArray(cats) && cats.length ? cats[0] : '';
			},
			primaryCategoryLabel () {
				const category = this.$store.getters.category(this.primaryCategory) || { id: this.primaryCategory };
				return this.categoryLabel(category);
			},
			truncatedSummary () {
				const text = this.application.summary || this.application.description || '';
				const stripped = String(text).replace(/[#*_`]/g, '').trim();
				return stripped.length > 140 ? stripped.slice(0, 137) + '...' : stripped;
			}
		},
		methods: {
			categoryLabel (category) {
				if (!category) {
					return ''
				}
				if (category.translations) {
					const locale = (typeof OC !== 'undefined' && OC.getLocale) ? OC.getLocale().slice(0, 2) : 'en';
					const translated = category.translations[locale] || category.translations.en;
					if (translated && translated.name) {
						return translated.name;
					}
				}
				const label = category.name || category.displayName || category.label || category.id || '';
				return String(label).replace(/[-_]+/g, ' ');
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
