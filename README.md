<h1 align="center">Importon Bridge</h1>

<p align="center">
  <strong>Import products into WooCommerce from any product page via a browser companion.</strong>
  <br>
  No scraping UIs, no API keys required. Just a lightweight Chrome extension + WordPress plugin.
</p>

<p align="center">
  <a href="https://github.com/nasratulnayem/importon-bridge/releases"><img src="https://img.shields.io/badge/version-0.1.0-blue.svg" alt="Version 0.1.0"></a>
  <a href="https://wordpress.org/plugins/"><img src="https://img.shields.io/badge/WordPress-6.5%2B-green.svg" alt="WordPress 6.5+"></a>
  <a href="https://woocommerce.com/"><img src="https://img.shields.io/badge/WooCommerce-required-96588A.svg" alt="WooCommerce required"></a>
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg" alt="PHP 7.4+"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-GPLv2-blue.svg" alt="GPLv2 License"></a>
</p>

---

## Overview

Importon Bridge lets you import products from any webpage directly into WooCommerce. A lightweight Chrome extension captures product data from the browser and sends it to your WordPress site through authenticated REST endpoints.

### Features

- **Browser Companion** — click-to-import from Alibaba, Amazon, or any product page
- **Batch URL Import** — queue multiple product URLs with run history and error logs
- **AI Rewriting** — optional product title/description rewrite via OpenAI or Gemini
- **Variable Products** — supports simple and variable products with images, attributes, variations, and pricing
- **No Scraping UI** — all data capture happens in your browser, not in WordPress

---

## Installation

### WordPress Plugin

1. Download the latest `importon-bridge-plugin.zip` from the [releases page](https://github.com/nasratulnayem/importon-bridge/releases)
2. Go to **WP Admin → Plugins → Add New → Upload Plugin**
3. Upload the zip and activate
4. Ensure **WooCommerce** is installed and active

### Chrome Extension

1. Download `importon-bridge-extension.zip` from the [releases page](https://github.com/nasratulnayem/importon-bridge/releases)
2. Unzip the file to a folder on your computer
3. Open `chrome://extensions` and enable **Developer mode** (toggle top-right)
4. Click **Load unpacked** and select the unzipped folder
5. Pin the extension to your toolbar for easy access

### Connection

1. In WordPress admin, open **Importon Bridge → Settings**
2. Click **Connect** to generate credentials and connect the extension
3. The extension popup will show your connection status and available categories

---

## Usage

1. Navigate to any product page (Alibaba, Amazon, your own store, etc.)
2. Click the Importon Bridge extension icon
3. Review and adjust product details
4. Click **Import** — the product is created in your WooCommerce store

For batch imports, use the **URL Import** page in WordPress admin to paste multiple URLs and queue them for processing.

---

## AI Rewriting

Optionally configure OpenAI or Gemini API keys in **Importon Bridge → Settings → AI Rewrite** to automatically rewrite product titles and descriptions during import.

---

## Development

```bash
# Clone the repo
git clone git@github.com:nasratulnayem/importon-bridge.git

# The plugin lives in wp-content/plugins/importon-bridge-plugin/
# The extension lives in wp-content/plugins/importon-bridge-extension/
```

### Building the plugin zip

```bash
cd importon-bridge-plugin
zip -r importon-bridge-plugin.zip . -x "*.git*" -x "*.DS_Store"
```

### Building the extension zip

```bash
cd importon-bridge-extension
zip -r importon-bridge-extension.zip . -x "*.git*" -x "*.DS_Store"
```

---

## Changelog

### 0.1.0

- Initial release
- Browser companion import via authenticated REST API
- WooCommerce product creation and updates (simple + variable)
- Optional AI rewriting (OpenAI / Gemini)
- Batch URL import queue with run logs

---

## License

GPLv2 or later. See [LICENSE](license.txt).

---

<p align="center">
  <sub>Importon Bridge is not affiliated with Alibaba Group. Alibaba is referenced only to describe supported product pages.</sub>
</p>
