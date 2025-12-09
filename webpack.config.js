var MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
var debug = process.env.NODE_ENV !== 'production';
var glob = require( 'glob' );

const entries = glob.sync( './modules/**/lib/*-block.js' ).reduce( ( acc, item ) => {
	const name = item.replace( /modules\/(.*)\/lib\/(.*)-block.js/, '$1' );
	acc[ name ] = item;
	return acc;
}, {} );

// @todo
var extractEditorSCSS = new MiniCssExtractPlugin( {
	filename: './[name].editor.build.css',
} );

var extractBlockSCSS = new MiniCssExtractPlugin( {
	filename: './[name].style.build.css',
} );

var plugins = [ extractEditorSCSS, extractBlockSCSS ];

var scssConfig = [ 'css-loader', 'sass-loader' ];

module.exports = [
	{
		context: __dirname,
		devtool: debug ? 'source-map' : null,
		mode: debug ? 'development' : 'production',
		entry: entries,
		output: {
			path: __dirname + '/dist/',
			filename: '[name].build.js',
		},
		externals: {
			react: 'React',
			'react-dom': 'ReactDOM',
		},
		module: {
			rules: [
				{
					test: /\.js$/,
					exclude: /node_modules/,
					use: [
						{
							loader: 'babel-loader',
						},
					],
				},
				{
					test: /editor\.scss$/,
					exclude: /node_modules/,
					// use: extractEditorSCSS.extract(scssConfig)
					use: [ MiniCssExtractPlugin.loader, ...scssConfig ],
				},
				{
					test: /style\.scss$/,
					exclude: /node_modules/,
					// use: extractBlockSCSS.extract(scssConfig)
					use: [ MiniCssExtractPlugin.loader, ...scssConfig ],
				},
			],
		},
		plugins,
	},
	{
		context: __dirname,
		devtool: debug ? 'source-map' : null,
		mode: debug ? 'development' : 'production',
		entry: {
			'calendar.react': __dirname + '/modules/calendar/lib/react/calendar.react.js',
		},
		output: {
			path: __dirname + '/modules/calendar/lib/dist',
			filename: '[name].build.js',
		},
		externals: {
			react: 'React',
			'react-dom': 'ReactDOM',
			moment: 'moment',
			'@wordpress/i18n': 'wp.i18n',
			'@wordpress/components': 'wp.components',
			'@wordpress/url': 'wp.url',
			'@wordpress/data': 'wp.data',
		},
		module: {
			rules: [
				{
					test: /\.js$/,
					exclude: /node_modules/,
					use: [
						{
							loader: 'babel-loader',
						},
					],
				},
				{
					test: /style\.react\.scss$/,
					exclude: /node_modules/,
					// use: extractBlockSCSS.extract(scssConfig)
					use: [ MiniCssExtractPlugin.loader, ...scssConfig ],
				},
			],
		},
		plugins,
	},
];
