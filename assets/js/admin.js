/**
 * Markdown Negotiation for Agents - Admin JavaScript.
 *
 * @package IlloDev\MarkdownNegotiation
 */

(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    // Toggle rate limit fields based on rate limit checkbox.
    const rateLimitCheckbox = document.querySelector(
      'input[name="jetstaa_mna_settings[rate_limit_enabled]"]',
    );

    if (rateLimitCheckbox) {
      const rateLimitFields = [
        document.querySelector(
          'input[name="jetstaa_mna_settings[rate_limit_requests]"]',
        ),
        document.querySelector(
          'input[name="jetstaa_mna_settings[rate_limit_window]"]',
        ),
      ];

      const toggleRateLimitFields = function () {
        rateLimitFields.forEach(function (field) {
          if (field) {
            field.closest("tr").style.opacity = rateLimitCheckbox.checked
              ? "1"
              : "0.5";
            field.disabled = !rateLimitCheckbox.checked;
          }
        });
      };

      rateLimitCheckbox.addEventListener("change", toggleRateLimitFields);
      toggleRateLimitFields();
    }

    // Toggle cache fields based on cache checkbox.
    const cacheCheckbox = document.querySelector(
      'input[name="jetstaa_mna_settings[cache_enabled]"]',
    );

    if (cacheCheckbox) {
      const cacheFields = [
        document.querySelector(
          'select[name="jetstaa_mna_settings[cache_driver]"]',
        ),
        document.querySelector('input[name="jetstaa_mna_settings[cache_ttl]"]'),
      ];

      const toggleCacheFields = function () {
        cacheFields.forEach(function (field) {
          if (field) {
            field.closest("tr").style.opacity = cacheCheckbox.checked
              ? "1"
              : "0.5";
            field.disabled = !cacheCheckbox.checked;
          }
        });
      };

      cacheCheckbox.addEventListener("change", toggleCacheFields);
      toggleCacheFields();
    }

    // Confirm cache flush.
    const flushForm = document.querySelector(
      'form[action*="jetstaa_mna_flush_cache"]',
    );
    if (flushForm) {
      flushForm.addEventListener("submit", function (e) {
        if (
          !window.confirm("Are you sure you want to flush the Markdown cache?")
        ) {
          e.preventDefault();
        }
      });
    }
  });
})();
