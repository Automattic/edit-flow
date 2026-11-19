// wp-env runs one environment, on port 8888 unless WP_ENV_PORT moves it. Set
// WP_BASE_URL before loading anything else, so the base config, the global
// setup and @wordpress/e2e-test-utils-playwright all use the same site.
process.env.WP_BASE_URL ||= `http://localhost:${process.env.WP_ENV_PORT || 8888}`;

const { defineConfig, devices } = require('@playwright/test');
const baseConfig = require('@wordpress/scripts/config/playwright.config');

module.exports = defineConfig({
	...baseConfig,
	testDir: './tests/e2e',
	globalSetup: require.resolve('./tests/e2e/global-setup.js'),
	projects: [
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'] },
		},
	],
});
