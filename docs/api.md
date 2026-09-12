# CampaignBridge REST API

The API namespace is `campaignbridge/v1`. Routes are registered in `includes/REST/Routes.php`, `includes/REST/Editor_Settings_Routes.php`, `includes/REST/Brand_Kit_Routes.php`, and `includes/REST/Preview_Routes.php`.

`GET` and `PUT /campaignbridge/v1/brand-kit` read and update the stored email brand colours. `PUT` accepts one slot (`id` and a portable hex `color`). Both require the management capability.

`POST /campaignbridge/v1/preview` compiles unsaved editor content into the canonical email artifact. It accepts `template_id` (integer, required), `content` (string, required, max 512 KB), and optional `metadata` (object with `title`, `language`, `background_color`, `unsubscribe_url`). The response includes `html`, `text`, `diagnostics`, `assets`, `compiler_version`, `profile_version`, and `fingerprint`. A document that fails validation returns diagnostics with HTTP 200 and no HTML. Requires the management capability plus `edit_post` on the target template. Rate-limited.

All administrative endpoints require the `campaignbridge_manage` capability (defined in `includes/Core/Capabilities.php`). Mutations additionally validate their WordPress nonce. Request arguments use WordPress REST schemas with sanitization and validation callbacks; errors return `WP_Error` with an HTTP status.

Credential encryption and reveal endpoints are administrative operations. They are rate-limited and never accept plaintext through the decryption path. Consumers must not cache responses containing revealed credentials.

The route implementation remains the authoritative reference while a generated OpenAPI contract is developed.
