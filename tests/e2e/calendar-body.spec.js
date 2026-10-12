/**
 * WordPress dependencies
 */
const { test, expect } = require('./utils');

test.describe('Calendar Body', () => {
	test('published posts appear on calendar with published status', async ({ admin, page, requestUtils }) => {
		// Create a published post via REST API (more reliable than UI)
		await requestUtils.createPost({
			title: 'Published Post',
			status: 'publish',
		});

		// Navigate to calendar
		await admin.visitAdminPage('index.php', 'page=calendar');
		await page.locator('.ef-calendar-header').waitFor();

		// Verify the published post appears
		const publishedPost = page.locator('.day-item').filter({ hasText: 'Published Post' }).first();
		await expect(publishedPost).toBeVisible();

		// Cleanup
		await requestUtils.deleteAllPosts();
	});

	test('calendar has draggable functionality enabled', async ({ admin, page }) => {
		await admin.visitAdminPage('index.php', 'page=calendar');
		await page.locator('.ef-calendar-header').waitFor();

		// Verify jQuery UI sortable is initialized on post lists
		const hasSortable = await page.evaluate(() => {
			const postLists = jQuery('.post-list');
			if (postLists.length === 0) return false;

			// Check if sortable is initialized
			const sortableInstance = postLists.first().sortable('instance');
			return sortableInstance !== undefined;
		});

		expect(hasSortable).toBe(true);
	});

	test('scheduled posts appear on calendar', async ({ admin, page, requestUtils }) => {
		// Create a future-dated post via REST API
		const futureDate = new Date();
		futureDate.setDate(futureDate.getDate() + 2);

		await requestUtils.createPost({
			title: 'Scheduled Post',
			status: 'future',
			date: futureDate.toISOString(),
		});

		// Navigate to calendar
		await admin.visitAdminPage('index.php', 'page=calendar');
		await page.locator('.ef-calendar-header').waitFor();

		// Verify the scheduled post appears
		const scheduledPost = page.locator('.day-item').filter({ hasText: 'Scheduled Post' }).first();
		await expect(scheduledPost).toBeVisible();

		// Cleanup
		await requestUtils.deleteAllPosts();
	});

	test('clicking a post opens the quick edit modal', async ({ admin, page, requestUtils }) => {
		await requestUtils.createPost({
			title: 'Quick Edit Post',
			status: 'draft',
		});

		await admin.visitAdminPage('index.php', 'page=calendar');
		await page.locator('.ef-calendar-header').waitFor();

		await page.locator('.day-item').filter({ hasText: 'Quick Edit Post' }).first().click();

		const modal = page.getByRole('dialog', { name: 'Quick Edit Post' });
		await expect(modal).toBeVisible();
		await expect(modal.locator('.item-information')).toBeVisible();
		await expect(modal.getByRole('link', { name: 'Edit', exact: true })).toBeVisible();

		await page.keyboard.press('Escape');
		await expect(modal).toBeHidden();

		// Cleanup
		await requestUtils.deleteAllPosts();
	});

	test('categories can be changed from the quick edit modal', async ({ admin, page, requestUtils }) => {
		const first = await requestUtils.rest({
			method: 'POST',
			path: '/wp/v2/categories',
			data: { name: 'Quick Edit First' },
		});
		const second = await requestUtils.rest({
			method: 'POST',
			path: '/wp/v2/categories',
			data: { name: 'Quick Edit Second' },
		});
		const post = await requestUtils.createPost({
			title: 'Category Post',
			status: 'draft',
			categories: [first.id],
		});

		await admin.visitAdminPage('index.php', 'page=calendar');
		await page.locator('.ef-calendar-header').waitFor();
		await page.locator('.day-item').filter({ hasText: 'Category Post' }).first().click();

		const modal = page.getByRole('dialog', { name: 'Category Post' });
		await modal.locator('.item-information-tax_category .editable-value').click();
		await modal.getByRole('checkbox', { name: 'Quick Edit Second' }).check();
		await modal.getByRole('link', { name: 'Save' }).click();

		// Both categories are kept, not just one.
		await expect(modal.locator('.item-information-tax_category .editable-value')).toHaveText(
			'Quick Edit First, Quick Edit Second'
		);
		const saved = await requestUtils.rest({ path: `/wp/v2/posts/${post.id}`, params: { context: 'edit' } });
		expect(saved.categories.sort()).toEqual([first.id, second.id].sort());

		// Cleanup
		await requestUtils.deleteAllPosts();
		for (const category of [first, second]) {
			await requestUtils.rest({
				method: 'DELETE',
				path: `/wp/v2/categories/${category.id}`,
				params: { force: true },
			});
		}
	});
});
