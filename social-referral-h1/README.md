# Social Referral H1 Modifier

A WordPress plugin that detects social media referral traffic and dynamically adds custom text to the H1 heading on your landing pages.

## Installation

1. Copy the `social-referral-h1/` folder into your WordPress `wp-content/plugins/` directory.
2. Log in to the WordPress admin and go to **Plugins**.
3. Activate **Social Referral H1 Modifier**.
4. Go to **Settings → Social Referral H1** to configure the plugin.

---

## How It Works

### Detection

When a visitor arrives on your site, the plugin checks for a social platform signal in this priority order:

| Priority | Method | Example |
|----------|--------|---------|
| 1 | UTM source parameter | `?utm_source=facebook` |
| 2 | Platform click ID parameter | `?fbclid=…` |
| 3 | HTTP Referer domain match | Browser referred from `facebook.com` |

Once a platform is detected it is stored in a **30-minute cookie**, so the H1 addition persists even if the visitor is redirected (e.g. by a UTM-stripping redirect).

### H1 Modification

After detection, the plugin uses output buffering to intercept the full page HTML and inserts the configured text into the **first `<h1>` tag** on the page. The addition is wrapped in `<span class="srh1-addition">` so you can target it with CSS if needed.

---

## Configuration

All settings are found at **Settings → Social Referral H1** in the WordPress admin.

### H1 Addition

| Setting | Description |
|---------|-------------|
| **Text to Add** | The text inserted into the H1. Use `{platform}` as a placeholder for the detected platform name — e.g. `Welcome from {platform}!` becomes `Welcome from Facebook!`. |
| **Position** | **Before** inserts the text before the existing H1 content. **After** (default) appends it after. |

### Target Pages

Controls which pages the H1 modification is applied to:

| Option | Behaviour |
|--------|-----------|
| **Landing pages** *(default)* | All singular pages and posts (`is_singular()`). Excludes archives, the blog index, search results, etc. |
| **All pages** | Every page on the site. |
| **Specific pages** | Only the pages whose IDs you enter (comma-separated). Example: `42, 57, 103`. |

### Social Platforms

Enable or disable detection for each platform individually using the toggle switches. The table also shows exactly what signals are used for each platform.

| Platform | UTM source values | Click ID param | Referrer domains |
|----------|-------------------|----------------|-----------------|
| Facebook | `facebook`, `fb` | `fbclid` | facebook.com, fb.com, l.facebook.com, m.facebook.com |
| Instagram | `instagram`, `ig` | `igshid` | instagram.com, l.instagram.com |
| Twitter / X | `twitter`, `x` | `twclid` | twitter.com, x.com, t.co |
| LinkedIn | `linkedin` | — | linkedin.com, lnkd.in |
| Pinterest | `pinterest` | — | pinterest.com, pin.it |
| TikTok | `tiktok`, `tt` | `ttclid` | tiktok.com, vm.tiktok.com |
| YouTube | `youtube`, `yt` | — | youtube.com, youtu.be, m.youtube.com |

---

## Styling the Addition

The inserted text is wrapped in a `<span>` with the class `srh1-addition`:

```html
<h1>Our Landing Page <span class="srh1-addition">Welcome from Facebook!</span></h1>
```

You can style it in your theme's CSS:

```css
.srh1-addition {
    font-size: 0.6em;
    color: #1877f2;
    font-weight: normal;
}
```

---

## File Structure

```
social-referral-h1/
├── social-referral-h1.php              # Main plugin file — bootstraps the plugin
├── includes/
│   ├── class-social-referral-detector.php   # Detects platform from UTM, click IDs, and referer
│   ├── class-h1-modifier.php                # Modifies the first H1 via output buffering
│   └── class-admin-settings.php            # Admin settings page and option handling
└── assets/
    └── admin.css                            # Styles for the admin settings page
```

---

## Requirements

- WordPress 5.0 or later
- PHP 7.4 or later
