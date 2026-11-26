const { DefinePlugin } = require('webpack');
const { VueLoaderPlugin } = require('vue-loader');

module.exports = {
	mode: 'production',
	entry: './src/default.js',
	output : {
		path: require('path').resolve(__dirname, 'js'),
		filename : 'market.bundle.js',
		publicPath: '/'
	},
	module: {
		rules: [{
			test: require.resolve('uikit'),
			loader: 'expose-loader',
			options: {
				exposes: ["UIkit"],
			},
		}, {
			test: /\.js?$/,
			exclude: /node_modules/,
			use: 'babel-loader',
		}, {
			test: /\.scss?$/,
			use: ['style-loader', 'css-loader', {
				loader: 'sass-loader',
				options: {
					implementation: require('sass'),
					// Ohne @charset/BOM: neuere postcss-Fassungen geben ein BOM aus Sass
					// wörtlich weiter, dann begänne das CSS mit einem BOM vor :root.
					sassOptions: {
						charset: false,
					},
				}
			}]
		}, {
			test: /\.pug$/,
			use: 'vue-pug-loader'
		}, {
			test: /\.vue$/,
			loader: 'vue-loader',
		}]
	},
	plugins: [
		new VueLoaderPlugin(),
		// Schalter, die Vue 3, Vuex 4 und Vue Router 4 beim Bündeln erwarten: Die
		// Komponenten nutzen die Options-API; Entwicklerwerkzeuge und Hinweise zur
		// Hydration bleiben aus dem Bündel.
		new DefinePlugin({
			__VUE_OPTIONS_API__: true,
			__VUE_PROD_DEVTOOLS__: false,
			__VUE_PROD_HYDRATION_MISMATCH_DETAILS__: false
		})
	]
}
