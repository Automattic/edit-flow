/**
 * Playwright global setup for Edit Flow E2E tests.
 *
 * This is the E2E equivalent of PHPUnit's bootstrap.php - it ensures
 * the test environment is properly configured before any tests run.
 *
 * @see https://playwright.dev/docs/test-global-setup-teardown
 * @see https://github.com/WordPress/gutenberg/blob/trunk/test/e2e/config/global-setup.ts
 */

const { request } = require('@playwright/test');
const { RequestUtils } = require('@wordpress/e2e-test-utils-playwright');

/**
 * Global setup function - runs once before all tests.
 *
 * @param {import('@playwright/test').FullConfig} config Playwright config.
 */
async function globalSetup(config) {
	const { storageState, baseURL } = config.projects[0].use;
	const storageStatePath = typeof storageState === 'string' ? storageState : undefined;

	const requestContext = await request.newContext({
		baseURL,
	});

	const requestUtils = new RequestUtils(requestContext, {
		storageStatePath,
	});

	// Authenticate and save the storageState to disk.
	await requestUtils.setupRest();

	// Activate the Edit Flow plugin - mirrors PHPUnit's bootstrap.php.
	await requestUtils.activatePlugin('edit-flow');

	await requestContext.dispose();
}

module.exports = globalSetup;
