# Want to contribute?

So you’ve taken a liking to Edit Flow and decided you want to give back. That's awesome! We'd love to have you help.

Bug Reports
------

Find a bug in Edit Flow? Let us know about it by creating a [new issue](https://github.com/Automattic/Edit-Flow/issues). Some recommendations for filing great bug reports below.

###### Great Bug Reports

**1. Is it really a bug?**

Before filing a bug report, make sure you're running the latest versions of Edit Flow and WordPress. 

Turn off all other plugins and switch to the default WordPress theme. If you still encounter the issue then you might have found a bug.

If the issue dissaepars, it was probably a conflict with one of your plugins or themes. Try activating only Edit flow and that theme or plugin to eliminate other variables. When the issue reappears, you've found the guilty party.

**2. Has the issue already been reported?**

To check if a bug has already been reported, try:
 * Checking the current [list of opened issues](https://github.com/Automattic/Edit-Flow/issues?q=is%3Aopen)
 * Looking through the [Edit Flow Support Forums](https://wordpress.org/support/plugin/edit-flow)

Not mentioned in either of those places? Doesn't appear to be caused by a conflict with another plugin or theme? You've found a bug!

**3. It's all in the details**

The more specific you can be, the easier it will be for someone to tackle the bug. 

When creating a new issue a concise summary and clear description are key. If it's been mentioned by someone else, like on the [Edit Flow Support Forums](https://wordpress.org/support/plugin/edit-flow), include a link.

Here's a sample of what a great summary looks like:

	Summary of the issue: The Edit Flow Calendar module is stuck on February 2

	Steps to reproduce:

	1. *Activate the Calendar module*
	2. *Click the "Calendar" link in the sidebar*

	Expected behavior: *The calendar should highlight today's date*

	Actual behavior: *The calendar higlights February 2 as today's date*

	Screenshots: *screenshot of behavior/error goes here*

Development Setup
------

This plugin uses `wp-env` for development and is required to run the tests written for the plugin. `wp-env` requires Docker so please ensure you have that installed on your system first. To install `wp-env`, use the following command:

```
npm -g i @wordpress/env
```

Read more about `wp-env` [here](https://www.npmjs.com/package/@wordpress/env).

This plugin also uses Composer to manage PHP dependencies. Composer can be downloaded [here](https://getcomposer.org/download/).

###### Getting started

1. Clone the plugin repo: `git clone git@github.com:Automattic/Edit-Flow.git`
2. Change to cloned directory: `cd /path/to/repo`
3. Install PHP dependencies: `composer install`
4. Install NPM dependencies: `npm install`
5. Start dev environment: `wp-env start`

###### Running tests

Ensure that the dev environment has already been started with `wp-env start`.

**PHP Integration Tests:**
1. Integration test: `composer run integration`
2. Multi-site integration test: `composer run integration-ms`

**E2E Tests (Playwright):**
1. Run E2E tests: `npm run test-e2e`
2. Run with visible browser: `npm run test-e2e:headed`
3. Debug mode: `npm run test-e2e:debug`

**JavaScript Tests:**
1. Run Jest tests: `npm run test-jest`

Creating and submitting Patches
------

###### Creating the patch

If you’re fixing a bug, start by forking [Edit Flow's repository](https://github.com/Automattic/Edit-Flow/i) and clone that new fork of Edit Flow to your computer. 

When writing your patch, make sure your code conforms to the [WordPress coding standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/#language-specific-standards). This guide will be used when reviewing your patch.

Also, make sure that your patch is documented correctly. Please follow the [WordPress inline documentation standards](https://make.wordpress.org/core/handbook/best-practices/inline-documentation-standards/#language-specific-standards) when documenting the code in your patch.

###### Submitting the patch

To share the changes you’ve made, you’ll need to push your changes to your repository on GitHub, and submit a pull request.

Keep the first line of your commit message brief. A quick explanation of your changes should do nicely. Go into more details on the following lines.

Not sure what to include your commit message? Take a look at the ["Description" section of the WordPress commit message documentation](https://make.wordpress.org/core/handbook/best-practices/commit-messages/#description). There is great advice for what should be included in when writing a clear, concise and relevant commit message.

After you've commited, push to your fork and create a Pull Request on Github.

Extending Edit Flow
------

Not sure you're ready to write a patch? Why not try extending Edit Flow? Take a look [here](http://editflow.org/extend/) for some ideas on how to extend current Edit Flow functionality.

The [Edit Flow Support Forums](https://wordpress.org/support/plugin/edit-flow) often have requests to add functionality to Edit Flow. Try and see if you can create this functionality by extending Edit Flow without modifying Edit Flow core. 

It's a great way to add functionality to existing Edit Flow installations without having to go through the process of patching Edit Flow core.

(Props to Jetpack. These contributing guidelines were based on the [Contribute](https://jetpack.com/contribute/#contribute) section on the Jetpack website and the [Contributing](https://github.com/Automattic/jetpack/blob/master/.github/CONTRIBUTING.md) section in the [Jetpack Github repository](https://github.com/Automattic/jetpack/))