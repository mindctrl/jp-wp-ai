/**
 * Admin Settings JavaScript
 *
 * Handles test connection functionality on the JP WP AI settings page.
 */

(function ($) {
	'use strict';

	$(document).ready(function () {
		const $testButton = $('#jp-wp-ai-test-connection');
		const $statusDiv = $('#jp-wp-ai-connection-status');
		const $apiKeyInput = $('#jp_wp_ai_openai_api_key');

		// Enable/disable test button based on API key input.
		$apiKeyInput.on('input', function () {
			$testButton.prop('disabled', $(this).val().trim() === '');
		});

		// Handle test connection button click.
		$testButton.on('click', function (e) {
			e.preventDefault();

			const $button = $(this);
			const originalText = $button.text();

			// Update button state.
			$button.prop('disabled', true).text('Testing...');
			$statusDiv.html('');

			// Make AJAX request.
			$.ajax({
				url: jpWpAiSettings.ajaxUrl,
				type: 'POST',
				data: {
					action: 'jp_wp_ai_test_openai_connection',
					nonce: jpWpAiSettings.nonce,
				},
				success: function (response) {
					if (response.success) {
						$statusDiv.html(
							'<div class="notice notice-success inline"><p>' +
								response.data.message +
								'</p></div>'
						);
					} else {
						$statusDiv.html(
							'<div class="notice notice-error inline"><p>' +
								response.data.message +
								'</p></div>'
						);
					}
				},
				error: function (xhr) {
					let message = 'An unexpected error occurred.';
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						message = xhr.responseJSON.data.message;
					}
					$statusDiv.html(
						'<div class="notice notice-error inline"><p>' +
							message +
							'</p></div>'
					);
				},
				complete: function () {
					$button.prop('disabled', false).text(originalText);
				},
			});
		});
	});
})(jQuery);

