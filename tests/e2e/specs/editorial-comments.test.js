/**
 * WordPress dependencies
 */

import { createNewPost } from "@wordpress/e2e-test-utils";

describe("Editorial Comments", () => {

  it("expects a user can create an editorial comment on a post", async () => {
    await createNewPost({title: 'Title'});

    // Save draft manually (WordPress 6.8 - saveDraft() is broken)
    const modifier = process.platform === 'darwin' ? 'Meta' : 'Control';
    await page.keyboard.down(modifier);
    await page.keyboard.press('s');
    await page.keyboard.up(modifier);
    await new Promise( r => setTimeout( r, 3000 ) );

    // todo: Eventually, we should show the "Respond to post" button when a post is saved in Gutenberg
    // without having to reload the page
    await page.reload({ waitUntil: "domcontentloaded" });

    // Wait for page to fully load after reload
    await new Promise( r => setTimeout( r, 2000 ) );

    const COMMENT_TEXT = 'Hello';

    // Use JavaScript click to avoid "not clickable" errors
    await page.evaluate(() => {
        const button = document.querySelector('#ef-comment_respond');
        if (button) button.click();
    });

    await page.type('#ef-replycontent', COMMENT_TEXT);

    const saveReplyButton = await page.$('.ef-replysave');
    await saveReplyButton.click();

    const commentNodes = await page.waitForSelector('#ef-comments .comment-content');

    const comments = await commentNodes.$$eval('p', nodes => nodes.map(n => {
      return n.innerText
    }));

    expect(comments).toContain(COMMENT_TEXT);
  });
});
