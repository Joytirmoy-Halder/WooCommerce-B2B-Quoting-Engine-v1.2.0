# 🛍️ WooCommerce B2B Quoting Engine (v1.2.0)

<div align="center">
  <p><strong>A beautifully simple, incredibly powerful way to let your customers request bulk or custom quotes instead of forcing them through a rigid checkout. Built for modern businesses.</strong></p>
</div>

---

## ✨ Features at a Glance

Transform your WooCommerce shop into a full-scale B2B engine and direct marketing powerhouse overnight.

- 🎯 **Pinpoint Targeting:** Globally switch your store into a quote-only catalog, or laser-target specific Categories and Individual Products to display the "Add to Quote" interface.
- 🎨 **Visual Design Studio:** An integrated, premium SaaS-like admin panel that lets you dynamically style the appearance of your quote buttons. Customize colors, hover states, border radiuses, and fonts in real-time with our intelligent floating preview overlay.
- 📱 **Multi-Channel Social Commerce:** Go beyond traditional email carts. Customers can drop inquiries directly using the seamlessly integrated messaging dropdown block, natively supporting **WhatsApp**, **Facebook Messenger**, **Telegram**, **Viber**, **Skype**, and **LINE**.
- 📋 **Fail-Safe Clipboard Fallback:** Our engineered Social URL compiler gracefully handles platforms block intent (like FB Messenger) by running a background clipboard-copy fallback to ensure zero friction.
- 💨 **Lightweight & Bulletproof:** Unbreakable DOM virtualization means your settings configurations remain safely housed inside a beautifully separated tabular layout without risking any database conflicts.

---

## ⚡ Direct Message Ordering

The world has moved to instant messaging. The B2B Quoting Engine introduces a state-of-the-art **Social Pipeline**. When active, sleek social icons align gracefully beneath the primary Quote button. Customers can click their preferred app, enter a custom message, and instantly generate a compiled intent ping that redirects them directly into a chat with your sales reps!

![Supported Networks](https://img.shields.io/badge/WhatsApp-25D366?style=for-the-badge&logo=whatsapp&logoColor=white) 
![Supported Networks](https://img.shields.io/badge/Messenger-00B2FF?style=for-the-badge&logo=messenger&logoColor=white) 
![Supported Networks](https://img.shields.io/badge/Telegram-2CA5E0?style=for-the-badge&logo=telegram&logoColor=white)

---

## 🚀 Easy Installation Guide

Even if you're not technical, getting the Quoting Engine running on your WordPress site takes less than 60 seconds.

### Step 1: Upload the Plugin
1. Download the `woo-b2b-quoting-engine` zip file.
2. Log into your WordPress admin dashboard.
3. On the left menu, go to **Plugins** → **Add New**.
4. Click **Upload Plugin** at the top of the screen.
5. Choose the zip file you downloaded and click **Install Now**.

### Step 2: Activate
Once uploaded, click **Activate Plugin**. The engine is now hooked perfectly into WooCommerce.

### Step 3: Configure Your Strategy
1. Navigate to **WooCommerce** → **Settings**.
2. Click the shiny new **B2B Quoting** tab at the top.
3. You'll see our premium, 4-tab dashboard:
    - **General Strategy:** Toggle the master switch to enable quoting everywhere, or pick specific products.
    - **Visual Design Studio:** Change the button text and colors to perfectly match your brand.
    - **Direct Message Ordering:** Type in your WhatsApp number, Messenger username, etc., to magically active the social pipeline.
    - **Social Button Styling:** Paint the social buttons with an alternate shade or leave them blank to match your theme.
4. Click **Save Changes** at the bottom, and you're officially live!

---

## 📜 Changelog

### v1.2.0 (Current Release)
- **Massive UI Overhaul:** Backend settings rebuilt from the ground up, moving away from standard grey WooCommerce styling to a premium, padded SaaS-style card interface.
- **Color Picker Engine Upgrade:** Color selections are now visually segmented using flexbox and customized circular palettes instead of the raw, clunky WordPress defaults.
- **Fail-Safe UI Virtualizer:** Replaced legacy tab hooks with an airtight, Javascript-based DOM injection algorithm to completely protect against fatal server errors when adding new fields.

### v1.1.0 (Skipped/Unreleased)
> *Note: Version 1.1.0 was successfully built to include the new Multi-Channel Social Commerce logic (WhatsApp/Messenger integration). However, during final testing, a fatal core conflict was discovered with WooCommerce's native `WC_Admin_Settings` class when trying to parse the new sub-tabs. Rather than releasing a potentially unstable build to the public, v1.1.0 was intentionally bypassed and merged into our v1.2.0 engineering sprint to fix the architecture permanently using DOM Virtualization.*

---

## 🤝 Author & Contributions
Developed by **Joytirmoy Halder Joyti**  
[Check out my GitHub Portfolio](https://github.com/Joytirmoy-Halder)

---

<p align="center"><i>Engineered with clean architectural standards. Highly extensible. Designed to convert.</i></p>
