<?php
/**
 * OpenAI Client Service
 *
 * Handles communication with OpenAI API for vision and text completion.
 *
 * @package JP\WP_AI\Services
 */

namespace JP\WP_AI\Services;

/**
 * OpenAI API client for WordPress.
 *
 * @since 1.0.0
 */
class OpenAI_Client {
	/**
	 * OpenAI API base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const API_BASE_URL = 'https://api.openai.com/v1';

	/**
	 * Option name for storing API key.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const API_KEY_OPTION = 'jp_wp_ai_openai_api_key';

	/**
	 * Gets the stored API key.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null API key or null if not set.
	 */
	public static function get_api_key(): ?string {
		$api_key = get_option( self::API_KEY_OPTION, '' );
		return ! empty( $api_key ) ? $api_key : null;
	}

	/**
	 * Sets the API key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_key The OpenAI API key.
	 * @return bool True on success, false on failure.
	 */
	public static function set_api_key( string $api_key ): bool {
		return update_option( self::API_KEY_OPTION, sanitize_text_field( $api_key ) );
	}

	/**
	 * Checks if API key is configured.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if API key is set, false otherwise.
	 */
	public static function has_api_key(): bool {
		return ! empty( self::get_api_key() );
	}

	/**
	 * Tests the API connection.
	 *
	 * @since 1.0.0
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function test_connection() {
		if ( ! self::has_api_key() ) {
			return new \WP_Error(
				'no_api_key',
				__( 'No API key configured.', 'jp-wp-ai' )
			);
		}

		$response = self::make_request(
			'/models',
			array(),
			'GET'
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Generates alt text for an image using GPT-4 Vision.
	 *
	 * @since 1.0.0
	 *
	 * @param string $image_url The URL of the image to analyze.
	 * @param string $context   Optional context about the image.
	 * @return array|\WP_Error Array with 'alt_text' and 'confidence' on success, WP_Error on failure.
	 */
	public static function generate_alt_text( string $image_url, string $context = '' ) {
		if ( ! self::has_api_key() ) {
			return new \WP_Error(
				'no_api_key',
				__( 'OpenAI API key is not configured. Please configure it in Settings → JP WP AI.', 'jp-wp-ai' )
			);
		}

		// Convert local URLs to base64 data URLs for OpenAI API.
		$processed_url = self::process_image_url( $image_url );
		if ( is_wp_error( $processed_url ) ) {
			return $processed_url;
		}

		$prompt  = 'Generate a concise, descriptive alt text for this image suitable for screen readers. ';
		$prompt .= 'Focus on the main subject and important details. Keep it under 125 characters. ';
		$prompt .= 'Do not include phrases like "image of" or "picture of". ';

		if ( ! empty( $context ) ) {
			$prompt .= 'Context: ' . $context . ' ';
		}

		$prompt .= 'Return only the alt text, nothing else.';

		$body = array(
			'model'      => 'gpt-4o-mini',
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => array(
						array(
							'type' => 'text',
							'text' => $prompt,
						),
						array(
							'type'      => 'image_url',
							'image_url' => array(
								'url' => $processed_url,
							),
						),
					),
				),
			),
			'max_tokens' => 100,
		);

		$response = self::make_request( '/chat/completions', $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$alt_text = $response['choices'][0]['message']['content'] ?? '';
		$alt_text = trim( $alt_text );

		// Remove common prefixes that might slip through.
		$alt_text = preg_replace( '/^(image of|picture of|photo of)\s+/i', '', $alt_text );

		return array(
			'alt_text'   => $alt_text,
			'confidence' => 'high', // GPT-4o is generally reliable.
		);
	}

	/**
	 * Processes an image URL for OpenAI API.
	 *
	 * Converts local/localhost URLs to base64 data URLs since OpenAI can't access them.
	 * Public URLs are returned as-is.
	 *
	 * @since 1.0.0
	 *
	 * @param string $image_url The image URL to process.
	 * @return string|\WP_Error Processed URL or WP_Error on failure.
	 */
	private static function process_image_url( string $image_url ) {
		// Check if this is a local URL (localhost, 127.0.0.1, or local network).
		$parsed_url = wp_parse_url( $image_url );
		$host       = $parsed_url['host'] ?? '';

		$is_local = (
			strpos( $host, 'localhost' ) !== false ||
			strpos( $host, '127.0.0.1' ) !== false ||
			strpos( $host, '192.168.' ) === 0 ||
			strpos( $host, '10.' ) === 0 ||
			strpos( $host, '172.' ) === 0
		);

		// If it's a public URL, return as-is.
		if ( ! $is_local ) {
			return $image_url;
		}

		// For local URLs, convert to base64 data URL.
		// Try to get the file path from the URL.
		$upload_dir = wp_upload_dir();
		$base_url   = $upload_dir['baseurl'];
		$base_dir   = $upload_dir['basedir'];

		// Check if this is an uploads URL.
		if ( strpos( $image_url, $base_url ) === 0 ) {
			$file_path = str_replace( $base_url, $base_dir, $image_url );
		} else {
			// Try to convert URL to file path.
			$file_path = str_replace( home_url(), ABSPATH, $image_url );
		}

		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error(
				'file_not_found',
				sprintf(
					/* translators: %s: File path */
					__( 'Image file not found: %s', 'jp-wp-ai' ),
					$file_path
				)
			);
		}

		// Read the file and convert to base64.
		$image_data = file_get_contents( $file_path );
		if ( false === $image_data ) {
			return new \WP_Error(
				'file_read_error',
				__( 'Failed to read image file.', 'jp-wp-ai' )
			);
		}

		// Get MIME type.
		$mime_type = wp_check_filetype( $file_path )['type'] ?: 'image/jpeg';

		// Create data URL.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Intentional and required by OpenAI API.
		$base64 = base64_encode( $image_data );
		return 'data:' . $mime_type . ';base64,' . $base64;
	}

	/**
	 * Generates a summary of content using GPT-4.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content    The content to summarize.
	 * @param int    $max_length Maximum length of summary in words (default 50).
	 * @return array|\WP_Error Array with 'summary' and 'word_count' on success, WP_Error on failure.
	 */
	public static function summarize_content( string $content, int $max_length = 50 ) {
		if ( ! self::has_api_key() ) {
			return new \WP_Error(
				'no_api_key',
				__( 'OpenAI API key is not configured. Please configure it in Settings → JP WP AI.', 'jp-wp-ai' )
			);
		}

		// Strip HTML tags and normalize whitespace.
		$clean_content = wp_strip_all_tags( $content );
		$clean_content = preg_replace( '/\s+/', ' ', $clean_content );
		$clean_content = trim( $clean_content );

		if ( empty( $clean_content ) ) {
			return new \WP_Error(
				'empty_content',
				__( 'No content to summarize.', 'jp-wp-ai' )
			);
		}

		$prompt = sprintf(
			'Summarize the following content in approximately %d words. Create a clear, concise summary that captures the main points. Return only the summary, nothing else.\n\nContent:\n%s',
			$max_length,
			$clean_content
		);

		$body = array(
			'model'       => 'gpt-4.1-nano',
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			'max_tokens'  => $max_length * 2, // Rough estimate: 1 word ≈ 1.3 tokens.
			'temperature' => 0.7,
		);

		$response = self::make_request( '/chat/completions', $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$summary = $response['choices'][0]['message']['content'] ?? '';
		$summary = trim( $summary );

		$word_count = str_word_count( $summary );

		return array(
			'summary'    => $summary,
			'word_count' => $word_count,
		);
	}

	/**
	 * Translates content into a target language using GPT-4.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $content     Array with 'title', 'content', and 'excerpt' keys.
	 * @param string $target_lang Target language code (ISO 639-1).
	 * @param string $source_lang Source language code (default 'auto').
	 * @return array|\WP_Error Array with translated content on success, WP_Error on failure.
	 */
	public static function translate_content( array $content, string $target_lang, string $source_lang = 'auto' ) {
		if ( ! self::has_api_key() ) {
			return new \WP_Error(
				'no_api_key',
				__( 'OpenAI API key is not configured. Please configure it in Settings → JP WP AI.', 'jp-wp-ai' )
			);
		}

		// Language name mapping for better prompts.
		$language_names = array(
			'es' => 'Spanish',
			'fr' => 'French',
			'de' => 'German',
			'ja' => 'Japanese',
			'zh' => 'Chinese',
			'pt' => 'Portuguese',
			'it' => 'Italian',
			'ru' => 'Russian',
			'ar' => 'Arabic',
			'hi' => 'Hindi',
		);

		$target_language_name = $language_names[ $target_lang ] ?? $target_lang;

		// Build the translation prompt.
		$source_info = 'auto' === $source_lang ? '' : ' from ' . ( $language_names[ $source_lang ] ?? $source_lang );
		$prompt      = sprintf(
			"Translate the following content%s to %s. Maintain all HTML formatting, links, and structure. Provide natural, contextually appropriate translations.\n\n",
			$source_info,
			$target_language_name
		);

		$prompt .= "Return the translation as a JSON object with these keys: title, content, excerpt\n\n";

		if ( ! empty( $content['title'] ) ) {
			$prompt .= "Title:\n" . $content['title'] . "\n\n";
		}

		if ( ! empty( $content['content'] ) ) {
			$prompt .= "Content:\n" . $content['content'] . "\n\n";
		}

		if ( ! empty( $content['excerpt'] ) ) {
			$prompt .= "Excerpt:\n" . $content['excerpt'] . "\n\n";
		}

		$body = array(
			'model'       => 'gpt-4.1-nano',
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			'temperature' => 0.3,
			'response_format' => array(
				'type' => 'json_object',
			),
		);

		$response = self::make_request( '/chat/completions', $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$translation_json = $response['choices'][0]['message']['content'] ?? '';
		$translation      = json_decode( $translation_json, true );

		if ( null === $translation ) {
			return new \WP_Error(
				'invalid_translation',
				__( 'Failed to parse translation response.', 'jp-wp-ai' )
			);
		}

		return array(
			'title'   => $translation['title'] ?? '',
			'content' => $translation['content'] ?? '',
			'excerpt' => $translation['excerpt'] ?? '',
		);
	}

	/**
	 * Makes a request to the OpenAI API.
	 *
	 * @since 1.0.0
	 *
	 * @param string $endpoint The API endpoint (e.g., '/chat/completions').
	 * @param array  $body     The request body.
	 * @param string $method   HTTP method (default 'POST').
	 * @return array|\WP_Error Response data on success, WP_Error on failure.
	 */
	private static function make_request( string $endpoint, array $body = array(), string $method = 'POST' ) {
		$api_key = self::get_api_key();

		if ( empty( $api_key ) ) {
			return new \WP_Error(
				'no_api_key',
				__( 'OpenAI API key is not configured.', 'jp-wp-ai' )
			);
		}

		$url = self::API_BASE_URL . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		if ( 'POST' === $method && ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'api_request_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'API request failed: %s', 'jp-wp-ai' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_message = $data['error']['message'] ?? __( 'Unknown API error', 'jp-wp-ai' );

			return new \WP_Error(
				'api_error',
				sprintf(
					/* translators: 1: HTTP status code, 2: Error message */
					__( 'OpenAI API error (%1$d): %2$s', 'jp-wp-ai' ),
					$status_code,
					$error_message
				)
			);
		}

		if ( null === $data ) {
			return new \WP_Error(
				'invalid_response',
				__( 'Invalid JSON response from OpenAI API.', 'jp-wp-ai' )
			);
		}

		return $data;
	}
}
