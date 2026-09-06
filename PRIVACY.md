# Privacy Policy — Importon Bridge

**Last updated:** September 7, 2026

Importon Bridge is a browser extension that helps users import product data from supplier websites into their WooCommerce stores. We respect your privacy and are committed to protecting it.

## Data Collection

Importon Bridge does **not** collect, transmit, or store any personal data on external servers.

All data processed by the extension stays entirely within your browser and your own WordPress/WooCommerce site. Specifically:

- **Product data** (titles, images, prices, descriptions) is captured from supplier pages and sent directly to your WordPress site via your own REST API endpoint.
- **Connection credentials** (WordPress application password) are stored locally in your browser using Chrome's built-in storage API and are never shared with any third party.
- **Import queue data** is stored locally in your browser only.

## What We Do Not Collect

- We do not collect personal identifiable information (name, email, address, etc.)
- We do not collect browsing history
- We do not collect analytics or tracking data
- We do not use cookies
- We do not sell, share, or transfer any user data to third parties

## Permissions

The extension requests the following permissions solely to provide its core functionality:

| Permission | Purpose |
|------------|---------|
| `activeTab` | Read product data from the active Alibaba page when you click the import button |
| `storage` | Store your WordPress connection credentials locally |
| `scripting` | Inject content scripts to extract product information from supplier pages |
| `notifications` | Show import progress and status updates |
| `host_permissions` | Communicate with your WordPress site and read supplier page content |

## Third-Party Services

The extension may connect to OpenAI or Google Gemini APIs **only if** you voluntarily configure API keys in the AI Rewriter settings. These connections are made directly from your WordPress server, not from the extension. The extension itself does not communicate with any third-party services.

## Data Security

- All communication between the extension and your WordPress site uses HTTPS when available.
- Credentials are stored locally and never transmitted to any server other than your own WordPress installation.
- The extension does not include any remote code execution.

## Children's Privacy

Importon Bridge is not directed at children under 13 and does not knowingly collect information from children.

## Changes to This Policy

We may update this privacy policy from time to time. Any changes will be reflected in the "Last updated" date above.

## Contact

If you have questions about this privacy policy, please open an issue at:
https://github.com/nasratulnayem/importon-bridge/issues
