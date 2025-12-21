# Edit Flow

Contributors: batmoo, danielbachhuber, sbressler, automattic  
Donate link: http://editflow.org/contribute/  
Tags: workflow, editorial, editorial calendar, custom status, newsroom  
Requires at least: 6.4  
Requires PHP: 7.4  
Tested up to: 6.9  
Stable tag: 0.9.9  

Redefining your editorial workflow.

## Description

Edit Flow empowers you to collaborate with your editorial team inside WordPress. We've made it modular so you can customize it to your needs:

* [Calendar](http://editflow.org/features/calendar/) - A convenient month-by-month look at your content.
* [Custom Statuses](http://editflow.org/features/custom-statuses/) - Define the key stages to your workflow.
* [Editorial Comments](http://editflow.org/features/editorial-comments/) - Threaded commenting in the admin for private discussion between writers and editors.
* [Editorial Metadata](http://editflow.org/features/editorial-metadata/) - Keep track of the important details.
* [Notifications](http://editflow.org/features/notifications/) - Receive timely updates on the content you're following.
* [Story Budget](http://editflow.org/features/story-budget/) - View your upcoming content budget.
* [User Groups](http://editflow.org/features/user-groups/) - Keep your users organized by department or function.

More details for each feature, screenshots and documentation can be found on [our website](http://editflow.org/).

We'd love to hear from you! For support questions, feedback and ideas, please use the [WordPress.org forums](http://wordpress.org/tags/edit-flow?forum_id=10), which we look at often. If you'd like to contribute code, [we'd love to have you involved](http://editflow.org/contribute/).

## Installation

The easiest way to install this plugin is to go to Add New in the Plugins section of your blog admin and search for "Edit Flow." On the far right side of the search results, click "Install."

If the automatic process above fails, follow these simple steps to do a manual install:

1. Extract the contents of the zip file into your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Write and enjoy the merits of a structured editorial workflow!

## Development

This plugin uses `wp-env` for development and is required to run the tests written for the plugin. `wp-env` requires Docker so please ensure you have that installed on your system first. To install `wp-env`, use the following command:

```
npm -g i @wordpress/env
```

Read more about `wp-env` [here](https://www.npmjs.com/package/@wordpress/env).

This plugin also uses Composer to manage PHP dependencies. Composer can be downloaded [here](https://getcomposer.org/download/).

### Getting started

1. Clone the plugin repo: `git clone git@github.com:Automattic/Edit-Flow.git`
2. Changed to cloned directory: `cd /path/to/repo`
3. Install PHP dependencies: `composer install`
4. Install NPM dependencies: `npm install`
5. Start dev environment: `wp-env start`

### Running tests

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

## Frequently Asked Questions

### Does Edit Flow work with multisite?

Yep, in the sense that you can activate Edit Flow on each subsite. Edit Flow doesn't yet offer the ability to manage content across a network of sites.

### Edit Flow doesn't do X, Y, and Z. That makes me sad.

All development happens on [GitHub](https://github.com/Automattic/Edit-Flow).

For support questions, feedback and ideas, please use the [WordPress.org forums](http://wordpress.org/tags/edit-flow?forum_id=10), which we look at often. For everything else, say [hello@editflow.org](mailto:hello@editflow.org).

## Screenshots

1. The calendar is a convenient month-by-month look at your content. Filter to specific statuses or categories to drill down.
2. Custom statuses allow you to define the key stages of your workflow.
3. Editorial comments allow for private discussion between writers and editors on a post-by-post basis.
4. Keep track of the important details with editorial metadata.
5. View all of your upcoming posts with the more traditional story budget view, and hit the print button to take it to your planning meeting.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full changelog.
