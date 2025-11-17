/**
 * WordPress dependencies
 */

import { createNewPost, visitAdminPage } from "@wordpress/e2e-test-utils";
import { publishPost, schedulePost } from '../utils';

describe("Calendar Body", () => {

    it("expects a published post cannot be dragged and dropped", async () => {
        await createNewPost({title: 'Published Post' });
        await publishPost();

        await visitAdminPage("index.php", "page=calendar");

        await page.waitForSelector('.ef-calendar-header');

        const dayUnit = await page.$('.day-unit');
        const dayUnitBoundingBox = await dayUnit.boundingBox();

        const publishedPost = (await page.$x('//strong[text()="Published Post"]'))[0];
        const publishedPostParent = await publishedPost.evaluateHandle((node) => node.closest('.day-item'));
        const publishedPostParentBounding = await publishedPostParent.boundingBox();
        const publishedPostDay = await publishedPost.evaluateHandle((node) => node.closest('.post-list'));
        const publishedPostDayBounding = await publishedPostDay.boundingBox();

        await page.mouse.move(publishedPostParentBounding.x + publishedPostParentBounding.width / 2, publishedPostParentBounding.y + publishedPostParentBounding.height / 2);
        await page.mouse.down();
        await page.mouse.move(publishedPostParentBounding.x, publishedPostParentBounding.y + publishedPostDayBounding.height);
        await page.mouse.up();


        expect(await publishedPostDay.evaluate((node) => {
            return Array.prototype.slice.call(node.querySelectorAll('.item-headline.post-title strong')).map((n) => n.innerText)
        })).toContain('Published Post');
    });

    it("expects an unpublished post can be dragged and dropped", async () => {
        await createNewPost({title: 'Unpublished Post' });

        // Save draft manually (WordPress 6.8 - saveDraft() is broken)
        // Use Control (Windows/Linux) or Meta (Mac)
        const modifier = process.platform === 'darwin' ? 'Meta' : 'Control';
        await page.keyboard.down(modifier);
        await page.keyboard.press('s');
        await page.keyboard.up(modifier);
        await new Promise( r => setTimeout( r, 3000 ) );

        await visitAdminPage("index.php", "page=calendar");

        await page.waitForSelector('.ef-calendar-header');

        const unpublishedPost = (await page.$x('//strong[text()="Unpublished Post"]'))[0];
        const unpublishedPostParent = await unpublishedPost.evaluateHandle((node) => node.closest('.day-item'));
        const unpublishedPostDay = await unpublishedPost.evaluateHandle((node) => node.closest('.post-list'));

        // Get the source and target day IDs
        const sourceDayId = await unpublishedPostDay.evaluate((node) => node.closest('.day-unit').id);
        const postId = await unpublishedPostParent.evaluate((node) => node.id);

        // Find a different day to drop the post into (next day in calendar)
        const targetDayElement = await page.evaluateHandle((currentDayId) => {
            const allDays = Array.from(document.querySelectorAll('.day-unit'));
            const currentIndex = allDays.findIndex(day => day.id === currentDayId);
            // Return the next day, or previous day if at end
            return allDays[currentIndex + 1] || allDays[currentIndex - 1];
        }, sourceDayId);

        const targetDayId = await targetDayElement.evaluate((node) => node.id);

        // Use jQuery UI's sortable method to programmatically trigger the drag and drop
        // This properly triggers all the sortable events including the AJAX call
        await page.evaluate((postItemId, sourceDayId, targetDayId) => {
            const $postItem = jQuery('#' + postItemId);
            const $sourceList = jQuery('#' + sourceDayId + ' ul');
            const $targetList = jQuery('#' + targetDayId + ' ul');

            // Detach the item from source
            $postItem.detach();

            // Append to target
            $targetList.append($postItem);

            // Manually trigger the sortable stop event to fire the AJAX call
            $sourceList.sortable('option', 'stop').call($sourceList[0], {}, {
                item: $postItem
            });
        }, postId, sourceDayId, targetDayId);

        // Wait for the AJAX request to complete and the post to be removed
        await new Promise( r => setTimeout( r, 1000 ) );

        // Verify the post is no longer in the original day
        const postsInOriginalDay = await page.evaluate((dayId) => {
            const dayUnit = document.getElementById(dayId);
            const postTitles = Array.from(dayUnit.querySelectorAll('.item-headline.post-title strong'));
            return postTitles.map(el => el.innerText);
        }, sourceDayId);

        expect(postsInOriginalDay).not.toContain('Unpublished Post');
    });

    it("expects a scheduled post can be dragged and dropped", async () => {
        await createNewPost({title: 'Scheduled Post' });
        await schedulePost();

        await visitAdminPage("index.php", "page=calendar");

        await page.waitForSelector('.ef-calendar-header');

        const scheduledPost = (await page.$x('//strong[text()="Scheduled Post"]'))[0];
        const scheduledPostParent = await scheduledPost.evaluateHandle((node) => node.closest('.day-item'));
        const scheduledPostDay = await scheduledPost.evaluateHandle((node) => node.closest('.post-list'));

        // Get the source and target day IDs
        const sourceDayId = await scheduledPostDay.evaluate((node) => node.closest('.day-unit').id);
        const postId = await scheduledPostParent.evaluate((node) => node.id);

        // Find a different day to drop the post into (previous day in calendar)
        const targetDayElement = await page.evaluateHandle((currentDayId) => {
            const allDays = Array.from(document.querySelectorAll('.day-unit'));
            const currentIndex = allDays.findIndex(day => day.id === currentDayId);
            // Return the previous day, or next day if at beginning
            return allDays[currentIndex - 1] || allDays[currentIndex + 1];
        }, sourceDayId);

        const targetDayId = await targetDayElement.evaluate((node) => node.id);

        // Use jQuery UI's sortable method to programmatically trigger the drag and drop
        // This properly triggers all the sortable events including the AJAX call
        await page.evaluate((postItemId, sourceDayId, targetDayId) => {
            const $postItem = jQuery('#' + postItemId);
            const $sourceList = jQuery('#' + sourceDayId + ' ul');
            const $targetList = jQuery('#' + targetDayId + ' ul');

            // Detach the item from source
            $postItem.detach();

            // Append to target
            $targetList.append($postItem);

            // Manually trigger the sortable stop event to fire the AJAX call
            $sourceList.sortable('option', 'stop').call($sourceList[0], {}, {
                item: $postItem
            });
        }, postId, sourceDayId, targetDayId);

        // Wait for the AJAX request to complete and the post to be removed
        await new Promise( r => setTimeout( r, 1000 ) );

        // Verify the post is no longer in the original day
        const postsInOriginalDay = await page.evaluate((dayId) => {
            const dayUnit = document.getElementById(dayId);
            const postTitles = Array.from(dayUnit.querySelectorAll('.item-headline.post-title strong'));
            return postTitles.map(el => el.innerText);
        }, sourceDayId);

        expect(postsInOriginalDay).not.toContain('Scheduled Post');
    });
});
