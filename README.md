[![Licensed under the MIT License](https://img.shields.io/badge/License-MIT-blue.svg)](https://github.com/report-uri/passkeys-php/blob/main/LICENSE)
[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0+-green.svg)](https://php.net)

# passkeys-php
*A security-focused PHP WebAuthn (FIDO2 / Passkeys) server library.*

This is a maintained fork of [lbuchs/WebAuthn](https://github.com/lbuchs/WebAuthn) by [Report URI](https://report-uri.com), forked at upstream `v2.2.0`. Goal: provide a small, lightweight, understandable library to protect logins with passkeys, security keys (Yubico, Solo), platform authenticators (Touch ID, Face ID, Windows Hello), etc. — with security fixes applied.

## Why fork

Upstream is effectively dormant. A pen test of Report URI's passkey integration surfaced several conformance issues; fixes were submitted as PRs to lbuchs/WebAuthn but have not been merged. This fork ships those fixes inline so consumers don't need to maintain patches of their own.

### Security improvements vs lbuchs/WebAuthn v2.2.0

- **Tighter origin check** — the previous regex-based RP-ID match treated the RP ID as a substring suffix (e.g. RP `example.com` would match host `evil-example.com`). Now an exact match or true subdomain (RP ID preceded by a dot).
- **Cross-origin rejection** — `processCreate` / `processGet` now reject ceremonies where `clientDataJSON.crossOrigin === true` (WebAuthn Level 3 §7.1 Step 10, §7.2 Step 13).
- **`none` attestation hardening** — the `attStmt` for the `none` attestation format must be an empty CBOR map (WebAuthn §8.7). Non-empty maps are now rejected.
- **Backup flag validation** — `AuthenticatorData` now rejects flag bytes where Backup State (BS) is set without Backup Eligible (BE), per spec.
- **Token Binding rejection** — `clientDataJSON.tokenBinding.status === 'present'` is rejected (WebAuthn Level 2 §7.1 Step 6, §7.2 Step 10), since this library does not implement Token Binding.

Each fix is a separate commit on `main` for easy auditing.

## Installation

```bash
composer require report-uri/passkeys-php
```

The library autoloads under PSR-4 as `ReportUri\Passkeys\`. The main entry point is `ReportUri\Passkeys\WebAuthn` (the class name is kept aligned with the W3C spec name).

```php
use ReportUri\Passkeys\WebAuthn;

$server = new WebAuthn('My App', 'example.com');
```

## Manual
See [`_test/`](_test/) for a simple working demo. The `server.php` + `client.html` pair exercises registration and login end-to-end.

### Supported attestation statement formats
* android-key
* android-safetynet
* apple
* fido-u2f
* none
* packed
* tpm

> This library supports authenticators which are signed with an X.509 certificate or which are self-attested. ECDAA is not supported.

## Workflow

             JAVASCRIPT            |          SERVER
    ------------------------------------------------------------
                             REGISTRATION


       window.fetch  ----------------->     getCreateArgs
                                                 |
    navigator.credentials.create   <-------------'
            |
            '------------------------->     processCreate
                                                 |
          alert ok or fail      <----------------'


    ------------------------------------------------------------
                          VALIDATION


       window.fetch ------------------>      getGetArgs
                                                 |
    navigator.credentials.get   <----------------'
            |
            '------------------------->      processGet
                                                 |
          alert ok or fail      <----------------'

## Attestation
Typically, when someone logs in, you only need to confirm that they are using the same device they used during registration — in that case you do not require attestation. If you need stronger guarantees (e.g. requiring a specific authenticator model) you can verify authenticity through direct attestation.

### no attestation
Just verify that the device is the same device used at registration. Use `'none'` attestation if you only select `none` as the format.

> Probably what you want for a public website with passkey login.

### indirect attestation
The browser may replace the AAGUID and attestation statement with a more privacy-friendly or more easily verifiable version (e.g. via an anonymization CA). You cannot validate against any root CA if the browser uses an anonymization certificate. This library sets attestation to `indirect` if you select multiple formats but don't provide any root CA.

### direct attestation
The browser provides data about the identificator device, so the device can be identified uniquely. The browser may warn the user about this. This library sets attestation to `direct` if you select multiple formats and provide root CAs.

## Passkeys / Client-side discoverable Credentials
A Client-side discoverable Credential Source is a public-key credential source whose private key is stored in the authenticator, client or client device. This requires a resident-credential-capable authenticator (FIDO2 hardware, not older U2F).

> Passkeys allow sharing credentials stored on one device with other devices. From a server's perspective there is no difference to client-side discoverable credentials — the OS handles cross-device sync transparently.

### How does it work?
In a typical server-side key management flow, the user enters their username (and maybe password). The server validates and returns a list of public-key identifiers for that user; the authenticator picks the first credential it issued and signs.

In a client-side flow, the user does not need to provide a username. The authenticator searches its own memory for a key bound to the relying party (domain). If a key is found, the authentication process proceeds as it would if the server had sent a list of identifiers.

### How can I use it?
#### on registration
When calling `ReportUri\Passkeys\WebAuthn->getCreateArgs`, set `$requireResidentKey` to true so the authenticator saves the registration in its memory.

#### on login
When calling `ReportUri\Passkeys\WebAuthn->getGetArgs`, don't provide any `$credentialIds` — the authenticator will look up the IDs in its own memory and return the user ID as `userHandle`. Set the type of authenticator to `hybrid` (passkey scanned via QR code) and `internal` (passkey stored on the device itself).

#### caveat
The RP ID (domain) is saved on the authenticator. If an authenticator is lost it is theoretically possible to find the services it's used with and log in there.

### device support
Built-in passkeys that automatically sync to all of a user's devices: see [passkeys.dev/device-support](https://passkeys.dev/device-support/).
* Apple iOS 16+ / iPadOS 16+ / macOS Ventura+
* Android 9+
* Microsoft Windows 11 23H2+

## Requirements
* PHP >= 8.0 with [OpenSSL](http://php.net/manual/en/book.openssl.php) and [Multibyte String](https://www.php.net/manual/en/book.mbstring.php)
* Browser with [WebAuthn support](https://caniuse.com/webauthn)
* PHP [Sodium](https://www.php.net/manual/en/book.sodium.php) (or [Sodium Compat](https://github.com/paragonie/sodium_compat)) for [Ed25519](https://en.wikipedia.org/wiki/EdDSA#Ed25519) support, or OpenSSL with native Ed25519 support (PHP ≥ 8.4)

## Credits

The original library was written by [Lukas Buchs](https://github.com/lbuchs) under the MIT license. See [NOTICE.md](NOTICE.md) for full attribution.

## License

[MIT](LICENSE) — same as upstream.

## Further reading
* [W3C WebAuthn spec](https://www.w3.org/TR/webauthn/)
* [MDN: Web Authentication API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Authentication_API)
* [passkeys.dev](https://passkeys.dev/)
* [FIDO Alliance](https://fidoalliance.org)
