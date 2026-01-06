const AutomatticPlugin = require( '@automattic/eslint-plugin-wpvip' );

module.exports = [
	{
		ignores: [
			'node_modules/**/*',
			'**/dist/**/*',
			'vendor/**/*',
			'**/*.build.js',
		],
	},
	...AutomatticPlugin.configs.recommended,
	{
		languageOptions: {
			parserOptions: {
				babelOptions: {
					presets: [ '@babel/preset-react' ],
				},
			},
		},
		settings: {
			react: {
				version: 'detect',
			},
			// WordPress packages are loaded at runtime, not installed as npm dependencies
			'import/core-modules': [ '@wordpress/i18n', '@wordpress/url', '@wordpress/data', '@wordpress/components', '@wordpress/compose', '@wordpress/plugins', '@wordpress/editor', 'moment', 'react', 'react-dom' ],
		},
		rules: {
			// WordPress packages (react, react-dom, etc.) are available at runtime via wp-scripts
			'import/no-extraneous-dependencies': 'off',
			'no-duplicate-imports': 'off', // Conflicts with type-only imports pattern
			// Project-specific rule overrides (matching previous .eslintrc.js)
			'no-prototype-builtins': 'off',
			'no-eval': 'off',
			complexity: 'off',
			camelcase: 'off',
			'no-undef': 'off',
			'valid-jsdoc': 'off',
			'react/prop-types': 'off',
			'react/react-in-jsx-scope': 'off',
			'react-hooks/rules-of-hooks': 'off',
			'no-redeclare': 'off',
			'no-shadow': 'off',
			'no-nested-ternary': 'off',
			'no-var': 'off',
			'no-unused-vars': 'off',
			'no-useless-escape': 'off',
			'prefer-const': 'off',
			'no-global-assign': 'off',
			'no-constant-binary-expression': 'off',
			'valid-typeof': 'off',
			eqeqeq: 'off',
			radix: 'off',
			'no-eq-null': 'off',
			'array-callback-return': 'off',
			'no-unused-expressions': 'off',
			'no-alert': 'off',
			'no-lonely-if': 'off',
		},
	},
];
