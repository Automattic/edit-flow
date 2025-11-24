/**
 * WordPress dependencies
 */
const { test, expect } = require('./utils');

test.describe('Editorial Comments', () => {
	test('expects a user can create an editorial comment on a post', async ({ admin, editor, page }) => {
		await admin.createNewPost({ title: 'Title' });

		// Save the post as draft
		await editor.saveDraft();

		// Wait for save to complete
		await expect(page.locator('.editor-post-saved-state')).toContainText('Saved');

		// Reload page to ensure editorial comments UI is loaded
		// TODO: Eventually show "Respond to post" button without reload
		await page.reload({ waitUntil: 'domcontentloaded' });

		// Wait for editorial comments section to be ready
		await page.locator('#ef-comment_respond').waitFor();

		const COMMENT_TEXT = 'Hello';

		// Click the respond button
		await page.locator('#ef-comment_respond').click();

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
