/**
 * Alt Text Generator - Block Editor Integration
 *
 * Adds "Generate Alt Text" button to Image block toolbar.
 */

import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { BlockControls } from '@wordpress/block-editor';
import { ToolbarGroup, ToolbarButton } from '@wordpress/components';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

// Import media library script for classic media modal.
import './media-library';

/**
 * Add Generate Alt Text button to Image block toolbar.
 */
const withAltTextGenerator = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const { name, attributes, setAttributes, isSelected } = props;

		// Only apply to image blocks.
		if (name !== 'core/image') {
			return <BlockEdit {...props} />;
		}

		const [isGenerating, setIsGenerating] = useState(false);
		const [error, setError] = useState(null);

	const handleGenerateAltText = async () => {
		const { id, url } = attributes;

		if (!url) {
			setError(__('No image selected', 'ai'));
			return;
		}

		setIsGenerating(true);
		setError(null);

		try {
			const response = await apiFetch({
				path: '/wp-abilities/v1/abilities/ai/generate-alt-text/run',
				method: 'POST',
				data: {
					input: {
						image_url: url,
						attachment_id: id || 0,
					},
				},
			});

			if (response && response.alt_text) {
				setAttributes({ alt: response.alt_text });
			} else {
				setError(__('Failed to generate alt text', 'ai'));
			}
		} catch (err) {
			console.error('Alt text generation error:', err);
			setError(err.message || __('An error occurred', 'ai'));
		} finally {
			setIsGenerating(false);
		}
	};

		return (
			<>
				<BlockEdit {...props} />
				{isSelected && attributes.url && (
					<BlockControls group="other">
						<ToolbarGroup>
							<ToolbarButton
								icon="admin-customizer"
								label={__('Generate Alt Text', 'ai')}
								onClick={handleGenerateAltText}
								disabled={isGenerating}
							>
								{isGenerating
									? __('Generating...', 'ai')
									: __('Generate Alt Text', 'ai')}
							</ToolbarButton>
						</ToolbarGroup>
					</BlockControls>
				)}
				{error && isSelected && (
					<div style={{ padding: '10px', color: 'red', fontSize: '12px' }}>
						{error}
					</div>
				)}
			</>
		);
	};
}, 'withAltTextGenerator');

addFilter(
	'editor.BlockEdit',
	'ai/alt-text-generator',
	withAltTextGenerator
);

