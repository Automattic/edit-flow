/**
 * WordPress dependencies
 */
const { test, expect } = require('./utils');

test.describe('Editorial Comments', () => {
	test('expects a user can create an editorial comment on a post', async ({ admin, editor, page }) => {
		await admin.createNewPost({ title: 'Title' });

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

		// Reload page to ensure editorial comments UI is loaded
		await page.reload({ waitUntil: 'load' });

		// Wait a moment for the block editor to initialize
		await page.waitForTimeout(2000);

		// In WordPress 6.9, metaboxes are in a collapsible panel at the bottom of the editor
		// Scroll to bottom to see the Meta Boxes toggle
		await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
		await page.waitForTimeout(500);

		// The Meta Boxes toggle has a specific structure - find and click the chevron/toggle
		// Look for the button with the chevron SVG or the panel header
		const metaBoxesRow = page.locator('.edit-post-meta-boxes-main__presenter, [class*="meta-boxes"]').first();

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

		// Now the metabox should be in the DOM and visible
		const metabox = page.locator('#edit-flow-editorial-comments');
		await metabox.waitFor({ state: 'visible', timeout: 15000 });

		// Scroll to the metabox
		await metabox.evaluate((el) => el.scrollIntoView({ behavior: 'instant', block: 'center' }));
		await page.waitForTimeout(500);

		// Expand the metabox if it's collapsed
		if (await metabox.evaluate((el) => el.classList.contains('closed'))) {
			const toggleButton = metabox.locator('button.handlediv');
			await toggleButton.click();
			await page.waitForTimeout(300);
		}

		// The respond button should now be visible
		const respondButton = page.locator('#ef-comment_respond');
		await respondButton.waitFor({ state: 'visible', timeout: 10000 });

		const COMMENT_TEXT = 'Hello';

		// Click the respond button
		await respondButton.click();

		// Type the comment
		await page.locator('#ef-replycontent').fill(COMMENT_TEXT);

		// Save the comment
		await page.locator('.ef-replysave').click();

		// Wait for comment to appear and verify
		await page.locator('#ef-comments .comment-content').waitFor();
		const commentText = await page.locator('#ef-comments .comment-content p').first().textContent();

		expect(commentText).toBe(COMMENT_TEXT);
	});
});
