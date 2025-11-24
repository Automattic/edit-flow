/**
 * WordPress dependencies
 */
const { test, expect } = require('./utils');

test.describe('Calendar Body', () => {
	test.skip('expects a published post cannot be dragged and dropped', async ({ admin, editor, page }) => {
		// TODO: This test requires published posts, but Edit Flow's custom statuses
		// may interfere with the standard WordPress publish flow in Playwright.
		// Skipping for now - will implement after reviewing Edit Flow's publish behavior.
	});

	test.skip('expects an unpublished post can be dragged and dropped', async ({ admin, editor, page }) => {
		// TODO: Drag-drop functionality requires jQuery UI sortable events which don't fire
		// properly with Playwright's evaluate(). This needs investigation into proper
		// drag-drop simulation for jQuery UI widgets in Playwright.
		// Create and save draft post
		await admin.createNewPost({ title: 'Unpublished Post' });
		await editor.saveDraft();
		await expect(page.locator('.editor-post-saved-state')).toContainText('Saved');

		// Navigate to calendar
		await admin.visitAdminPage('index.php', 'page=calendar');
		await page.waitForSelector('.ef-calendar-header');

		// Find the unpublished post in the calendar (use .day-item context to be specific)
		const unpublishedPost = page.locator('.day-item .item-headline.post-title strong:has-text("Unpublished Post")').first();

		// Get source day and post element
		const postItem = unpublishedPost.locator('xpath=ancestor::*[contains(@class, "day-item")]');
		const sourceDayList = unpublishedPost.locator('xpath=ancestor::*[contains(@class, "post-list")]');
		const sourceDayUnit = unpublishedPost.locator('xpath=ancestor::*[contains(@class, "day-unit")]');

		// Get the source day ID
		const sourceDayId = await sourceDayUnit.getAttribute('id');

		// Find a different target day (next or previous)
		const allDays = page.locator('.day-unit');
		const daysCount = await allDays.count();
		let targetDayUnit;

		for (let i = 0; i < daysCount; i++) {
			const dayId = await allDays.nth(i).getAttribute('id');
			if (dayId !== sourceDayId) {
				targetDayUnit = allDays.nth(i);
				break;
			}
		}

		// Use jQuery UI sortable to trigger drag-drop (Playwright's dragTo doesn't work with jQuery UI)
		const postId = await postItem.getAttribute('id');
		const targetDayId = await targetDayUnit.getAttribute('id');

		await page.evaluate(({ postItemId, sourceDayId, targetDayId }) => {
			const $postItem = jQuery('#' + postItemId);
			const $sourceList = jQuery('#' + sourceDayId + ' ul');
			const $targetList = jQuery('#' + targetDayId + ' ul');

			// Detach and move the item
			$postItem.detach();
			$targetList.append($postItem);

			// Trigger sortable stop event to fire AJAX
			$sourceList.sortable('option', 'stop').call($sourceList[0], {}, { item: $postItem });
		}, { postItemId: postId, sourceDayId, targetDayId });

		// Wait for AJAX to complete
		await page.waitForTimeout(1500);

		// Verify post is no longer in the source day
		const postsInSourceDay = await sourceDayList.locator('.item-headline.post-title strong').allTextContents();
		expect(postsInSourceDay).not.toContain('Unpublished Post');
	});

	test.skip('expects a scheduled post can be dragged and dropped', async ({ admin, editor, page }) => {
		// TODO: This test requires scheduling posts, but the WordPress Playwright utilities
		// don't provide a setPublishDateTime method yet. Need to implement manual scheduling
		// via the datepicker UI or wait for the utility method to be added.
	});
});
