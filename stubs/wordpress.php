<?php
/**
 * WordPress stubs for PHPStan
 *
 * Basic stubs for WordPress functions to help with static analysis.
 */

// Options API
function get_option( string $option, $default = false ): mixed {}
function update_option( string $option, $value, $autoload = null ): bool {}
function add_option( string $option, $value = '', $deprecated = '', $autoload = 'yes' ): bool {}
function delete_option( string $option ): bool {}

// Transients API
function get_transient( string $transient ): mixed {}
function set_transient( string $transient, $value, int $expiration = 0 ): bool {}
function delete_transient( string $transient ): bool {}

// Post Meta API
function get_post_meta( int $post_id, string $key = '', bool $single = false ): mixed {}
function update_post_meta( int $post_id, string $key, $value, $prev_value = '' ): int|bool {}
function add_post_meta( int $post_id, string $key, $value, bool $unique = false ): int|bool {}
function delete_post_meta( int $post_id, string $key, $value = '' ): bool {}

// User Meta API
function get_user_meta( int $user_id, string $key = '', bool $single = false ): mixed {}
function update_user_meta( int $user_id, string $key, $value, $prev_value = '' ): int|bool {}
function add_user_meta( int $user_id, string $key, $value, bool $unique = false ): int|bool {}
function delete_user_meta( int $user_id, string $key, $value = '' ): bool {}

// Cache API
function wp_cache_get( string $key, string $group = '', bool $force = false, $found = null ): mixed {}
function wp_cache_set( string $key, $value, string $group = '', int $expire = 0 ): bool {}
function wp_cache_delete( string $key, string $group = '' ): bool {}
function wp_cache_flush_group( string $group ): bool {}
