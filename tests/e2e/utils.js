/**
 * WordPress dependencies
 */
const { test: base, expect } = require('@wordpress/e2e-test-utils-playwright');

/**
 * Base test from the WordPress Playwright utils, with Edit Flow fixtures.
 *
 * The tests run against the development site, so they must not touch content
 * they didn't create. A test adds the ID of each post it creates to
 * `createdPostIds`, and the fixture deletes those posts once the test has
 * finished, whether it passed or failed.
 */
const test = base.extend({
	createdPostIds: async ({ requestUtils }, use) => {
		const ids = [];
		await use(ids);
		await Promise.all(
			ids.map((id) =>
				requestUtils.rest({
					method: 'DELETE',
					path: `/wp/v2/posts/${id}`,
					params: { force: true },
				})
			)
		);
	},
});

/**
 * Returns the ID of the post open in the block editor.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @return {Promise<number>} Post ID.
 */
function getEditorPostId(page) {
	return page.evaluate(() => window.wp.data.select('core/editor').getCurrentPostId());
}

module.exports = { test, expect, getEditorPostId };
