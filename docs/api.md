# CampaignBridge REST API

The API namespace is `campaignbridge/v1`. Routes are registered in `includes/REST/Routes.php`, `includes/REST/Editor_Settings_Routes.php`, and `includes/REST/Brand_Kit_Routes.php`.

`GET` and `PUT /campaignbridge/v1/brand-kit` read and update the stored email brand colours. `PUT` accepts one slot (`id` and a portable hex `color`). Both require the management capability.

All administrative endpoints require the configured management capability. Mutations additionally validate their WordPress nonce. Request arguments use WordPress REST schemas with sanitization and validation callbacks; errors return `WP_Error` with an HTTP status.

Credential encryption and reveal endpoints are administrative operations. They are rate-limited and never accept plaintext through the decryption path. Consumers must not cache responses containing revealed credentials.

The route implementation remains the authoritative reference while a generated OpenAPI contract is developed.
