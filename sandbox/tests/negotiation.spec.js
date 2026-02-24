// @ts-check
const { test, expect } = require("@playwright/test");

const WP_URL = "http://localhost:8080";
const WP_ADMIN = "admin";
const WP_PASSWORD = "admin123";

// Helper to get the first published post by slug or default
async function getPostUrl(request, slug = "hello-markdown-world") {
	// Try by slug first
	const res = await request.get(
		`${WP_URL}/wp-json/wp/v2/posts?slug=${slug}&status=publish`,
	);
	const posts = await res.json();
	if (posts.length > 0) return { id: posts[0].id, link: posts[0].link };

	// Fallback to first post
	const fallback = await request.get(
		`${WP_URL}/wp-json/wp/v2/posts?status=publish&per_page=1`,
	);
	const all = await fallback.json();
	return { id: all[0]?.id, link: all[0]?.link };
}

test.describe("HTTP Content Negotiation", () => {
	test("GET single post: default Accept returns HTML", async ({
		request,
	}) => {
		const { link } = await getPostUrl(request);
		const res = await request.get(link, {
			headers: { Accept: "text/html,application/xhtml+xml" },
		});
		expect(res.status()).toBe(200);
		expect(res.headers()["content-type"]).toContain("text/html");
		const body = await res.text();
		expect(body).toContain("<!DOCTYPE html>");
		console.log("✅ Default response is HTML");
	});

	test("GET single post with Accept: text/markdown returns Markdown", async ({
		request,
	}) => {
		const { link } = await getPostUrl(request);
		const res = await request.get(link, {
			headers: { Accept: "text/markdown" },
		});
		expect(res.status()).toBe(200);
		expect(res.headers()["content-type"]).toContain("text/markdown");
		const body = await res.text();
		expect(body).not.toMatch(/<html/i);
		expect(body).toMatch(/^#\s/m); // At least one heading
		console.log("✅ Accept: text/markdown returns Markdown");
		console.log("--- Response preview ---");
		console.log(body.slice(0, 500));
	});

	test("GET single post with Accept: text/x-markdown returns Markdown", async ({
		request,
	}) => {
		const { link } = await getPostUrl(request);
		const res = await request.get(link, {
			headers: { Accept: "text/x-markdown" },
		});
		expect(res.status()).toBe(200);
		expect(res.headers()["content-type"]).toContain("markdown");
		console.log("✅ Accept: text/x-markdown returns Markdown");
	});

	test("Markdown response includes Vary: Accept header", async ({
		request,
	}) => {
		const { link } = await getPostUrl(request);
		const res = await request.get(link, {
			headers: { Accept: "text/markdown" },
		});
		expect(res.headers()["vary"]).toContain("Accept");
		console.log("✅ Vary: Accept header present");
	});

	test("Markdown response includes X-Markdown-Source header", async ({
		request,
	}) => {
		const { link } = await getPostUrl(request);
		const res = await request.get(link, {
			headers: { Accept: "text/markdown" },
		});
		expect(res.headers()["x-markdown-source"]).toBe("wordpress-plugin");
		console.log("✅ X-Markdown-Source: wordpress-plugin present");
	});

	test("Markdown response includes X-Markdown-Plugin-Version header", async ({
		request,
	}) => {
		const { link } = await getPostUrl(request);
		const res = await request.get(link, {
			headers: { Accept: "text/markdown" },
		});
		expect(res.headers()["x-markdown-plugin-version"]).toBeTruthy();
		console.log("✅ X-Markdown-Plugin-Version header present");
	});

	test("Markdown response includes Link header for Markdown discovery", async ({
		request,
	}) => {
		const { link } = await getPostUrl(request);
		const res = await request.get(link, {
			headers: { Accept: "text/html" },
		});
		const linkHeader = res.headers()["link"] ?? "";
		expect(linkHeader).toContain("text/markdown");
		console.log("✅ Link header present for Markdown discovery");
	});

	test('HTML response includes <link rel="alternate" type="text/markdown">', async ({
		request,
	}) => {
		const { link } = await getPostUrl(request);
		const res = await request.get(link, {
			headers: { Accept: "text/html" },
		});
		const body = await res.text();
		expect(body).toContain('rel="alternate"');
		expect(body).toContain('type="text/markdown"');
		console.log("✅ HTML includes alternate link for Markdown discovery");
	});

	test("Vary: Accept present on HTML responses too", async ({ request }) => {
		const { link } = await getPostUrl(request);
		const res = await request.get(link, {
			headers: { Accept: "text/html" },
		});
		expect(res.headers()["vary"]).toContain("Accept");
		console.log("✅ Vary: Accept also on HTML responses (CDN-safe)");
	});

	test("Single post ?format=markdown returns Markdown", async ({
		request,
	}) => {
		const { link } = await getPostUrl(request);
		const res = await request.get(`${link}?format=markdown`);
		expect(res.status()).toBe(200);
		const body = await res.text();
		expect(body).not.toMatch(/<html/i);
		console.log("✅ ?format=markdown query parameter works");
	});

	test("Single post .md URL extension returns Markdown", async ({
		request,
	}) => {
		const { link } = await getPostUrl(request);
		// Strip trailing slash, append .md
		const mdUrl = link.replace(/\/$/, "") + ".md";
		const res = await request.get(mdUrl);
		expect(res.status()).toBe(200);
		expect(res.headers()["content-type"]).toContain("text/markdown");
		const body = await res.text();
		expect(body).not.toMatch(/<html/i);
		console.log("✅ .md URL extension works");
	});

	test("REST API /jetstaa-mna/v1/markdown/:id returns markdown field", async ({
		request,
	}) => {
		const { id } = await getPostUrl(request);
		const res = await request.get(
			`${WP_URL}/wp-json/jetstaa-mna/v1/markdown/${id}`,
		);
		expect([200, 401]).toContain(res.status()); // may require auth
		if (res.status() === 200) {
			const body = await res.json();
			expect(body).toHaveProperty("markdown");
			console.log("✅ REST API /jetstaa-mna/v1/markdown/:id works");
		} else {
			console.log(
				"ℹ️ REST API endpoint requires authentication (expected)",
			);
		}
	});

	test("REST API status endpoint works", async ({ request }) => {
		const res = await request.get(
			`${WP_URL}/wp-json/jetstaa-mna/v1/status`,
		);
		expect([200, 401]).toContain(res.status());
		if (res.status() === 200) {
			const body = await res.json();
			expect(body).toHaveProperty("enabled");
			console.log(
				"✅ REST API /jetstaa-mna/v1/status works:",
				JSON.stringify(body),
			);
		} else {
			console.log("ℹ️ Status endpoint requires auth");
		}
	});

	test("WP REST API posts include markdown field", async ({ request }) => {
		const { id } = await getPostUrl(request);
		const res = await request.get(`${WP_URL}/wp-json/wp/v2/posts/${id}`);
		const body = await res.json();
		expect(body).toHaveProperty("markdown");
		console.log(
			"✅ REST API posts have markdown field, type:",
			typeof body.markdown,
		);
	});

	test("Oversized Accept header returns 400", async ({ request }) => {
		const { link } = await getPostUrl(request);
		const res = await request.get(link, {
			headers: { Accept: "text/markdown," + "a".repeat(1100) },
		});
		expect(res.status()).toBe(400);
		console.log("✅ Oversized Accept header (>1024 bytes) returns 400");
	});

	test("Hello Markdown World post converts correctly", async ({
		request,
	}) => {
		const { link, id } = await getPostUrl(request, "hello-markdown-world");
		if (!id) {
			console.log("ℹ️ hello-markdown-world post not found, skipping");
			return;
		}
		const res = await request.get(link, {
			headers: { Accept: "text/markdown" },
		});
		expect(res.status()).toBe(200);
		const body = await res.text();
		expect(body).toContain("Hello Markdown World");
		expect(body).toContain("## Introduction");
		expect(body).toContain("**test post**");
		expect(body).toContain("Item one");
		console.log("✅ HTML→Markdown conversion is correct");
		console.log("--- Markdown output ---");
		console.log(body);
	});
});
