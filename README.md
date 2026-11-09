# OdooWoo Invoices

WordPress plugin for the WooCommerce shop. It receives invoices that [OdooWoo](https://beltoft.net/) pushes when an invoice is posted in Odoo, stores the PDF against the order, and lets the customer download it from My Account, behind the shop's own login.

## How it works

1. OdooWoo posts a signed JSON message (invoice number, date, total and the PDF) to `POST /wp-json/odoowoo-invoices/v1/invoice`.
2. The plugin checks the signature, stores the PDF under `wp-content/uploads/odoowoo-invoices/` with a random file name, and adds an order note.
3. My Account → Orders shows a **Download invoice** button, and the order page lists all invoices. Shop managers see the links on the order screen.
4. Files never have a public URL. Downloads go through `admin-post.php?action=odoowoo_invoice` and are allowed only for the order's customer, shop managers, or, for guest orders, a visitor holding the order key.

## Setup

1. Install and activate the plugin (WooCommerce required).
2. WooCommerce → OdooWoo Invoices: paste the **webhook secret** of your OdooWoo dashboard. Alternatively define `ODOOWOO_INVOICES_SECRET` in `wp-config.php`.
3. In the OdooWoo dashboard, switch on sending invoices to the shop.
4. Your Odoo needs the current OdooWoo helper module (it provides `render_invoice_pdf_by_id`).

Without a secret the endpoint refuses every request.

### nginx

`.htaccess` is ignored by nginx. File names are random 128-bit values and nothing links to them, but block the folder as well:

```nginx
location ^~ /wp-content/uploads/odoowoo-invoices/ { deny all; return 404; }
```

## Signature

Headers `X-OdooWoo-Timestamp` (unix seconds, at most 5 minutes old) and `X-OdooWoo-Signature`: lowercase hex HMAC-SHA256, key = the shared secret, over `<timestamp>.<raw request body>`.

## Development

```bash
/root/plugin-tests/run.sh /var/www/wordpress/workspaces/odoowoo-invoices
```

Uninstalling removes the saved secret only. Stored invoices are customer records and stay in `uploads/odoowoo-invoices/`.

GPL-2.0-or-later.
