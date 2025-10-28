/**
 * Content Translator Block - Editor Component
 *
 * Shows a preview of the language selector in the block editor.
 */

import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';

import metadata from './block.json';

/**
 * Edit component for the Content Translator block.
 */
const Edit = () => {
	const blockProps = useBlockProps({
		className: 'wp-block-jp-wp-ai-content-translator',
	});

	return (
		<div {...blockProps}>
			<div className="content-translator-controls" style={{ 
				padding: '20px', 
				border: '1px solid #ddd', 
				borderRadius: '4px',
				backgroundColor: '#f9f9f9'
			}}>
				<p style={{ margin: '0 0 10px 0', fontWeight: 'bold' }}>
					{__('Content Translator Block (Preview)', 'jp-wp-ai')}
				</p>
				<div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
					<label htmlFor="translator-preview-select">
						{__('Translate this page:', 'jp-wp-ai')}
					</label>
					<select id="translator-preview-select" disabled style={{ padding: '5px' }}>
						<option>{__('Select a language', 'jp-wp-ai')}</option>
						<option>Spanish</option>
						<option>French</option>
						<option>German</option>
						<option>Japanese</option>
					</select>
					<button type="button" disabled style={{ padding: '5px 15px' }}>
						{__('Translate', 'jp-wp-ai')}
					</button>
				</div>
			</div>
		</div>
	);
};

/**
 * Register the Content Translator block.
 */
registerBlockType(metadata.name, {
	edit: Edit,
	save: () => null, // Dynamic block, rendered by PHP
});

