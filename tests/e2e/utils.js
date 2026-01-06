/**
 * WordPress dependencies
 */
const { test: base, expect } = require('@wordpress/e2e-test-utils-playwright');

/**
 * Export base test and expect from WordPress Playwright utils
 * This can be extended with Edit Flow-specific fixtures if needed
 */
const test = base;

module.exports = { test, expect };
