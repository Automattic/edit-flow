## Publishing a New Release

### Testing

It's important to make sure that the release has been thoroughly tested before it's published. This will get added to over time but at a minimum, we should make sure basics of all modules work without issue.

- Custom Status: New posts are set to default custom status (for both Block and Classic Editors).
- Custom Status: Status can be changed to another custom one (for both Block and Classic Editors).
- Custom Status: Publishing and unpublishing works (for both Block and Classic Editors).
- Custom Status: Enabling and disabling the feature works as expected (for both Block and Classic Editors).
- Editorial Comments: Can add comments and reply to them (for both Block and Classic Editors).
- Notifications: Can follow and unfollow posts (works for both Block and Classic Editors).
- Editorial Metadata: Metabox renders and can add and remove data (for both Block and Classic Editors).
- Calendar: Renders as expected; posts are displayed on the calendar; drag-and-drop works; clicking a post shows more details; filters work.
- Story Budget: Renders as expected; filters work.
- ...

### Preparing

1. Build JavaScript assets.

We need to make sure we are at the latest versions of all built assets (just in case there were conflicts or a step was missed in a PR). We'll automate this step in the future.

```
npm install
npm run build
```

If there are any changed JavaScript files, we'll need to examine what's different in the files, verify the changes, and create a new PR and merge them to master. After that, we should do another round of testing before proceeding.

2. Prepare changelog.

From the commit list (https://github.com/Automattic/Edit-Flow/commits/master), find the last release and work up till now to collect all merged PRs and changes.

Compile the updates and add them to both `README.md` and `readme.txt` files.

The format for updates should be:

```
(Feature|Improvement|UI Improvement|Bug fix): A brief description of the change (https://github.com/path/to/PR -- props user1, user2)
```

"Feature" is for a new addition or piece of functionality; "Improvement" and "UI Improvement" are for enhancements to existing functionality; "Bug fix" is for, well, bug fixes :)

Ideally, we should give props to the person who reported the issue (and made additional meaningful contributions to it, e.g. further details, reproduction steps) and anyone who contributed to the PR.

3. Add upgrade notice

Summarize the upgrade and add it to the "Upgrade Notice" section in both `README.md` and `readme.txt` files.

4. Bump version numbers.

- `readme.txt`: Update the `Stable tag` value to the new version.
- `edit_flow.php`: Update the `Version` value in the plugin header.
- `edit_flow.php`: Update the `EDIT_FLOW_VERSION` constant.

5. PR the changes.

Push up the readme and version changes to a new PR and merge.

6. Create the release on GitHub.

Go to the repository's Releases page, click "Draft a new release", create a new tag (e.g., `1.0.0`), and publish the release. This triggers the automated deployment workflow.

### Publishing

Publishing is automated via GitHub Actions. When you create a GitHub Release:

1. **Create GitHub Release**

   Go to the repository's Releases page and click "Draft a new release". Create a new tag (e.g., `1.0.0`) and publish the release.

2. **Automated deployment**

   The `.github/workflows/deploy.yml` workflow will:
   - Build JavaScript assets (`npm ci && npm run build`)
   - Deploy to WordPress.org SVN
   - Attach a zip file to the GitHub Release

   The workflow uses the `.distignore` file to exclude development files from the distribution.

3. **Test**

   From a test site, download the latest version and make sure things work as expected.

   For a quick-and-easy test site, use https://jurassic.ninja

4. **Celebrate**

   We're done! High five! Go enjoy a beverage/dessert/guilty pleasure of your choice!

   But, keep an eye on the GitHub issues and the WP.org Support Forums (https://wordpress.org/support/plugin/edit-flow/) for any feedback and issues that may have been introduced with this new release.
