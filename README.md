![Wawp Banner](https://ps.w.org/automation-web-platform/assets/banner-1544x500.png)

### Wawp – Order Notifications, OTP Login, Checkout Verifications & Country Code

[![WP tested 6.8.2](https://img.shields.io/badge/WordPress-6.8.2-blue)](#)
[![Requires PHP 7.4+](https://img.shields.io/badge/PHP-7.4+-blue)](#)
[![License: GPLv3](https://img.shields.io/badge/License-GPLv3-green.svg)](https://opensource.org/licenses/GPL-3.0)

Automate WhatsApp notifications, OTP login/verification, chat widgets, and advanced phone fields for WooCommerce and WordPress.

- Website: https://wawp.net
- Docs: https://wawp.net/get-started/welcome-to-wawp/
- Pricing: https://wawp.net/pricing/
- Community: https://www.facebook.com/groups/wawpcommunity
- Video Tutorials: https://www.youtube.com/@wawpapp

## ✨ Features

- **Notifications**: new orders, status changes, pending payments, review requests, admin alerts, scheduled follow-ups.  
- **OTP Auth**: login, signup, checkout; role-based redirects; welcome messages; alerts on login/signup.  
- **Chat Widget**: multi-agent, QR open, social links, analytics, full customization, display conditions.  
- **Country Code & Validation**: real-time validation, auto-detect, allow/deny lists, auto-format.  
- **Logs**: message history, advanced filters, resend failures.  
- **Customers**: WhatsApp-active checks, multi-numbers per account, per-feature sender selection.

## 🚀 Getting Started

1. Install the plugin (WordPress → Plugins → Add New) or upload to `/wp-content/plugins/`.  
2. Activate it and open **Wawp** in your WP admin.  
3. Create a free account: https://wawp.net/signup  
4. Connect your WhatsApp number (QR).  
5. Paste API keys and add instances: `wp-admin/admin.php?page=wawp&awp_section=instances`.  
6. Configure Notifications / OTP / Chat Widget.

**Free plan:** 200 messages/month. Need more? See [pricing](https://wawp.net/pricing/).

## 🧩 Shortcodes

- `[wawp_otp_login]` — Login form  
- `[wawp_signup_form]` — Signup form  
- `[wawp-fast-login]` — Both forms

## 🛡️ Security Notes

- Treat Access Tokens like passwords; rotate regularly.  
- Use HTTPS for all endpoints.  
- Avoid logging sensitive message contents.

## 📦 Build & Contribute

- Issues & PRs are welcome.  
- Follow WP coding standards and nonces/sanitization best practices.  
- Keep `readme.txt` changelog in sync with releases.

## 📜 License

GPLv3 — see `LICENSE`.

