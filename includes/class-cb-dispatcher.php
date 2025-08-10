<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class CB_Dispatcher {
	public static function generate_and_send_campaign( $post_ids, $settings, $sections_map, $providers ) {
		$blocks = array();

		if ( ! empty( $sections_map ) ) {
			foreach ( $sections_map as $section_key => $post_id ) {
				$post = get_post( $post_id );
				if ( ! $post ) {
					continue; }
				$img                    = get_the_post_thumbnail_url( $post_id, 'medium' );
				$content_html           = apply_filters( 'the_content', $post->post_content );
				$link                   = get_permalink( $post_id );
				$title_raw              = (string) get_post_field( 'post_title', $post_id );
				$title_decoded          = html_entity_decode( $title_raw, ENT_QUOTES, 'UTF-8' );
				$title_clean            = preg_replace( '/\s*\(\d+\s*[×x]\s*\d+\)\s*$/u', '', $title_decoded );
				$blocks[ $section_key ] = sprintf(
					"<div>%s<h3>%s</h3>%s<p><a href='%s'>Read more</a></p></div>",
					$img ? sprintf( "<img src='%s' style='max-width:100%%'>", esc_url( $img ) ) : '',
					esc_html( $title_clean ),
					wp_kses_post( $content_html ),
					esc_url( $link )
				);
			}
			return self::dispatch_to_provider( $blocks, $settings, $providers );
		}

		foreach ( array_slice( (array) $post_ids, 0, 8 ) as $index => $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue; }
			$img           = get_the_post_thumbnail_url( $post_id, 'medium' );
			$content_html  = apply_filters( 'the_content', $post->post_content );
			$link          = get_permalink( $post_id );
			$title_raw     = (string) get_post_field( 'post_title', $post_id );
			$title_decoded = html_entity_decode( $title_raw, ENT_QUOTES, 'UTF-8' );
			$title_clean   = preg_replace( '/\s*\(\d+\s*[×x]\s*\d+\)\s*$/u', '', $title_decoded );
			$blocks[ 'CONTENT_BLOCK_' . ( $index + 1 ) ] = sprintf(
				"<div>%s<h3>%s</h3>%s<p><a href='%s'>Read more</a></p></div>",
				$img ? sprintf( "<img src='%s' style='max-width:100%%'>", esc_url( $img ) ) : '',
				esc_html( $title_clean ),
				wp_kses_post( $content_html ),
				esc_url( $link )
			);
		}
		return self::dispatch_to_provider( $blocks, $settings, $providers );
	}

	public static function dispatch_to_provider( $blocks, $settings, $providers ) {
		$provider_slug = isset( $settings['provider'] ) && isset( $providers[ $settings['provider'] ] ) ? $settings['provider'] : 'mailchimp';
		$provider      = isset( $providers[ $provider_slug ] ) ? $providers[ $provider_slug ] : null;
		if ( ! $provider ) {
			if ( class_exists( 'CampaignBridge_Notices' ) ) {
				CampaignBridge_Notices::error( esc_html__( 'No valid provider selected.', 'campaignbridge' ) );
			}
			return false;
		}
		return $provider->send_campaign( $blocks, $settings );
	}
}
