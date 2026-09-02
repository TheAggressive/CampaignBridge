# CampaignBridge REST API

The API namespace is `campaignbridge/v1`. Routes are registered in `includes/REST/Routes.php` and `includes/REST/Editor_Settings_Routes.php`.

All administrative endpoints require the configured management capability. Mutations additionally validate their WordPress nonce. Request arguments use WordPress REST schemas with sanitization and validation callbacks; errors return `WP_Error` with an HTTP status.

Credential encryption and reveal endpoints are administrative operations. They are rate-limited and never accept plaintext through the decryption path. Consumers must not cache responses containing revealed credentials.

The route implementation remains the authoritative reference while a generated OpenAPI contract is developed.
