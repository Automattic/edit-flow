const addCategoryToPost = async (categoryName) => {
    await ensureSidebarOpened();

    // Wait for sidebar to fully load
    await new Promise( r => setTimeout( r, 2000 ) );

    // Debug: Check if sidebar actually opened
    const sidebarState = await page.evaluate(() => {
        const sidebar = document.querySelector('.editor-sidebar, .interface-complementary-area');
        const settingsBtn = document.querySelector('[aria-label="Settings"]');
        return {
            sidebarExists: !!sidebar,
            settingsAriaExpanded: settingsBtn?.getAttribute('aria-expanded')
        };
    });

    if (!sidebarState.sidebarExists) {
        throw new Error('Sidebar did not open');
    }

    // WordPress 6.8: Use Puppeteer XPath to click the Categories toggle
    // IMPORTANT: Must use Puppeteer click, not JavaScript click, for the panel to expand
    const categoriesButtons = await page.$x('//button[contains(@class, "components-panel__body-toggle") and text()="Categories"]');
    if (categoriesButtons.length === 0) {
        // Try a broader search
        const allButtons = await page.evaluate(() => {
            const buttons = Array.from(document.querySelectorAll('button'));
            return buttons
                .filter(btn => btn.textContent.includes('Categor'))
                .map(btn => ({
                    text: btn.textContent.trim(),
                    classes: btn.className
                }));
        });
        throw new Error('Could not find Categories toggle button in sidebar. Found buttons with "Categor": ' + JSON.stringify(allButtons));
    }

    const ariaExpanded = await categoriesButtons[0].evaluate(el => el.getAttribute('aria-expanded'));
    if (ariaExpanded !== 'true') {
        await categoriesButtons[0].click();
        // Wait longer for panel to expand
        await new Promise( r => setTimeout( r, 2000 ) );
    }

    // Click the "Add New Category" button
    await page.waitForSelector(
        '.editor-post-taxonomies__hierarchical-terms-add',
        { timeout: 5000 }
    );

    await page.click(
        '.editor-post-taxonomies__hierarchical-terms-add'
    )

    await page.waitForSelector(
        '.editor-post-taxonomies__hierarchical-terms-input input',
        { timeout: 3000 }
    );

    // Type the category name in the field.
    await page.type(
        '.editor-post-taxonomies__hierarchical-terms-input input',
        categoryName
    );

    await page.click(
        '.editor-post-taxonomies__hierarchical-terms-submit'
    )
}

/**
 * We need to implement our own `publishPost` to test on the admin
 * Updated for WordPress 6.8 which uses a publish panel
 */
const publishPost = async() => {
    // WordPress 6.8: Click "Open publish panel" button
    await page.evaluate(() => {
        const button = Array.from(document.querySelectorAll('button')).find(
            btn => btn.textContent.includes('Open publish panel')
        );
        if (button) button.click();
    });

    // Wait for panel to open
    await new Promise( r => setTimeout( r, 1000 ) );

    // Click the final "Save" or "Publish" button in the panel
    await page.evaluate(() => {
        const buttons = Array.from(document.querySelectorAll('.editor-post-publish-panel button'));
        const publishButton = buttons.find(btn =>
            btn.textContent === 'Save' ||
            btn.textContent === 'Publish' ||
            btn.classList.contains('editor-post-publish-button')
        );
        if (publishButton) publishButton.click();
    });

    // A success notice should show up
    await page.waitForSelector( '.components-snackbar, .components-snackbar-list', { timeout: 10000 } );
}

const schedulePost = async() => {
    // WordPress 6.8: Open the publish panel first
    await page.evaluate(() => {
        const button = Array.from(document.querySelectorAll('button')).find(
            btn => btn.textContent.includes('Open publish panel')
        );
        if (button) button.click();
    });

    await new Promise( r => setTimeout( r, 1000 ) );

    // Click "Publish:Immediately" button to expand the date picker
    await page.evaluate(() => {
        const buttons = Array.from(document.querySelectorAll('.editor-post-publish-panel button'));
        const scheduleButton = buttons.find(btn => btn.textContent.includes('Publish:Immediately'));
        if (scheduleButton) scheduleButton.click();
    });

    await new Promise( r => setTimeout( r, 1000 ) );

    // Calculate future date (3 days from now to stay within calendar view)
    const today = new Date();
    const futureDate = new Date();
    futureDate.setDate( today.getDate() + 3 );
    const futureDay = futureDate.getDate();

    // Click the day button in the calendar picker
    // This properly triggers WordPress React state updates
    await page.evaluate((targetDay) => {
        const dayButtons = Array.from(document.querySelectorAll('.components-datetime__date__day'));
        const dayButton = dayButtons.find(btn => btn.textContent === String(targetDay));
        if (dayButton) dayButton.click();
    }, futureDay);

    // Wait for WordPress to update
    await new Promise( r => setTimeout( r, 1500 ) );

    // Click the Save button (WordPress may not change it to "Schedule" but still saves with future date)
    await page.evaluate(() => {
        const buttons = Array.from(document.querySelectorAll('.editor-post-publish-panel button'));
        const publishButton = buttons.find(btn =>
            (btn.textContent === 'Save' || btn.textContent === 'Schedule') &&
            btn.classList.contains('editor-post-publish-button')
        );
        if (publishButton) publishButton.click();
    });

    // Wait for success notice
    await page.waitForSelector( '.components-snackbar, .components-snackbar-list', { timeout: 10000 } );

    // Wait longer to ensure the post is fully saved before navigating away
    await new Promise( r => setTimeout( r, 2000 ) );
}

const ensureSidebarOpened = async() => {
	// WordPress 6.8: Try to find and click the Settings button to open sidebar
	const settingsButton = await page.$('[aria-label="Settings"]');

	if (settingsButton) {
		const ariaExpanded = await settingsButton.evaluate(el => el.getAttribute('aria-expanded'));
		if (ariaExpanded === 'false') {
			// Use Puppeteer click for reliability
			await settingsButton.click();
			await new Promise( r => setTimeout( r, 1500 ) );
		}
	} else {
		// Try JavaScript click as fallback
		await page.evaluate(() => {
			const btn = document.querySelector('[aria-label="Settings"]');
			if (btn && btn.getAttribute('aria-expanded') === 'false') {
				btn.click();
			}
		});
		await new Promise( r => setTimeout( r, 1500 ) );
	}
}

export { addCategoryToPost, publishPost, schedulePost };

