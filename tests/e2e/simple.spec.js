/**
 * WordPress dependencies
 */
const { test, expect } = require('./utils');

test.describe('Edit Flow', () => {
	test('the plugin settings page loads', async ({ admin, page }) => {
		await admin.visitAdminPage('admin.php', 'page=ef-settings');

		const heading = page.locator('.edit-flow-admin h2');
		await expect(heading).toHaveText('Edit Flow');
	});
});
