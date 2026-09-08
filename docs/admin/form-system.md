# Admin forms

Three screens use the shared form code: general settings, provider settings,
and post type selection. Their definitions live in `includes/Admin/Screens/`.
These are internal APIs, not a general-purpose form framework.

Declare field types, labels, validation rules, layouts, and messages explicitly.
Field names and form IDs do not infer validation or behavior. For example, the
general settings screen declares email validation and the provider screen declares
the API key minimum length alongside its encrypted field.

`Form` constructs its per-form security, validator, data manager, handler, and
renderer directly after the fields have been configured. The renderer is refreshed
after submission so it reads the saved data. There is no form service container,
configuration cache, query optimizer, or asset optimizer; admin assets are enqueued
by the existing screen asset code.

Submission retains capability and nonce checks, sanitization, encrypted credential
handling, and server-side validation. Conditional evaluation and its submission
guard remain covered by the security suites. Do not bypass these checks in screen
callbacks.

Use the production screen definitions as examples. Tests should submit and render
forms with their real dependencies, and assert persisted values, rejected input,
and escaped output rather than substitute a form container.
