<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Mailchimp webhook signature and payload tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Provider;

use CampaignBridge\Providers\Mailchimp_Webhook_Receiver;
use WP_UnitTestCase;

/** Proves only fresh deliveries signed with a stored secret are authenticated, and only a campaign ID is read. */
final class Mailchimp_Webhook_Receiver_Test extends WP_UnitTestCase {
	private const NOW    = 1790000000;
	private const SECRET = 'whsec-test-0123456789abcdefghijklmnopqrstu';
	private const BODY   = 'type=campaign&fired_at=2026-10-17+18%3A00%3A00&data%5Bid%5D=mc0042&data%5Bsubject%5D=Spring+sale&data%5Bstatus%5D=sent&data%5Breason%5D=&data%5Blist_id%5D=abc123';

	/** @return array<string, string> */
	public static function signed( string $body, string $secret, int $timestamp ): array {
		return array( Mailchimp_Webhook_Receiver::SIGNATURE_HEADER => 't=' . $timestamp . ',v1=' . hash_hmac( 'sha256', $timestamp . '.' . $body, $secret ) );
	}

	public function test_a_fresh_delivery_signed_with_a_stored_secret_is_authenticated(): void {
		$receiver = new Mailchimp_Webhook_Receiver();
		$delivery = $receiver->authenticate( self::BODY, self::signed( self::BODY, self::SECRET, self::NOW - 10 ), array( 'other-secret', self::SECRET ), self::NOW );

		self::assertNotNull( $delivery, 'Any stored secret may have signed it.' );
		self::assertSame( self::NOW - 10 + Mailchimp_Webhook_Receiver::TOLERANCE, $delivery->expires_at() );
		self::assertSame( 64, strlen( $delivery->key() ) );
		self::assertStringNotContainsString( 'mc0042', $delivery->key() );
		self::assertSame( $delivery->key(), $receiver->authenticate( self::BODY, self::signed( self::BODY, self::SECRET, self::NOW - 10 ), array( self::SECRET ), self::NOW )?->key(), 'The same delivery has the same key.' );
	}

	public function test_a_wrong_missing_or_malformed_signature_is_refused(): void {
		$receiver = new Mailchimp_Webhook_Receiver();
		$header   = Mailchimp_Webhook_Receiver::SIGNATURE_HEADER;
		$cases    = array(
			'wrong secret'   => self::signed( self::BODY, 'not-the-secret', self::NOW ),
			'altered body'   => self::signed( self::BODY . '&x=1', self::SECRET, self::NOW ),
			'missing header' => array(),
			'empty header'   => array( $header => '' ),
			'no timestamp'   => array( $header => 'v1=' . hash_hmac( 'sha256', self::NOW . '.' . self::BODY, self::SECRET ) ),
			'no signature'   => array( $header => 't=' . self::NOW ),
			'two timestamps' => array( $header => 't=' . self::NOW . ',t=' . self::NOW . ',v1=' . hash_hmac( 'sha256', self::NOW . '.' . self::BODY, self::SECRET ) ),
			'uppercase hex'  => array( $header => 't=' . self::NOW . ',v1=' . strtoupper( hash_hmac( 'sha256', self::NOW . '.' . self::BODY, self::SECRET ) ) ),
			'oversized'      => array( $header => 't=' . self::NOW . str_repeat( ',v0=x', 300 ) ),
		);
		foreach ( $cases as $label => $headers ) {
			self::assertNull( $receiver->authenticate( self::BODY, $headers, array( self::SECRET ), self::NOW ), $label );
		}
		self::assertNull( $receiver->authenticate( self::BODY, self::signed( self::BODY, self::SECRET, self::NOW ), array(), self::NOW ), 'No stored secret, no delivery.' );
		self::assertNull( $receiver->authenticate( self::BODY, self::signed( self::BODY, '', self::NOW ), array( '' ), self::NOW ), 'An empty secret never matches.' );
	}

	public function test_a_stale_or_future_signature_is_refused(): void {
		$receiver = new Mailchimp_Webhook_Receiver();

		self::assertNotNull( $receiver->authenticate( self::BODY, self::signed( self::BODY, self::SECRET, self::NOW - Mailchimp_Webhook_Receiver::TOLERANCE ), array( self::SECRET ), self::NOW ) );
		self::assertNull( $receiver->authenticate( self::BODY, self::signed( self::BODY, self::SECRET, self::NOW - Mailchimp_Webhook_Receiver::TOLERANCE - 1 ), array( self::SECRET ), self::NOW ), 'A replay after five minutes fails on its own.' );
		self::assertNotNull( $receiver->authenticate( self::BODY, self::signed( self::BODY, self::SECRET, self::NOW + Mailchimp_Webhook_Receiver::FUTURE_SKEW ), array( self::SECRET ), self::NOW ) );
		self::assertNull( $receiver->authenticate( self::BODY, self::signed( self::BODY, self::SECRET, self::NOW + Mailchimp_Webhook_Receiver::FUTURE_SKEW + 1 ), array( self::SECRET ), self::NOW ) );
	}

	public function test_only_a_campaign_event_names_a_campaign(): void {
		$receiver = new Mailchimp_Webhook_Receiver();

		self::assertSame( 'mc0042', $receiver->event( self::BODY )?->remote_campaign_id() );
		$subscribe = $receiver->event( 'type=subscribe&fired_at=2026-10-17+18%3A00%3A00&data%5Bemail%5D=person%40example.com&data%5Blist_id%5D=abc123' );
		self::assertNotNull( $subscribe );
		self::assertNull( $subscribe->remote_campaign_id(), 'Subscriber events are ignored without being read.' );
	}

	public function test_a_malformed_body_is_refused(): void {
		$receiver = new Mailchimp_Webhook_Receiver();
		foreach ( array( '', 'not a form', 'type=', 'type=Campaign%21', 'type=campaign', 'type=campaign&data=mc0042', 'type=campaign&data%5Bid%5D=mc%2F0042', 'type%5B%5D=campaign&data%5Bid%5D=mc0042' ) as $body ) {
			self::assertNull( $receiver->event( $body ), $body );
		}
	}
}
