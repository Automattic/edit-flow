import { defineConfig, transformWithOxc } from 'vite';

export default defineConfig( {
	define: {
		EF_CALENDAR: { WP_VERSION: 5.4 },
	},
	plugins: [
		{
			// Source keeps JSX in .js files (the wp-scripts convention), which
			// Vite only parses as plain JS. Transform it as JSX for tests.
			name: 'edit-flow-jsx-in-js',
			enforce: 'pre',
			transform( code, id ) {
				if ( ! /\/(modules|tests\/js)\/.*\.js$/.test( id ) ) {
					return null;
				}
				return transformWithOxc( code, id, {
					lang: 'jsx',
					jsx: { runtime: 'automatic' },
				} );
			},
		},
	],
	test: {
		environment: 'jsdom',
		include: [ 'tests/js/**/*.test.js' ],
		setupFiles: [ 'tests/js/setup.js' ],
	},
} );
