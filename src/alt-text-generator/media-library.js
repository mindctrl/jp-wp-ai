/**
 * Alt Text Generator - Media Library Integration
 *
 * Adds "Generate Alt Text" functionality to the Media Library.
 */

(function ($) {
	'use strict';

	$( document ).ready(
		function () {
			// Handle generate button click in media library.
			$( document ).on(
				'click',
				'.ai-generate-alt-text',
				function (e) {
					e.preventDefault();

					const $button      = $( this );
					const $status      = $button.siblings( '.ai-alt-text-status' );
					const attachmentId = $button.data( 'attachment-id' );
					const $altField    = $button.closest( 'tr' ).find( 'input[id^="attachments-"][id$="-image_alt"]' );

					if ( ! attachmentId) {
						$status.html( '<span style="color: red;">Invalid attachment ID.</span>' );
						return;
					}

					// Update button state.
					$button.prop( 'disabled', true ).text( 'Generating...' );
					$status.html( '<span style="color: #666;">Processing...</span>' );

					// Make AJAX request.
					$.ajax(
						{
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'ai_generate_alt_text',
								nonce: window.aiAltTextGenerator?.nonce || '',
								attachment_id: attachmentId,
							},
							success: function (response) {
								if (response.success && response.data.alt_text) {
									// Update the alt text field.
									$altField.val( response.data.alt_text );
									$status.html( '<span style="color: green;">✓ Generated!</span>' );

									// Trigger change event to mark field as modified.
									$altField.trigger( 'change' );
								} else {
									const message = response.data?.message || 'Failed to generate alt text.';
									$status.html( '<span style="color: red;">' + message + '</span>' );
								}
							},
							error: function (xhr) {
								let message = 'An unexpected error occurred.';
								if (xhr.responseJSON?.data?.message) {
									message = xhr.responseJSON.data.message;
								}
								$status.html( '<span style="color: red;">' + message + '</span>' );
							},
							complete: function () {
								$button.prop( 'disabled', false ).text( 'Generate Alt Text' );
							},
						}
					);
				}
			);
		}
	);
})( jQuery );
