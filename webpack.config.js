/**
 * WordPress Dependencies
 */
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
	...defaultConfig,
	entry: {
		'alt-text-generator': './src/alt-text-generator/index.js',
		'content-summarizer': './src/content-summarizer/index.js',
		'content-translator/index': './src/content-translator/index.js',
		'content-translator/view': './src/content-translator/view.js',
	},
	output: {
		...defaultConfig.output,
		filename: '[name].js',
		path: path.resolve(process.cwd(), 'build'),
	},
};

