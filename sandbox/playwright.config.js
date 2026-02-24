// @ts-check
const { defineConfig, devices } = require("@playwright/test");

module.exports = defineConfig({
	testDir: "./tests",
	timeout: 60_000,
	expect: { timeout: 15_000 },
	fullyParallel: false,
	retries: 0,
	workers: 1,
	reporter: [["list"], ["html", { open: "never" }]],

	use: {
		baseURL: "http://localhost:8080",
		trace: "retain-on-failure",
		screenshot: "only-on-failure",
	},

	projects: [
		{
			name: "setup",
			testMatch: /setup\.spec\.js/,
			use: { ...devices["Desktop Chrome"] },
		},
		{
			name: "e2e",
			testMatch: /negotiation\.spec\.js/,
			use: { ...devices["Desktop Chrome"] },
			dependencies: ["setup"],
		},
	],
});
