const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const devHost = 'app.local';

const alias = {
	'@': path.resolve( __dirname, 'resources/js' ),
};

module.exports = {
	...defaultConfig,
	entry: {
		'js/app': './resources/js/index.tsx',
		'css/app': './resources/css/app.css',
	},
	watchOptions: {
		ignored: [ '**/assets/build/**', '**/*.asset.php' ],
	},
	output: {
		path: path.resolve( __dirname, './assets/build/' ),
		filename: '[name].js',
		clean: false,
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) => plugin.constructor.name !== 'RtlCssPlugin'
		),
	],
	resolve: {
		...defaultConfig.resolve,
		alias,
	},
	devServer: {
		devMiddleware: {
			writeToDisk: true,
		},
		allowedHosts: 'auto',
		port: 8889,
		host: devHost,
		proxy: {
			'/assets/build': {
				pathRewrite: {
					'^/assets/build': '',
				},
			},
		},
		headers: { 'Access-Control-Allow-Origin': '*' },
	},
};
