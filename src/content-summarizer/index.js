/**
 * Content Summarizer - Block Editor Integration
 *
 * Adds sidebar panel for content summarization.
 */

import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { PanelBody, Button, TextControl, Notice } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { serialize } from '@wordpress/blocks';
import apiFetch from '@wordpress/api-fetch';

const ContentSummarizerPanel = () => {
	const [maxLength, setMaxLength] = useState(50);
	const [summary, setSummary] = useState('');
	const [isGenerating, setIsGenerating] = useState(false);
	const [error, setError] = useState(null);

	// Get current post content.
	const blocks = useSelect((select) => {
		return select('core/block-editor').getBlocks();
	}, []);

	const { editPost } = useDispatch('core/editor');
	const { insertBlocks } = useDispatch('core/block-editor');
	const { createBlock } = wp.blocks;

	const handleGenerateSummary = async () => {
		// Serialize blocks to HTML content.
		const content = serialize(blocks);

		if (!content || content.trim() === '') {
			setError(__('Please add some content first.', 'ai'));
			return;
		}

		setIsGenerating(true);
		setError(null);
		setSummary('');

		try {
			const response = await apiFetch({
				path: '/wp-abilities/v1/abilities/ai/summarize-content/run',
				method: 'POST',
				data: {
					input: {
						content: content,
						max_length: parseInt(maxLength, 10),
					},
				},
			});

			if (response && response.summary) {
				setSummary(response.summary);
			} else {
				setError(__('Failed to generate summary', 'ai'));
			}
		} catch (err) {
			console.error('Summary generation error:', err);
			setError(err.message || __('An error occurred', 'ai'));
		} finally {
			setIsGenerating(false);
		}
	};

	const handleCopyToClipboard = () => {
		navigator.clipboard.writeText(summary).then(() => {
			// Could add a temporary success message here.
			alert(__('Summary copied to clipboard!', 'ai'));
		});
	};

	const handleSetAsExcerpt = () => {
		editPost({ excerpt: summary });
		alert(__('Summary set as post excerpt!', 'ai'));
	};

	const handleInsertAtTop = () => {
		const paragraphBlock = createBlock('core/paragraph', {
			content: summary,
		});
		insertBlocks(paragraphBlock, 0);
		alert(__('Summary inserted at the top!', 'ai'));
	};

	return (
		<PanelBody title={__('Content Summarizer', 'ai')} initialOpen={true}>
			<p>
				{__(
					'Generate a concise summary of your post content using AI.',
					'ai'
				)}
			</p>

			<TextControl
				label={__('Summary Length (words)', 'ai')}
				type="number"
				min={10}
				max={200}
				value={maxLength}
				onChange={(value) => setMaxLength(value)}
				help={__('Approximate number of words in the summary.', 'ai')}
			/>

			<Button
				variant="primary"
				onClick={handleGenerateSummary}
				disabled={isGenerating}
				isBusy={isGenerating}
			>
				{isGenerating
					? __('Generating...', 'ai')
					: __('Generate Summary', 'ai')}
			</Button>

			{error && (
				<Notice status="error" isDismissible={false} style={{ marginTop: '10px' }}>
					{error}
				</Notice>
			)}

			{summary && (
				<div style={{ marginTop: '15px' }}>
					<h4>{__('Generated Summary:', 'ai')}</h4>
					<div
						style={{
							padding: '10px',
							background: '#f0f0f1',
							borderRadius: '4px',
							marginBottom: '10px',
						}}
					>
						{summary}
					</div>

					<div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
						<Button variant="secondary" onClick={handleCopyToClipboard}>
							{__('Copy', 'ai')}
						</Button>
						<Button variant="secondary" onClick={handleSetAsExcerpt}>
							{__('Set as Excerpt', 'ai')}
						</Button>
						<Button variant="secondary" onClick={handleInsertAtTop}>
							{__('Insert at Top', 'ai')}
						</Button>
					</div>
				</div>
			)}
		</PanelBody>
	);
};

const ContentSummarizerSidebar = () => {
	return (
		<>
			<PluginSidebarMoreMenuItem target="ai-content-summarizer" icon="admin-customizer">
				{__('AI Summarizer', 'ai')}
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name="ai-content-summarizer"
				title={__('AI Content Summarizer', 'ai')}
				icon="admin-customizer"
			>
				<ContentSummarizerPanel />
			</PluginSidebar>
		</>
	);
};

registerPlugin('ai-content-summarizer', {
	render: ContentSummarizerSidebar,
});

