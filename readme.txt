=== Definitive Announcement Bar for Bricks and WooCommerce ===
Contributors: leokawa
Tags: announcement bar, notification bar, top bar, woocommerce, bricks
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, rotating announcement bar for any theme. Works with Bricks Builder, block and classic themes, and WooCommerce.

== Description ==

Definitive Announcement Bar adds a clean bar to the top of your site for shipping notices, sales, opening hours or any other short message. Add several messages and they cross-fade automatically.

It is built to stay out of your way: one small stylesheet, one small script without jQuery, and nothing is loaded on pages where the bar is hidden.

= Works with your setup =

* **Bricks Builder** – the bar is placed right before your Bricks header. It is never shown inside the builder. Colors, font size and padding accept Bricks CSS variables such as `var(--primary)`.
* **Any other theme** – block themes and classic themes use the standard `wp_body_open` hook. For older themes without that hook, the bar is moved to the top of the page automatically.
* **WooCommerce** – show or hide the bar on the shop, product categories, single products, cart, checkout and My Account. Shortcodes inside messages work, too. Compatible with High-Performance Order Storage and the block-based cart and checkout.
* **Page builders and custom headers** – switch to manual placement and use the `[definitive_announcement_bar]` shortcode wherever you want the bar.

= Features =

* Multiple rotating messages with links, bold and italic text
* Background, text and close button colors, font size, padding and uppercase style
* Optional sticky bar that stays visible while scrolling
* Dismissible for a session or a number of days, and it reappears automatically when you change the messages
* Hide the bar on the front page, blog, posts, pages, search, 404 or WooCommerce pages
* Hide the bar on any single post, page or product from its edit screen
* Show the bar to everyone, only logged-in users or only logged-out visitors
* Schedule a start and end date, for example for a weekend sale
* Works with page caching: dismissal and scheduling are also checked in the browser
* Accessible: keyboard-friendly close button, rotation pauses on hover and focus, and animations are turned off for visitors who prefer reduced motion
* Translation ready and RTL friendly

== Installation ==

1. In your WordPress admin, go to **Plugins → Add New** and search for "Definitive Announcement Bar".
2. Install and activate the plugin.
3. Go to **Settings → Announcement Bar**, add your messages and save.

== Frequently Asked Questions ==

= Does it work with Bricks Builder? =

Yes. When Bricks is your active theme, the bar is printed right before the Bricks header template. If you would rather place it inside your header, choose "Manual" placement and add the `[definitive_announcement_bar]` shortcode with a Bricks Shortcode element.

= Does it work with WooCommerce? =

Yes. WooCommerce pages get their own display rules, so you can, for example, hide the bar on the checkout. You can also hide it on individual products.

= My sticky header covers the bar. What can I do? =

When the bar is visible, its height is available as the CSS variable `--dabar-height` on the `html` element. Add `top: var(--dabar-height, 0px);` to your sticky header. The variable is set to `0px` once a visitor closes the bar.

= The sticky option does not work. =

Sticky positioning is blocked when a parent element uses `overflow: hidden`. Check your theme or custom CSS for `overflow` on `html`, `body` or wrapper elements.

= When does a closed bar show again? =

With "Keep closed for" set to 0, the bar comes back in the next browser session. Otherwise it stays closed for the number of days you choose. Changing the messages always shows the bar again to everyone.

= Can I control where the bar appears with code? =

Use the `dabar_should_display` filter:

`add_filter( 'dabar_should_display', function ( $display ) { return is_page( 'contact' ) ? false : $display; } );`

= Can I style the bar with my own CSS? =

Yes. The bar uses the ID `#dabar` and the classes `.dabar__inner`, `.dabar__message` and `.dabar__close`. The colors and sizes are CSS custom properties (`--dabar-bg`, `--dabar-color`, `--dabar-close-color`, `--dabar-font-size`, `--dabar-padding`), so you can override them in your stylesheet.

== Screenshots ==

1. The announcement bar at the top of a WooCommerce store.
2. Content and placement settings.
3. Appearance, close button and display rules.

== Changelog ==

= 1.0.0 =
* Initial release.
