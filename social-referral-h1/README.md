# Social Referral H1 Modifier

A WordPress plugin that detects where a visitor came from — a social media platform or a paid ad campaign — and dynamically adds custom text to the H1 heading on your landing pages.

## Installation

1. Copy the `social-referral-h1/` folder into `wp-content/plugins/`.
2. In the WordPress admin go to **Plugins** and activate **Social Referral H1 Modifier**.
3. Go to **Settings → Social Referral H1** to configure the plugin.

---

## How It Works

### Detection

When a visitor arrives, the plugin checks for a referral signal in this priority order:

| Priority | Group | Method | Example |
|----------|-------|--------|---------|
| 1 | **Ad platform** | UTM source + paid medium | `?utm_source=google&utm_medium=cpc` |
| 1 | **Ad platform** | Platform click ID + paid medium | `?fbclid=…&utm_medium=paid_social` |
| 1 | **Ad platform** | Google click ID param | `?gclid=…` |
| 2 | **Social** | UTM source parameter | `?utm_source=facebook` |
| 2 | **Social** | Platform click ID param | `?fbclid=…` (no paid medium) |
| 2 | **Social** | HTTP Referer domain | Browser referred from `facebook.com` |

Ad platforms are checked first so that paid Facebook/Instagram traffic is correctly identified as **Meta Ads** rather than organic social.

The detected platform is stored in a **30-minute cookie**, so the H1 addition persists even if the visitor passes through a redirect (e.g. a UTM-stripping redirect).

### H1 Modification

After detection, the plugin uses output buffering to intercept the full page HTML and inserts the configured text into the **first `<h1>` tag**. The addition is wrapped in `<span class="srh1-addition">` so you can target it with CSS.

---

## Configuration

All settings are at **Settings → Social Referral H1**.

### Display

| Setting | Description |
|---------|-------------|
| **Position** | **Before** inserts the text before the existing H1 content. **After** (default) appends it. |

### Target Pages

| Option | Behaviour |
|--------|-----------|
| **Landing pages** *(default)* | All singular pages and posts. Excludes archives, the blog index, search results, etc. |
| **All pages** | Every page on the site. |
| **Specific pages** | Only the pages whose IDs you enter (comma-separated, e.g. `42, 57, 103`). |

### Ad Platforms

Each ad platform has its own editable H1 text and an enable/disable toggle.

| Platform | H1 Text (default) | Detected via |
|----------|-------------------|--------------|
| **Google Ads** | `Welcome, Google Ads visitor!` | `gclid` URL param, or `utm_source=google` + paid `utm_medium` |
| **Meta Ads** | `Welcome, Meta Ads visitor!` | `fbclid` + paid `utm_medium`, or `utm_source` ∈ {facebook, fb, instagram, ig, meta} + paid `utm_medium` |

**Paid `utm_medium` values recognised:** `cpc`, `ppc`, `paid`, `paid_search`, `paid_social`, `display`, `cpv`, `cpm`, `remarketing`, `retargeting`.

### Social Platforms

Each platform has its own editable H1 text and an enable/disable toggle.

| Platform | H1 Text (default) | UTM source | Click ID | Referrer domains |
|----------|-------------------|------------|----------|-----------------|
| **Facebook** | `Welcome from Facebook!` | `facebook`, `fb` | `fbclid` | facebook.com, fb.com, l.facebook.com, m.facebook.com |
| **Instagram** | `Welcome from Instagram!` | `instagram`, `ig` | `igshid` | instagram.com, l.instagram.com |
| **Twitter / X** | `Welcome from Twitter / X!` | `twitter`, `x` | `twclid` | twitter.com, x.com, t.co |
| **LinkedIn** | `Welcome from LinkedIn!` | `linkedin` | — | linkedin.com, lnkd.in |
| **Pinterest** | `Welcome from Pinterest!` | `pinterest` | — | pinterest.com, pin.it |
| **TikTok** | `Welcome from TikTok!` | `tiktok`, `tt` | `ttclid` | tiktok.com, vm.tiktok.com |
| **YouTube** | `Welcome from YouTube!` | `youtube`, `yt` | — | youtube.com, youtu.be, m.youtube.com |

---

## Styling the Addition

The inserted text is wrapped in a `<span>` with the class `srh1-addition`:

```html
<h1>Our Landing Page <span class="srh1-addition">Welcome from Facebook!</span></h1>
```

Add CSS to your theme to style it:

```css
.srh1-addition {
    font-size: 0.6em;
    font-weight: normal;
    color: #1877f2; /* Facebook blue */
}
```

---

## File Structure

```
social-referral-h1/
├── social-referral-h1.php                    # Plugin bootstrap and main class
├── includes/
│   ├── class-social-referral-detector.php   # Referral detection (ad + social)
│   ├── class-h1-modifier.php                # H1 modification via output buffering
│   └── class-admin-settings.php            # Admin settings page and option handling
└── assets/
    └── admin.css                            # Admin page styles
```

---

## Requirements

- WordPress 5.9 or later
- PHP 8.0 or later
