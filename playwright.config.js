const { defineConfig, devices } = require('@playwright/test');
const baseConfig = require('@wordpress/scripts/config/playwright.config');

module.exports = defineConfig({
	...baseConfig,
	testDir: './tests/e2e',
	use: {
		...baseConfig.use,
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8889',
	},
	webServer: {
		...baseConfig.webServer,
		port: 8889,
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'] },
		},
	],
});
