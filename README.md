# WC Advanced Bulk Price Updater 🚀

A lightweight, high-capacity WooCommerce plugin engineered specifically for large stores (10,000+ products) hosted on strict shared environments (like Hostinger, SiteGround, or Bluehost). 

It updates product prices in real-time using asynchronous AJAX batching, avoiding common server pitfalls like PHP execution timeouts, out-of-memory crashes, and ModSecurity firewall blocks.

---

## ⚡ The Problem It Solves

Standard WooCommerce bulk edit tools rely on single-request processing or aggressive background queues. On high-volume catalogs (10k+ items), this regularly leads to:
* 🔴 **504 Gateway Timeouts**
* 🔴 **503 Service Unavailable Errors**
* 🔴 **ModSecurity/Firewall connection drops** (due to rapid-fire DB requests)
* 🔴 **Accidental price compounding** on overlapping categories

## 🛡️ Core Features & Protections

* **Firewall-Friendly Throttling:** Runs in small, controlled batches (20 items/request) with an enforced 3-second breathing pause between runs to fly under server rate-limiting radars.
* **24-Hour Double-Run Shield:** Automatically stamps processed products with a custom timestamp meta tag (`_wc_bpu_last_run`). If you re-run an update on an overlapping category, previously modified products are safely skipped.
* **Strict Taxonomy Isolation:** Supports single or multi-category filtering with `include_children => false`, guaranteeing parent category runs won't unintentionally alter child category items twice.
* **Granular Filtering:** Target by Product Type (*Simple*, *Variable*, or *Both*) and Status (*Published*, *Drafts*, *Pending*, *Private*, or *Any*).
* **Live Visual UI:** Real-time percentage bar with total count tracking so you never have to guess if the script flatlined.

---

## 🛠️ Installation

1. Download the `wc-bulk-price-updater.php` file (or zip the folder).
2. Upload to your WordPress directory under `/wp-content/plugins/`.
3. Activate **WC Advanced Bulk Price Updater** in your WordPress Admin Dashboard.

---

## 🚀 Usage Guide

1. Navigate to **WooCommerce > Bulk Price Update** in your WordPress menu.
2. Set your **Percentage Increase (%)** (e.g., `25` for a +25% bump).
3. Choose your target **Product Type** and **Product Status**.
4. Highlight one, multiple, or all **Categories**.
5. Click **Start Bulk Update** and leave the tab open until the progress bar reaches 100%.

> ☕ **Pro-Tip:** Grab a coffee while it runs. The 3-second pause keeps Hostinger happy, even if it takes a few extra minutes for massive catalogs!

---

## 📄 License

Distributed under the **MIT License**. See `LICENSE` for more information.
