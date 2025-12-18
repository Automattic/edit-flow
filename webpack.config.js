const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

// The calendar-react entry needs @wordpress packages bundled (not externalized)
// because the calendar page loads outside the block editor context where these
// globals aren't guaranteed to be available. This includes all @wordpress packages
// and their transitive dependencies (like react/jsx-runtime used by the automatic
// JSX transform).
const shouldBundleRequest = ( request ) => {
	// Bundle all @wordpress packages
	if ( request.startsWith( '@wordpress/' ) ) {
		return true;
	}
	// Bundle react/jsx-runtime (used by automatic JSX transform)
	if ( request === 'react/jsx-runtime' || request === 'react/jsx-dev-runtime' ) {
		return true;
	}
	return false;
};

// Replace the default DependencyExtractionWebpackPlugin with a configured one
// Return false to explicitly prevent externalization of bundled packages
const plugins = defaultConfig.plugins.map( ( plugin ) => {
	if ( plugin.constructor.name === 'DependencyExtractionWebpackPlugin' ) {
		return new DependencyExtractionWebpackPlugin( {
			requestToExternal( request ) {
				// Return false to bundle these packages instead of externalizing
				if ( shouldBundleRequest( request ) ) {
					return false;
				}
			},
			requestToHandle( request ) {
				// Return false to not add script handles for bundled packages
				if ( shouldBundleRequest( request ) ) {
					return false;
				}
			},
		} );
	}
	return plugin;
} );

module.exports = {
	...defaultConfig,
	entry: {
		'custom-status-block': './modules/custom-status/lib/custom-status-block.js',
		'calendar-react': './modules/calendar/lib/react/calendar.react.js',
	},
	plugins,
};
