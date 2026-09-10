<?php

declare(strict_types=1);

$root      = dirname( __DIR__, 2 );
$forbidden = array( 'WP_Query', 'get_posts', 'get_post_meta', 'update_post_meta', 'delete_post_meta', 'get_option', 'update_option', 'delete_option' );
$files     = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/includes', FilesystemIterator::SKIP_DOTS ) );
$matches   = array();

foreach ( $files as $file ) {
	if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
		continue;
	}
	$relative = substr( $file->getPathname(), strlen( $root ) + 1 );
	if ( 'includes/Core/Storage.php' === $relative || str_starts_with( $relative, 'includes/Repository/' ) ) {
		continue;
	}
	$tokens = token_get_all( (string) file_get_contents( $file->getPathname() ) );
	foreach ( $tokens as $index => $token ) {
		if ( is_array( $token ) && T_VARIABLE === $token[0] && '$wpdb' === $token[1] ) {
			$matches[ $relative ] = true;
			break;
		}
		if ( ! is_array( $token ) || T_STRING !== $token[0] || ! in_array( $token[1], $forbidden, true ) ) {
			continue;
		}
		$previous = null;
		for ( $cursor = $index - 1; $cursor >= 0; --$cursor ) {
			if ( is_array( $tokens[ $cursor ] ) && in_array( $tokens[ $cursor ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$previous = $tokens[ $cursor ];
			break;
		}
		$next = null;
		for ( $cursor = $index + 1, $count = count( $tokens ); $cursor < $count; ++$cursor ) {
			if ( is_array( $tokens[ $cursor ] ) && in_array( $tokens[ $cursor ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$next = $tokens[ $cursor ];
			break;
		}
		$method = is_array( $previous ) && in_array( $previous[0], array( T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_FUNCTION, T_FN ), true );
		if ( '(' === $next && ! $method ) {
			$matches[ $relative ] = true;
			break;
		}
	}
}

$paths = array_keys( $matches );
sort( $paths );
echo implode( PHP_EOL, $paths );
if ( array() !== $paths ) {
	echo PHP_EOL;
}
