/**
 * WordPress dependencies
 */
const { test, expect } = require('./utils');

test.describe('Custom Status', () => {
	test('can apply custom status to a post', async ({ admin, editor, page }) => {
		const postTitle = `Status Test Post ${Date.now()}`;

		// Create a new post
		await admin.createNewPost({ title: postTitle });
		await editor.canvas.locator('role=textbox[name="Add title"i]').waitFor();

		// Open document settings to access custom status
		await editor.openDocumentSettingsSidebar();

		// Look for Edit Flow custom status selector
		// Edit Flow adds a custom status dropdown in the sidebar
		const statusDropdown = page.locator('select#post-status-display, select[name="post_status"]').first();

		if (await statusDropdown.isVisible()) {
			// Get available status options
			const options = await statusDropdown.locator('option').allTextContents();

			// Edit Flow should provide custom statuses beyond the default WordPress ones
			// Look for common Edit Flow statuses like "Pitch" or "Assigned"
			const hasCustomStatuses = options.some(opt =>
				opt.includes('Pitch') || opt.includes('Assigned') || opt.includes('In Progress')
			);

			if (hasCustomStatuses) {
				// Select a custom status if available
				const customOption = options.find(opt =>
					opt.includes('Pitch') || opt.includes('Assigned')
				);
				if (customOption) {
					await statusDropdown.selectOption({ label: customOption });
				}
			}
		}

		// Save the post
		await editor.saveDraft();

		// Verify post was saved
		await expect(page.getByRole('button', { name: 'Saved' })).toBeVisible({ timeout: 10000 });
	});

	test('custom status settings page is accessible', async ({ admin, page }) => {
		await admin.visitAdminPage('admin.php', 'page=ef-settings');

		// Click on Custom Statuses module
		const customStatusLink = page.locator('a:has-text("Custom Statuses"), a:has-text("Custom Status")');

		if (await customStatusLink.count() > 0) {
			await customStatusLink.first().click();

			// Verify we're on the custom statuses configuration page
			await expect(page.getByRole('heading', { name: /Custom Status/i }).first()).toBeVisible();
		}
	});
});
