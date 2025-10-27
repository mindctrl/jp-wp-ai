<?php
/**
 * PHPStan stubs for WordPress AI API
 *
 * This file provides type definitions for the WordPress AI API
 * that this plugin depends on. These stubs allow PHPStan to
 * understand the structure without requiring the actual implementation.
 *
 * @package JP\WP_AI
 */

namespace WordPress\AI\Abstracts {
	/**
	 * Abstract base class for AI features.
	 */
	abstract class Abstract_Feature {
		/**
		 * Loads feature metadata.
		 *
		 * @return array{id: string, label: string, description: string}
		 */
		abstract protected function load_feature_metadata(): array;

		/**
		 * Gets the feature ID.
		 *
		 * @return string
		 */
		public function get_id(): string {
			return '';
		}

		/**
		 * Gets the feature label.
		 *
		 * @return string
		 */
		public function get_label(): string {
			return '';
		}

		/**
		 * Gets the feature description.
		 *
		 * @return string
		 */
		public function get_description(): string {
			return '';
		}

		/**
		 * Checks if the feature is enabled.
		 *
		 * @return bool
		 */
		public function is_enabled(): bool {
			return false;
		}
	}
}

namespace {
	/**
	 * AI Ability class stub.
	 */
	class WP_AI_Ability {
		/**
		 * Executes the ability with given arguments.
		 *
		 * @param array<string, mixed> $args The ability arguments.
		 * @return mixed The result of the ability execution.
		 */
		public function execute( array $args ) {
			return null;
		}
	}

	/**
	 * Registers an AI ability with WordPress.
	 *
	 * @param string               $name    The ability name/identifier.
	 * @param array<string, mixed> $ability The ability configuration.
	 * @return bool True on success, false on failure.
	 */
	function wp_register_ability( string $name, array $ability ): bool {
		return true;
	}

	/**
	 * Retrieves a registered AI ability.
	 *
	 * @param string $name The ability name.
	 * @return WP_AI_Ability|null The ability object or null if not found.
	 */
	function wp_get_ability( string $name ): ?WP_AI_Ability {
		return null;
	}
}

