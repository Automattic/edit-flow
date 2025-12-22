# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

* fix: scope module asset loading to relevant pages only by @GaryJones in [#858](https://github.com/Automattic/Edit-Flow/pull/858)
* fix: allow text selection in calendar overlay without triggering drag by @GaryJones in [#857](https://github.com/Automattic/Edit-Flow/pull/857)
* fix: update post date to current time when publishing from custom status by @GaryJones in [#856](https://github.com/Automattic/Edit-Flow/pull/856)
* fix: resolve calendar drag-and-drop not persisting post date changes by @GaryJones in [#854](https://github.com/Automattic/Edit-Flow/pull/854)
* fix: guard against null return from get_edit_post_link() and get_permalink() by @GaryJones in [#853](https://github.com/Automattic/Edit-Flow/pull/853)
* fix: prevent get_custom_statuses() from corrupting WordPress's term cache by @GaryJones in [#852](https://github.com/Automattic/Edit-Flow/pull/852)
* fix: add Private option to bulk edit status dropdown by @GaryJones in [#851](https://github.com/Automattic/Edit-Flow/pull/851)
* fix: resolve stale cache causing incorrect display in custom status Quick Edit by @GaryJones in [#849](https://github.com/Automattic/Edit-Flow/pull/849)
* fix: add type guard to prevent PHP warning in preview link filter by @GaryJones in [#834](https://github.com/Automattic/Edit-Flow/pull/834)

### Added

* feat(custom-status): add status migration tool and WP-CLI commands by @GaryJones in [#859](https://github.com/Automattic/Edit-Flow/pull/859)
* feat(notifications): add Post Author and Auto-subscribed badges by @GaryJones in [#847](https://github.com/Automattic/Edit-Flow/pull/847)
* feat(story-budget): improve UX with Screen Options and collapsible categories by @GaryJones in [#846](https://github.com/Automattic/Edit-Flow/pull/846)

### Changed

* style(story-budget): refresh print stylesheet for modern WordPress by @GaryJones in [#848](https://github.com/Automattic/Edit-Flow/pull/848)

### Maintenance

* chore: rename workflow to match plugin standards by @GaryJones in [#850](https://github.com/Automattic/Edit-Flow/pull/850)
* chore: migrate build system from webpack to wp-scripts by @GaryJones in [#845](https://github.com/Automattic/Edit-Flow/pull/845)
* chore: simplify ESLint config after eslint-plugin-wpvip fix by @GaryJones in [#844](https://github.com/Automattic/Edit-Flow/pull/844)
* ci: standardise test matrix and update readme by @GaryJones in [#840](https://github.com/Automattic/Edit-Flow/pull/840)
* chore: migrate to ESLint 9 with flat config by @GaryJones in [#839](https://github.com/Automattic/Edit-Flow/pull/839)
* chore: migrate dependabot reviewers to CODEOWNERS by @GaryJones in [#836](https://github.com/Automattic/Edit-Flow/pull/836)
* ci: Improve CI infrastructure and expand PHP compatibility by @GaryJones in [#835](https://github.com/Automattic/Edit-Flow/pull/835)
* ci: pin E2E tests to Ubuntu 22.04 for Playwright compatibility by @GaryJones in [#833](https://github.com/Automattic/Edit-Flow/pull/833)

## [0.9.9] - 2024-05-24

* Enhancements: bump lowest supported PHP version to 8.0 and lowest WordPress version to 6.0 ([#727](https://github.com/Automattic/Edit-Flow/pull/727))
* Test fix: Update ESLint configuration and format JS files ([#723](https://github.com/Automattic/Edit-Flow/pull/723))
* Enhancements: Move JS environment to node 20, upgrade packages ([#725](https://github.com/Automattic/Edit-Flow/pull/725))

## [0.9.8] - 2024-04-10

* Fix WP 5.9 deprecation notice with `who` in `WP_User_Query` ([#701](https://github.com/Automattic/Edit-Flow/pull/701))
* Add PHP 8.2 fixes ([#700](https://github.com/Automattic/Edit-Flow/pull/700))
* Bump @babel/traverse from 7.1.6 to 7.23.2 ([#714](https://github.com/Automattic/Edit-Flow/pull/714))

## [0.9.7] - 2022-08-26

* Bug fix: Allow scheduled posts to be shifted around on calendar ([#614](https://github.com/Automattic/Edit-Flow/pull/614))
* Bug fix: Add back unpublish status, small css tweak for statuses ([#613](https://github.com/Automattic/Edit-Flow/pull/613))
* Enhancements: Renaming some frontend calendar configuration variable ([#615](https://github.com/Automattic/Edit-Flow/pull/615))
* Bug fix: Swap join() with implode() for PHP 8 compatibility ([#627](https://github.com/Automattic/Edit-Flow/pull/627))
* Enhancements: Give each attribute in Edit Flow settings page a different id ([#650](https://github.com/Automattic/Edit-Flow/pull/650))
* Bug fix: Fix test_story_budget_set_number_days_filter_invalid in PHP 8.0 ([#644](https://github.com/Automattic/Edit-Flow/pull/644))
* Bug fix: Allow updating post slugs in Quick Edit and Classic Editor with a different status from published ([#648](https://github.com/Automattic/Edit-Flow/pull/648))
* Bug fix: Fix jQuery warnings as WordPress upgraded it from 1.12.4-wp to 3.5.1 ([#649](https://github.com/Automattic/Edit-Flow/pull/649))
* Enhancements: Remove redundant jQuery selector #the-list a.editinline ([#668](https://github.com/Automattic/Edit-Flow/pull/668))
* Enhancements: Remove redundant jQuery selector #toggle_details ([#669](https://github.com/Automattic/Edit-Flow/pull/669))
* Bug fix: Fixes non-variable being passed by reference ([#667](https://github.com/Automattic/Edit-Flow/pull/667))

## [0.9.6] - 2020-04-26

* Bug fix: Fix bug causing error on save button click for post after hovering over button ([#604](https://github.com/Automattic/Edit-Flow/pull/604))
* Enhancements: Bring Gutenberg to Calendar filters ([#603](https://github.com/Automattic/Edit-Flow/pull/603))
* Enhancements: Tweak styling on Calendar ([#605](https://github.com/Automattic/Edit-Flow/pull/605))

## [0.9.5] - 2020-04-08

* Bug fix: Fix bug preventing previewing posts authored by other users ([#597](https://github.com/Automattic/Edit-Flow/pull/597) -- props mallorydxw)
* Improvement: Status dropdowns are now be Edit Flow specific but filterable ([#595](https://github.com/Automattic/Edit-Flow/pull/595))
* Improvement: Support i18n in Story Budget date picker ([#569](https://github.com/Automattic/Edit-Flow/pull/569))
* Bug fix: Prevent multiple custom fields from being created ([#591](https://github.com/Automattic/Edit-Flow/pull/591))
* Bug fix: Fix slug behavior ([#575](https://github.com/Automattic/Edit-Flow/pull/575))

## [0.9.4] - 2020-02-04

* Bug fix: Move 'Customize' link in metadata metabox to inside metabox ([#590](https://github.com/Automattic/Edit-Flow/pull/590) -- props jsit)
* Bug fix: Fix ef_custom_status_list filter on get_custom_statuses ([#587](https://github.com/Automattic/Edit-Flow/pull/587) -- props jsit)
* Bug fix: Keep save button text updated, without errors ([#585](https://github.com/Automattic/Edit-Flow/pull/585) -- props WPprodigy)
* Bug fix: Add ef_week_first_day as script data to prevent echoing script tags ([#582](https://github.com/Automattic/Edit-Flow/pull/582) -- props ryelle)
* Improvement: Deprecate ef_get_comments_plus ([#581](https://github.com/Automattic/Edit-Flow/pull/581) -- props WPprodigy)
* Improvement: Cleanup block editor compatibility logic ([#580](https://github.com/Automattic/Edit-Flow/pull/580) -- props WPprodigy)
* Improvement: Display custom statuses in post states ([#579](https://github.com/Automattic/Edit-Flow/pull/579) -- props WPprodigy)
* Improvement: Clean up dashboard-note logic ([#578](https://github.com/Automattic/Edit-Flow/pull/578) -- props WPprodigy)

## [0.9.3] - 2019-12-18

* Bug fix: parse date time from numeric string instead of textual date ([#546](https://github.com/Automattic/Edit-Flow/pull/546) -- props batmoo, cojennin)
* Bug fix: ensure status friendly names are used in notifications ([#560](https://github.com/Automattic/Edit-Flow/pull/560) -- props batmoo, cojennin)
* Bug fix: fix WP Menu post title notice ([#552](https://github.com/Automattic/Edit-Flow/pull/552) -- props batmoo, cojennin)
* Updates to tests, build pipeline (props batmoo, dchymko, cojennin)

## [0.9.2] - 2019-11-24

* Bug fix: Prevent issues with scheduling and trashing posts when using the block editor ([#556](https://github.com/Automattic/Edit-Flow/pull/556) -- props cojennin, davisshaver, batmoo)

## [0.9.1] - 2019-11-04

* Bug fix: Prevent custom status from being reverted when using Gutenberg ([#521](https://github.com/Automattic/Edit-Flow/pull/521) -- props mikeyarce, batmoo)
* Bug fix: Don't break post previews when using custom statuses ([#515](https://github.com/Automattic/Edit-Flow/pull/515) -- props rebeccahum)
* Bug fix: Don't auto-subscribe the current user when notification settings are changed ([#540](https://github.com/Automattic/Edit-Flow/pull/540) -- props dchymko, jerclarke, mikeyarce)
* Bug fix: prevent fatals when editing posts on the frontend via certain plugins ([#538](https://github.com/Automattic/Edit-Flow/pull/538) -- props kjohnson, dchymko)
* Bug fix: Don't break classic editor when custom statuses are disabled ([#537](https://github.com/Automattic/Edit-Flow/pull/537) -- props batmoo, thesquaremedia)
* Bug fix: Prevent `count()` warning on PHP 7.2 ([#534](https://github.com/Automattic/Edit-Flow/pull/534) -- props batmoo, NeilWJames)
* Bug fix: Prevent multiple plugin entries on the plugins page ([#530](https://github.com/Automattic/Edit-Flow/pull/530) -- props batmoo, mboynes, joshbetz)
* Bug fix: jQuery compatibility issues ([#499](https://github.com/Automattic/Edit-Flow/pull/499) -- props jameslesliemiller)
* Dev: Fix Travis CI Tests (props dchymko)

## [0.9] - 2018-01-10

* Feature: Block Editor compatibility for Custom Status module. Props rinatkhaziev. See BLOCKS.md for details.
* Feature: new filter `ef_calendar_item_html` for Calendar module that allows to print custom markup for each day.
* UI Improvement: start removing arbitrary colors and conform to WP Color guide.
* UI Improvement: Add [NO ACCESS] and [NO EMAIL] to the list of notified users.

## [0.8.3] - 2018-06-14

* UI Improvement: Made primary buttons on Settings screen consistent with WordPress UI. Props cojennin.
* UI Improvement: Display who particularly was notified about an editorial comment. Props goodguyry, WPprodigy.
* Improvement: Updated Russian translation and documentation. Props achumakov.
* Improvement: Eliminate a few cases of raw SQL queries in favor of `date_query`. Props justnorris.
* Improvement: Cache calendar items for each user individually to prevent potential cache pollution. Props justnorris.
* Improvement: various i18n updates.
* Improvement: Move ef_story_budget_posts_query_args filter down to allow overriding the date query in Story Budget module.
* Improvement: Limit results in Calendar to 200 per page.
* Improvement: Don't generate rewrite rules for notepad as they're unused.
* Improvement: Support modifying HTML output of a Calendar day via ef_pre_calendar_single_date_item_html filter. Props cklosowski.
* Improvement: show custom post statuses in Calendar and Story Budget. Props mikeyarce.
* WordPress Coding Standards improvements and code cleanup. Props justnorris.
* Bug fix: Prevent user from removing "Draft" post status.
* Bug fix: Fix ef_pre_insert_editorial_comment filter. Props sudar.
* Bug fix: Fix PHP Warning: array_map(): Argument #2 should be an array.
* Bug fix: Always offset post times to UTC+0 for Calendars. Props justnorris.
* Bug fix: Use taxonomy when checking for term existence. Props shadyvb.
* Bug fix: Correctly handle screen options update for Story Budget columns. Props raduconst.
* Bug fix: EF_Calendar::get_beginning_of_week and EF_Calendar::get_ending_of_week should respect DST. Props FewKinG.
* Bug fix: Build home_url() previews with trailing slash. Props jeremyfelt.

## [0.8.2] - 2016-09-16

* Improvement: Updated Spanish localization. Props moucho.
* Improvement: New Swedish localization. Props Warpsmith.
* Improvement: Japanese localization 100% on translate.wordpress.org.
* Improvement: Updated Brazilian Portuguese translation. Props arthurdapaz.
* Improvement: Internationalization improvements in settings and calendar. Props robertsky.
* Improvement: Updates Travis CI to support containerization, PHP 7 and HHVM.
* Bug fix: Fix PHP warning in class-module.php. Props jerclarke.
* Bug fix: Add label to Dashboard Notes so it displays as "Dashboard Notes" when exporting.
* Bug fix: Clean up PHP code to comply with PHP Strict Standards.
* Bug fix: Removed deprecated get_currentuserinfo. Props kraftbj.
* Bug fix: Adding $post param to preview_post_link filter. Props micahwave.
* Bug fix: Calendar current_user_can capability check corrected. Props keg.
* Bug fix: Clean up custom status timestamp fix and add unit tests.
* Bug fix: Fix error messaging for module settings pages. Props natebot.
* Bug fix: Add check on user-settings.php to prevent error. Props paulabbott.
* Bug fix: Add check for empty author when sending notification. Props petenelson.
* Bug fix: Remove PHP4 constructor from screen options. Props mjangda.

## [0.8.1] - 2016-04-13

* New German localization. Props Circleview.
* New Spanish localization. Props Andrew Kurtis.
* Performance improvements for the calendar, custom statuses, and editorial metadata.
* Bug fix: Show "(no title)" on the calendar when a post doesn't have a title.
* Bug fix: Persist the future date position of a post on the calendar when a post is updated.

## [0.8] - 2013-12-19

* New feature: Dashboard Notepad.
* New feature: Double-click to create a new post on the calendar. Props bbrooks, cojennin.
* Post subscriptions are now saved via AJAX. Props cojennin.
* Subscribe to a post's updates using a quick "Follow" link.
* Assign a date and time to editorial metadata's date field. Props cojennin.
* Modify which filters are used on the calendar and story budget. Props cojennin.
* Scheduled publication time is now included in relevant email notifications. Props mattoperry.
* Calendar and story budget module descriptions link to their respective pages. Props rgalindo05.
* New Russian localization. Props te-st.ru.
* Updated Japanese localization. Props naokomc.
* Updated Dutch localization. Props kardotim.
* Bug fix: User group selection no longer appears in network admin.
* Improved slug generation when changing title of Drafts. Props natebot.
* Bug fix: Permalink slugs are now editable after initial save. Props nickdaugherty.
* Bug fix: Permit calendar filters to be properly reset.
* Bug fix: Posts, pages, custom post types can now be previewed correctly.
* Bug fix: Fix Strict Standards PHP notice with add_caps_to_role(). Props azizur.
* Bug fix: PHP compatibility issue. Props ziz.
* Bug fix: Correct calendar encoding. Props willvanwazer.
* Bug fix: Check for $screen in filter_manage_posts_column. Props styledev.
* Bug fix: Correct Edit Flow icon size. Props Fstop.
* Improvement: Add editorial metadata to the Posts screen. Props drrobotnik.
* Improvement: Visual support for MP6. Props keoshi.
* Bug fix: Catch WP_Error returning with get_terms(). Props paulgibbs.
* Improvement: Better unit testing with PHPUnit. Props willvanwazer, mbijon.
* Bug fix: Correctly close out list item in editorial comments. Props jkovis.

## [0.7.6] - 2013-01-30

* Bug fix for 3.4.2 compatibility.

## [0.7.5] - 2013-01-29

* New Japanese localization. Props naokomc.
* New French localization. Props boris-hocde.
* Allow custom post statuses to be completely disabled for a post type.
* Better implementation of editable slugs hack. Props cojennin.
* Editorial metadata names can now be up to 200 characters. Props cojennin.
* Bug fix: Load modules on 'init' for proper translation.
* Bug fix: Pagination functional again when filtering to a post type.
* Bug fix: Pre-PHP 5.2.9 array_unique() compatibility.
* Bug fix: Respect the timezone when indicating which day is Today.
* Bug fix: Calendar should work for all post types.

## [0.7.4] - 2012-11-21

* Added 'Scheduled' as one of the statuses you see in the 'Posts At A Glance' widget.
* Sort posts on the Manage Posts view by visible editorial metadata date fields.
* Modify email notifications with two filters.
* Bug fix: Proper support for unicode characters in custom status and editorial metadata descriptions.
* Bug fix: Show the proper last modified value on the story budget. Props danls.
* Bug fix: Make the jQuery UI theme more specific. Props danls.
* Bug fix: Use the proper singular label for a post type when generating notification text.
* Bug fix: Post slug now updates when the post has a custom status.
* Bug fix: When determining whether a user can change a post's status, check the 'edit_posts' cap.

## [0.7.3] - 2012-07-03

* Bug fix: Support PHP 5.2.x by removing anonymous functions.
* Bug fix: Only update user's Story Budget saved filters when the Story Budget is being viewed.

## [0.7.2] - 2012-07-03

* Users without the 'publish_posts' capability can now use and change custom statuses. Props Daniel Chesterton.
* Support for trashing posts from the calendar. Props Dan York.
* Updated codebase to use PHP5-style OOP references.
* Fixed some script and stylesheet references that had a double '//' in the URI path.
* New `edit_flow_supported_module_post_types_args` filter.

## [0.7.1] - 2012-04-11

* Show the year on the calendar and story budget if it's not the current year.
* Allow users to save post subscriptions the first time they save the post.
* Changed the behavior of notifications for the user changing a status or leaving a comment.
* New Italian localization. Props Luca Patane.
* Bug fix: Auto-subscribe the post author to their posts by default.
* Bug fix: Only show authors in the user dropdown for the calendar and the story budget.
* Bug fix: Metaboxes are registered with proper priority. Props benbalter.
* Bug fix: If a user hasn't ever opened the calendar before, the date should default to today.
* Bug fix: Prevent editorial metadata filters from stomping on others' uses.
* Bug fix: Specify a max-width on `<select>` dropdowns.

## [0.7] - 2012-01-09

* Entire plugin was rewritten into a modular architecture.
* Calendar is far more functional with drag and drop.
* Custom statuses can be drag and drop ordered with AJAX.
* Editorial Metadata terms can be ordered with AJAX.
* Story Budget shows "viewable" editorial metadata.
* Notifications/subscriptions are filtered.
* Important note: If upgrading from pre-v0.6, please upgrade to v0.6.5 first.

## [0.6.5] - 2011-09-19

* Bug fix: Workaround for a bug in core where the timestamp is set when a post is saved with a custom status.

## [0.6.4] - 2011-07-22

* Display unpublished custom statuses inline with the post title.
* New number type for editorial metadata.
* Dropped the admin option for disabling custom statuses on posts.
* Add a 'Clear' link to editorial metadata date fields.
* Bug fix: Proper support for bulk editing custom statuses.
* Bug fix: Contributor saving a new post respects the default custom status.
* Bug fix: Better respect for user roles and capabilities in Story Budget.
* Bug fix: Custom statuses in Quick Edit now work as expected.
* Bug fix: Show all taxonomy terms on the Story Budget.
* Bug fix: Display message if there are no editorial metadata fields.

## [0.6.3] - 2011-03-21

* Restored email notifications to old delivery method.
* Better approach to including files for Windows systems.
* Option to see all unpublished content on story budget and editorial calendar.

## [0.6.2] - 2011-01-26

* Bug fix: Post Titles were broken in email notifications.
* Bug fix: Bulk editing would cause editorial metadata to occasionally be deleted.

## [0.6.1] - 2011-01-09

* Custom Post Type support for custom post statuses, editorial metadata, editorial comments, notifications.
* Added search and filtering tools for user and usergroup lists.
* Email notifications are now queued to improve performance.
* Posts in calendar now have a unique classname based on the status.
* The "Posts I'm Following" widget has a cleaner look.
* Various bug fixes.

## [0.6] - 2010-11-09

* New feature: Editorial Metadata with customizable field types.
* New feature: Story Budget.
* Completely rewritten calendar view.
* Temporarily disabled QuickPitch widget.
* Bug fix: Editorial comments should no longer show up in the stock Recent Comments widget.
* Bug fix: Duplicate custom post statuses and usergroups are handled in more sane ways.

## [0.5.3] - 2010-10-06

* Fixes issue where default Custom Statuses and User Groups were returning even after being deleted.

## [0.5.1] - 2010-07-29

* Editorial calendar improvements: filter by category or author.
* QuickPitch stories get default status instead of pitch status.
* No email notifications for "Auto Draft" post status.
* Backwards compatibility with WordPress 2.9.x.

## [0.5] - 2010-07-03

* Calendar view for visualizing and spec assignments at a glance.
* Improvements for WordPress 3.0 compatibility.

## [0.4]

* Users that edit a post automatically get subscribed to that post.
* Edit Flow automatically hides editorial comments if the plugin is disabled.
* Moved default custom status additions to upgrade function.
* Bug fix: remove editorial comments from comments feed.

## [0.3.3] - 2010-02-04

* Added tooltips with descriptions to the Status dropdown and Status Filter links.
* Fixed the issue where subscribed users/usergroups were not receiving notifications.

## [0.3.2] - 2010-01-28

* Fixed fatal error if notifications were disabled.

## [0.3.1]

* Small bug fixes.

## [0.3]

* Edit Flow now requires 2.9+.
* Notification emails on status change now have specific subject lines.
* Usergroups!
* Added "Always notify admin option".
* Added option to hide the status dropdown on Post and Page edit pages.
* Added option to globally disable QuickPitch widget.
* Bug fix: Custom Status names cannot be longer than 20 chars.
* Bug fix: Deleted users are removed as subscribers from posts.

## [0.2]

* Custom Statuses are now supported for pages.
* Editorial Comments (with threading).
* Email Notifications (on post status change and editorial comment).
* Additional Post metadata.
* Quick Pitch Dashboard widget.
* Bug fix: sorting issue on Manage Posts page.

## [0.1.5]

* Ability to assign custom statuses to posts.

[Unreleased]: https://github.com/Automattic/Edit-Flow/compare/0.9.9...HEAD
[0.9.9]: https://github.com/Automattic/Edit-Flow/compare/0.9.8...0.9.9
[0.9.8]: https://github.com/Automattic/Edit-Flow/compare/0.9.7...0.9.8
[0.9.7]: https://github.com/Automattic/Edit-Flow/compare/0.9.6...0.9.7
[0.9.6]: https://github.com/Automattic/Edit-Flow/compare/0.9.5...0.9.6
[0.9.5]: https://github.com/Automattic/Edit-Flow/compare/0.9.4...0.9.5
[0.9.4]: https://github.com/Automattic/Edit-Flow/compare/0.9.3...0.9.4
[0.9.3]: https://github.com/Automattic/Edit-Flow/compare/0.9.2...0.9.3
[0.9.2]: https://github.com/Automattic/Edit-Flow/compare/0.9.1...0.9.2
[0.9.1]: https://github.com/Automattic/Edit-Flow/compare/0.9...0.9.1
[0.9]: https://github.com/Automattic/Edit-Flow/compare/0.8.3...0.9
[0.8.3]: https://github.com/Automattic/Edit-Flow/compare/0.8.2...0.8.3
[0.8.2]: https://github.com/Automattic/Edit-Flow/compare/0.8.1...0.8.2
[0.8.1]: https://github.com/Automattic/Edit-Flow/compare/0.8...0.8.1
[0.8]: https://github.com/Automattic/Edit-Flow/compare/0.7.6...0.8
[0.7.6]: https://github.com/Automattic/Edit-Flow/compare/0.7.5...0.7.6
[0.7.5]: https://github.com/Automattic/Edit-Flow/compare/0.7.4...0.7.5
[0.7.4]: https://github.com/Automattic/Edit-Flow/compare/0.7.3...0.7.4
[0.7.3]: https://github.com/Automattic/Edit-Flow/compare/0.7.2...0.7.3
[0.7.2]: https://github.com/Automattic/Edit-Flow/compare/0.7.1...0.7.2
[0.7.1]: https://github.com/Automattic/Edit-Flow/compare/0.7...0.7.1
[0.7]: https://github.com/Automattic/Edit-Flow/compare/0.6.5...0.7
[0.6.5]: https://github.com/Automattic/Edit-Flow/compare/0.6.4...0.6.5
[0.6.4]: https://github.com/Automattic/Edit-Flow/compare/0.6.3...0.6.4
[0.6.3]: https://github.com/Automattic/Edit-Flow/compare/0.6.2...0.6.3
[0.6.2]: https://github.com/Automattic/Edit-Flow/compare/0.6.1...0.6.2
[0.6.1]: https://github.com/Automattic/Edit-Flow/compare/0.6...0.6.1
[0.6]: https://github.com/Automattic/Edit-Flow/compare/0.5.3...0.6
[0.5.3]: https://github.com/Automattic/Edit-Flow/compare/0.5.1...0.5.3
[0.5.1]: https://github.com/Automattic/Edit-Flow/compare/0.5...0.5.1
[0.5]: https://github.com/Automattic/Edit-Flow/compare/0.4...0.5
[0.4]: https://github.com/Automattic/Edit-Flow/compare/0.3.3...0.4
[0.3.3]: https://github.com/Automattic/Edit-Flow/compare/0.3.2...0.3.3
[0.3.2]: https://github.com/Automattic/Edit-Flow/compare/0.3.1...0.3.2
[0.3.1]: https://github.com/Automattic/Edit-Flow/compare/0.3...0.3.1
[0.3]: https://github.com/Automattic/Edit-Flow/compare/0.2...0.3
[0.2]: https://github.com/Automattic/Edit-Flow/compare/0.1.5...0.2
[0.1.5]: https://github.com/Automattic/Edit-Flow/releases/tag/0.1.5
