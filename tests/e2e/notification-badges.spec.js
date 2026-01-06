/**
 * WordPress dependencies
 */
const { test, expect } = require('./utils');

/**
 * Helper function to expand meta boxes in the block editor
 */
async function expandMetaBoxes(page) {
	// Scroll to bottom to see the Meta Boxes toggle
	await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
	await page.waitForTimeout(500);

	// Try clicking directly via JavaScript to expand meta boxes
	await page.evaluate(() => {
		// Find the meta boxes toggle button (it has aria-expanded attribute)
		const buttons = document.querySelectorAll('button[aria-expanded]');
		for (const btn of buttons) {
			if (btn.textContent.includes('Meta Boxes') || btn.closest('[class*="meta-boxes"]')) {
				if (btn.getAttribute('aria-expanded') === 'false') {
					btn.click();
					return true;
				}
			}
		}
		// Alternative: find by class name pattern
		const toggle = document.querySelector('.edit-post-meta-boxes-main button[aria-expanded="false"]');
		if (toggle) {
			toggle.click();
			return true;
		}
		return false;
	});

	await page.waitForTimeout(1500);
}

/**
 * Helper function to expand a specific metabox if collapsed
 */
async function expandMetabox(page, metabox) {
	const isClosed = await metabox.evaluate((el) => el.classList.contains('closed'));
	if (isClosed) {
		const toggleButton = metabox.locator('button.handlediv, .handlediv');
		await toggleButton.click();
		await page.waitForTimeout(300);
	}
}

test.describe('Notification Badges', () => {
	test('post author has Post Author badge and disabled checkbox', async ({ admin, editor, page }) => {
		await admin.createNewPost({ title: 'Notification Badge Test' });

		// Dismiss the welcome guide if it appears
		const welcomeGuide = page.locator('.edit-post-welcome-guide, [aria-label="Welcome to the block editor"]');
		if (await welcomeGuide.isVisible({ timeout: 2000 }).catch(() => false)) {
			await page.keyboard.press('Escape');
			await page.waitForTimeout(300);
		}

		// Save the post as draft
		await editor.saveDraft();

		// Wait for save to complete
		await expect(page.locator('.editor-post-saved-state')).toContainText('Saved');

		// Reload page to ensure notifications UI is loaded
		await page.reload({ waitUntil: 'load' });

		// Wait a moment for the block editor to initialize
		await page.waitForTimeout(2000);

		// Expand meta boxes
		await expandMetaBoxes(page);

		// Find the notifications metabox
		const metabox = page.locator('#edit-flow-notifications');
		await metabox.waitFor({ state: 'visible', timeout: 15000 });

		// Scroll to the metabox
		await metabox.evaluate((el) => el.scrollIntoView({ behavior: 'instant', block: 'center' }));
		await page.waitForTimeout(500);

		// Expand the metabox if it's collapsed
		await expandMetabox(page, metabox);

		// Get the current user's ID from the page
		const currentUserId = await page.evaluate(() => {
			return typeof ef_notifications_localization !== 'undefined'
				? ef_notifications_localization.post_author_id
				: null;
		});

		expect(currentUserId).not.toBeNull();

		// Find the post author's list item
		const authorCheckbox = page.locator(`#ef-selected-users-${currentUserId}`);
		await authorCheckbox.waitFor({ state: 'attached', timeout: 5000 });

		// Verify the checkbox is disabled (auto-subscribed - JS disables it)
		await expect(authorCheckbox).toBeDisabled();

		// Verify the "Post Author" badge is present
		const postAuthorBadge = page.locator('.post_following_list-post_author');
		await expect(postAuthorBadge).toBeVisible();
		await expect(postAuthorBadge).toHaveText('Post Author');

		// Note: "Auto-subscribed" badge only shows when the author is already subscribed.
		// For a new post, the author may not yet be auto-subscribed until a status transition.
		// The PHP unit tests cover the auto-subscribed badge logic.
	});

	test('non-author users do not have Post Author badge', async ({ admin, editor, page }) => {
		await admin.createNewPost({ title: 'Non-Author Badge Test' });

		// Dismiss the welcome guide if it appears
		const welcomeGuide = page.locator('.edit-post-welcome-guide, [aria-label="Welcome to the block editor"]');
		if (await welcomeGuide.isVisible({ timeout: 2000 }).catch(() => false)) {
			await page.keyboard.press('Escape');
			await page.waitForTimeout(300);
		}

		// Save the post as draft
		await editor.saveDraft();

		// Wait for save to complete
		await expect(page.locator('.editor-post-saved-state')).toContainText('Saved');

		// Reload page to ensure notifications UI is loaded
		await page.reload({ waitUntil: 'load' });
		await page.waitForTimeout(2000);

		// Expand meta boxes
		await expandMetaBoxes(page);

		// Find the notifications metabox
		const metabox = page.locator('#edit-flow-notifications');
		await metabox.waitFor({ state: 'visible', timeout: 15000 });

		// Scroll to the metabox
		await metabox.evaluate((el) => el.scrollIntoView({ behavior: 'instant', block: 'center' }));
		await page.waitForTimeout(500);

		// Expand the metabox if it's collapsed
		await expandMetabox(page, metabox);

		// Get all Post Author badges - should only be one (for the actual post author)
		const postAuthorBadges = page.locator('.post_following_list-post_author');
		const badgeCount = await postAuthorBadges.count();

		// There should be exactly one Post Author badge
		expect(badgeCount).toBe(1);

		// Get the current user's ID
		const currentUserId = await page.evaluate(() => {
			return typeof ef_notifications_localization !== 'undefined'
				? ef_notifications_localization.post_author_id
				: null;
		});

		// Verify the badge is associated with the post author's checkbox
		const authorListItem = page.locator(`label[for="ef-selected-users-${currentUserId}"]`);
		const badgeInAuthorItem = authorListItem.locator('.post_following_list-post_author');
		await expect(badgeInAuthorItem).toBeVisible();
	});
});
