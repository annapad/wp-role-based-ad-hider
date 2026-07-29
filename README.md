# WP Role-Based Ad Hider

Hide ads from users with specific WordPress roles or capabilities. Useful for membership sites and subscription platforms where premium users should have an ad-free experience.

## What it does

- Adds a `has-ad-free-access` class to `<body>` when the current user matches configured roles or capabilities
- Injects CSS that hides common ad containers (AdSense, Google Ad Manager, Ad Inserter, Advanced Ads) for those users
- Runs a lightweight MutationObserver to catch ads inserted dynamically after page load
- Fully configurable CSS selectors so you can target ads from any ad system

## Why it exists

Most "hide ads for members" solutions either:

- Only work with the ad plugin they ship with
- Miss ads that load asynchronously after page render
- Hide with `display: none` on the client side only, so the ad request still fires

This plugin gates on a server-rendered body class plus a lightweight observer for async ads. It won't stop ad *requests* — you'd need to intercept the actual ad scripts for that — but it does keep the visual experience clean for paying users.

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate through the Plugins menu
3. Go to **Settings → Ad Hider** to configure roles and selectors

## Configuration

**Eligible Roles:** Check the WordPress roles that should get the ad-free experience (e.g. Subscriber, Editor, or a custom role like `premium_member`).

**Eligible Capability:** Optional. Enter a capability slug (e.g. `read_private_content`). Users with this capability will also see the site without ads, regardless of their role.

**Custom CSS Selectors:** One selector per line. These are added on top of the built-in list.

**Default selectors (always applied):**

- `.adsbygoogle` (Google AdSense)
- `[id^="div-gpt-ad"]` (Google Ad Manager / GPT)
- `[class*="ai-viewport-"]` (Ad Inserter)
- `[class*="advads-"]` (Advanced Ads)
- `.ad-container`, `.advertisement` (generic)

## Filters

**`ad_hider_selectors`** — Filter the array of CSS selectors before rendering.

```php
add_filter( 'ad_hider_selectors', function ( $selectors ) {
    $selectors[] = '.my-custom-ad-block';
    return $selectors;
} );
```

**`ad_hider_should_hide`** — Filter the boolean decision for the current user.

```php
add_filter( 'ad_hider_should_hide', function ( $should_hide ) {
    // Force ad-free for a specific user.
    if ( get_current_user_id() === 42 ) {
        return true;
    }
    return $should_hide;
} );
```

## Requirements

- WordPress 5.0 or later
- PHP 7.4 or later

## License

GPL-2.0-or-later. Free to use, modify, and redistribute under the terms of the GPL.
