<h1 align="center">Importon Bridge</h1>

<p align="center">
  Import Alibaba products into WooCommerce with one click.
  <br>
  A lightweight Chrome extension + WordPress plugin.
</p>

<p align="center">
  <a href="https://github.com/nasratulnayem/importon-bridge/releases"><img src="https://img.shields.io/badge/version-0.1.0-blue.svg" alt="Version 0.1.0"></a>
  <a href="https://wordpress.org/plugins/"><img src="https://img.shields.io/badge/WordPress-6.5%2B-green.svg" alt="WordPress 6.5+"></a>
  <a href="https://woocommerce.com/"><img src="https://img.shields.io/badge/WooCommerce-required-96588A.svg" alt="WooCommerce required"></a>
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg" alt="PHP 7.4+"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-GPLv2-blue.svg" alt="GPLv2 License"></a>
</p>

---

## What problem does this solve?

Manually copying product data from Alibaba into WooCommerce is slow and error-prone. Importon Bridge eliminates the copy-paste workflow:

1. Browse Alibaba as usual
2. Click the extension icon
3. Product lands in your WooCommerce store

No API keys, no CSV files, no technical setup.

---

## How it works

```
Alibaba product page  →  Chrome extension  →  WordPress REST API  →  WooCommerce product
```

The Chrome extension reads product data directly from the browser. It sends the data to your WordPress site through authenticated REST endpoints. Your WooCommerce store gets a new product — title, description, images, variants, pricing, and all.

---

## Features

- **One-click import** from any Alibaba product page
- **Batch URL import** — queue multiple Alibaba URLs and process them from WP Admin
- **Variable products** — handles size/color variants, multiple images, attributes, pricing
- **AI rewriting** — optionally rewrite titles and descriptions via OpenAI or Gemini
- **Import history** — view run logs and failed items per batch

---

## Installation

### WordPress plugin

1. Download `importon-bridge-plugin.zip` from [releases](https://github.com/nasratulnayem/importon-bridge/releases)
2. **WP Admin → Plugins → Add New → Upload Plugin**
3. Upload and activate. WooCommerce must be installed.

### Chrome extension

1. Download `importon-bridge-extension.zip` from [releases](https://github.com/nasratulnayem/importon-bridge/releases)
2. Unzip to a folder
3. Open `chrome://extensions` → enable **Developer mode** → **Load unpacked**
4. Select the folder

### Connect

1. Open **Importon Bridge → Settings** in WP Admin
2. Click **Connect**
3. Done — the extension and WordPress are linked

---

## Usage

1. Go to any Alibaba product page
2. Click the Importon Bridge extension icon in your toolbar
3. Review product details and click **Import**
4. The product appears in your WooCommerce store

For bulk imports, paste multiple Alibaba URLs into the **URL Import** page in WP Admin.

---

## AI rewriting (optional)

Configure OpenAI or Gemini API keys in **Importon Bridge → Settings → AI Rewrite** to auto-rewrite product titles and descriptions during import.

---

## Changelog

### 0.1.0

- One-click Alibaba-to-WooCommerce import via Chrome extension
- WooCommerce product creation (simple + variable) with images, attributes, variations, pricing
- Batch URL import queue with run logs and error tracking
- Optional AI rewriting (OpenAI / Gemini)

---

## License

GPLv2 or later. See [license.txt](license.txt).

---

<p align="center">
  <sub>Importon Bridge is not affiliated with Alibaba Group. Alibaba is referenced only to describe supported product pages.</sub>
</p>
