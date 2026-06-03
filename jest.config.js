module.exports = {
    testMatch: ["<rootDir>/tests/jest/**/**.test.js"], // finds test
    moduleNameMapper: {
        "^.+\\.(css|less|scss)$": "babel-jest"
    },
    globals: {
        "EF_CALENDAR": {
            "WP_VERSION": 5.4
        }
    },
    preset: '@wordpress/jest-preset-default',
    // uuid (pulled in by @wordpress/components) ships as ESM only, so it must be
    // transpiled rather than left in the default node_modules ignore list.
    transformIgnorePatterns: [
        "node_modules/(?!(?:uuid)/)",
    ],
};