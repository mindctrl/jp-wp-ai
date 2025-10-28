/**
 * Content Translator - Front-end Script
 *
 * Handles language detection, translation requests, and content swapping.
 */

(function() {
	'use strict';

	// Wait for DOM to be ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	function init() {
		const translatorBlock = document.querySelector('.wp-block-jp-wp-ai-content-translator');
		
		if (!translatorBlock) {
			return;
		}

		const postId = translatorBlock.dataset.postId;
		const languageSelect = document.getElementById('content-translator-language-select');
		const translateBtn = document.getElementById('content-translator-translate-btn');
		const originalBtn = document.getElementById('content-translator-original-btn');
		const statusDiv = document.getElementById('content-translator-status');

		// Store original content.
		let originalTitle = document.title;
		let originalContent = null;
		let currentLanguage = null;

		// Detect browser language and pre-select if available.
		detectAndSelectBrowserLanguage(languageSelect);

		// Event listeners.
		translateBtn.addEventListener('click', handleTranslate);
		originalBtn.addEventListener('click', handleShowOriginal);

		/**
		 * Detects browser language and pre-selects it in the dropdown.
		 */
		function detectAndSelectBrowserLanguage(select) {
			const browserLang = navigator.language || navigator.languages[0];
			
			if (!browserLang) {
				return;
			}

			// Extract language code (e.g., 'en-US' -> 'en').
			const langCode = browserLang.split('-')[0].toLowerCase();

			// Check if this language is in our options.
			const option = select.querySelector(`option[value="${langCode}"]`);
			
			if (option) {
				select.value = langCode;
				// Add a visual indicator.
				option.textContent = option.textContent + ' ★';
			}
		}

		/**
		 * Handles the translate button click.
		 */
		function handleTranslate() {
			const targetLang = languageSelect.value;

			if (!targetLang) {
				showStatus('Please select a language.', 'error');
				return;
			}

			// Don't translate if already in this language.
			if (currentLanguage === targetLang) {
				showStatus('Content is already in this language.', 'info');
				return;
			}

			// Store original content if not already stored.
			if (!originalContent) {
				storeOriginalContent();
			}

			// Disable controls during translation.
			setControlsEnabled(false);
			showStatus('Translating...', 'loading');

			// Make AJAX request.
			const formData = new FormData();
			formData.append('action', 'ai_translate_content');
			formData.append('nonce', aiContentTranslator.nonce);
			formData.append('post_id', postId);
			formData.append('target_lang', targetLang);

			fetch(aiContentTranslator.ajaxUrl, {
				method: 'POST',
				body: formData,
			})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						applyTranslation(data.data.translation);
						currentLanguage = targetLang;
						
						const cacheMsg = data.data.cached ? ' (from cache)' : '';
						showStatus('Translation complete!' + cacheMsg, 'success');
						
						// Show the "Show Original" button.
						originalBtn.style.display = 'inline-block';
					} else {
						showStatus('Error: ' + (data.data.message || 'Translation failed'), 'error');
					}
				})
				.catch(error => {
					console.error('Translation error:', error);
					showStatus('Error: Failed to translate content', 'error');
				})
				.finally(() => {
					setControlsEnabled(true);
				});
		}

		/**
		 * Handles the show original button click.
		 */
		function handleShowOriginal() {
			if (!originalContent) {
				return;
			}

			restoreOriginalContent();
			currentLanguage = null;
			showStatus('Showing original content', 'info');
			originalBtn.style.display = 'none';
		}

		/**
		 * Stores the original page content before translation.
		 */
		function storeOriginalContent() {
			originalContent = {
				title: document.title,
				mainContent: getMainContent(),
			};
		}

		/**
		 * Gets the main content element(s) to translate.
		 */
		function getMainContent() {
			// Try to find the main content area.
			const selectors = [
				'.entry-content',
				'.post-content',
				'article .content',
				'main article',
				'article',
				'main',
			];

			for (const selector of selectors) {
				const element = document.querySelector(selector);
				if (element) {
					// Clone the element to preserve translator block
					const clone = element.cloneNode(true);
					
					// Remove the translator block from the clone
					const translatorInClone = clone.querySelector('.wp-block-jp-wp-ai-content-translator');
					if (translatorInClone) {
						translatorInClone.remove();
					}
					
					return {
						element: element,
						html: clone.innerHTML,
						translatorBlock: translatorBlock,
					};
				}
			}

			// Fallback: use body.
			const bodyClone = document.body.cloneNode(true);
			const translatorInClone = bodyClone.querySelector('.wp-block-jp-wp-ai-content-translator');
			if (translatorInClone) {
				translatorInClone.remove();
			}
			
			return {
				element: document.body,
				html: bodyClone.innerHTML,
				translatorBlock: translatorBlock,
			};
		}

		/**
		 * Applies the translation to the page.
		 */
		function applyTranslation(translation) {
			// Update page title.
			if (translation.title) {
				document.title = translation.title;
				
				// Also update h1 if it matches the original title.
				const h1 = document.querySelector('h1.entry-title, h1.post-title, article h1');
				if (h1 && h1.textContent.trim() === originalTitle.trim()) {
					h1.textContent = translation.title;
				}
			}

			// Update main content.
			if (translation.content && originalContent && originalContent.mainContent) {
				// Store reference to translator block
				const currentTranslatorBlock = originalContent.mainContent.element.querySelector('.wp-block-jp-wp-ai-content-translator');
				
				// Apply translated content
				originalContent.mainContent.element.innerHTML = translation.content;
				
				// Re-insert the translator block if it was in the content
				if (currentTranslatorBlock) {
					// Find where to insert it (try to put it back in the same position)
					const firstChild = originalContent.mainContent.element.firstChild;
					if (firstChild) {
						originalContent.mainContent.element.insertBefore(currentTranslatorBlock, firstChild);
					} else {
						originalContent.mainContent.element.appendChild(currentTranslatorBlock);
					}
				}
			}
		}

		/**
		 * Restores the original content.
		 */
		function restoreOriginalContent() {
			if (!originalContent) {
				return;
			}

			// Restore title.
			document.title = originalContent.title;

			// Restore h1.
			const h1 = document.querySelector('h1.entry-title, h1.post-title, article h1');
			if (h1) {
				// Extract title from original title (remove site name if present).
				const titleParts = originalContent.title.split('|');
				if (titleParts.length > 0) {
					h1.textContent = titleParts[0].trim();
				}
			}

			// Restore main content.
			if (originalContent.mainContent) {
				// Store reference to the LIVE translator block (with event listeners)
				const liveTranslatorBlock = originalContent.mainContent.element.querySelector('.wp-block-jp-wp-ai-content-translator');
				
				// Restore original HTML
				originalContent.mainContent.element.innerHTML = originalContent.mainContent.html;
				
				// Re-insert the LIVE translator block to preserve event listeners
				if (liveTranslatorBlock) {
					// Find where the translator block should go
					// If the original HTML had the translator block, replace it with the live one
					const restoredTranslatorBlock = originalContent.mainContent.element.querySelector('.wp-block-jp-wp-ai-content-translator');
					
					if (restoredTranslatorBlock) {
						// Replace the restored (dead) block with the live one
						restoredTranslatorBlock.parentNode.replaceChild(liveTranslatorBlock, restoredTranslatorBlock);
					} else {
						// If not found, insert at the beginning
						const firstChild = originalContent.mainContent.element.firstChild;
						if (firstChild) {
							originalContent.mainContent.element.insertBefore(liveTranslatorBlock, firstChild);
						} else {
							originalContent.mainContent.element.appendChild(liveTranslatorBlock);
						}
					}
				}
			}
		}

		/**
		 * Shows a status message.
		 */
		function showStatus(message, type) {
			statusDiv.textContent = message;
			statusDiv.className = 'content-translator-status content-translator-status-' + type;
			
			// Auto-hide success/info messages after 5 seconds.
			if (type === 'success' || type === 'info') {
				setTimeout(() => {
					statusDiv.textContent = '';
					statusDiv.className = 'content-translator-status';
				}, 5000);
			}
		}

		/**
		 * Enables or disables the translation controls.
		 */
		function setControlsEnabled(enabled) {
			languageSelect.disabled = !enabled;
			translateBtn.disabled = !enabled;
			
			if (enabled) {
				translateBtn.textContent = translateBtn.dataset.originalText || 'Translate';
			} else {
				if (!translateBtn.dataset.originalText) {
					translateBtn.dataset.originalText = translateBtn.textContent;
				}
				translateBtn.textContent = 'Translating...';
			}
		}
	}
})();

