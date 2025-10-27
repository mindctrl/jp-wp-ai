# PHPStan Stubs

This directory contains stub files for PHPStan static analysis.

## What are Stubs?

Stubs are PHP files that provide type definitions for external dependencies without implementing the actual functionality. They allow PHPStan to understand the structure and types of code that isn't available during static analysis.

## Files

### `wordpress-ai.php`

Provides type definitions for the WordPress AI API that this plugin depends on:

- **`WordPress\AI\Abstracts\Abstract_Feature`** - Abstract base class for AI features
- **`WP_AI_Ability`** - Class representing an AI ability that can be executed
- **`wp_register_ability()`** - Function to register an AI ability
- **`wp_get_ability()`** - Function to retrieve a registered AI ability

## Usage

These stubs are automatically loaded by PHPStan through the `phpstan.neon` configuration file:
