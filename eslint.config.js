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
		settings: {
			react: {
				version: 'detect',
			},
		},
		rules: {
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
