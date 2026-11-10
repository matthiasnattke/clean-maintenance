=== Clean Maintenance ===
Contributors: matthiasnattke
Tags: maintenance, maintenance mode, multisite, coming soon, rest api
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.6.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lean maintenance mode for single sites and multisite networks: your own page, scheduling, HTTP 503 and an optional REST API lock.

== Description ==

Clean Maintenance shows visitors a calm maintenance page while you and everyone with the right role keep working on the site as usual. Search engines get HTTP status 503 with a "Retry-After" header, so they know the break is only temporary.

It does one thing and stays out of your way: no page builder, no contact forms, no tracking, no upsell.

= Features =

* **Mode:** off, on, or scheduled with start and end picked from a calendar (in the site's time zone).
* **Your own page:** any WordPress page – even a draft or a private page – becomes the maintenance page, shown in the style of your theme without header and footer, with background image and colour.
* **Classic design:** a simple standalone page with heading, text and background, independent of the theme.
* **Exceptions:** one slug or IP address per line, comments with #. The dashboard, login and robots.txt always stay reachable.
* **Roles:** decide who still sees the site and who may use the dashboard; everyone else is redirected. Network administrators can never lock themselves out.
* **REST API lock:** lock the REST API for visitors who are not logged in – always or only during maintenance – with a list of namespaces that stay public (for example oEmbed).
* **Search engines:** HTTP 503 with Retry-After, optionally let bots through or send "noindex".
* **Small comforts:** a red "Maintenance mode active" notice in the admin bar with a switch to turn it off, and a preview of the maintenance page.
* **Multisite:** every site has its own settings. The network admin shows all sites with their state, turns them on and off in bulk and adds a dashboard widget.
* **Translation ready:** English and German included.

= Privacy =

Clean Maintenance does not collect, store or send any personal data and does not contact external services. It sets no cookies.

== Installation ==

1. Upload the folder `clean-maintenance` to `/wp-content/plugins/`, or install the ZIP file via "Plugins → Add New → Upload Plugin".
2. Activate the plugin – in a multisite network preferably network-wide.
3. Go to "Settings → Maintenance mode", choose your maintenance page and turn the mode on.

== Frequently Asked Questions ==

= Can I lock myself out? =

No. Network administrators always have access, and the dashboard and login stay reachable.

= Does maintenance mode hurt my ranking? =

The maintenance page answers with HTTP 503 and a Retry-After header. This tells search engines that the break is temporary.

= Do the block editor and Jetpack still work when the REST API is locked? =

Yes. Only requests without a login are locked, and namespaces such as `jetpack` and `wpcom` are on the list of exceptions by default.

= How do I show my own design? =

Create a page with the block editor, ideally as a draft or private page, and choose it as the maintenance page. It is shown with your theme's styles but without header and footer.

= Is there a hook for developers? =

The action `clean_maintenance_render` fires right before the maintenance page is printed. The filter `clean_maintenance_site_name` changes the name shown on the simple default notice.

== Screenshots ==

1. Settings of a site, ordered by task: status, appearance, access, search engines and comfort.
2. Classic design with a background image, coloured heading and text and a link to the dashboard.
3. Light text on a dark background image.
4. A holiday notice with emphasised text.
5. Bold colours and a construction motif.
6. Calm colours, no link.
7. Plain and simple – one sentence is enough.
8. Network dashboard widget: sites in maintenance, reachable and planned at a glance, each with a switch.
9. Network overview: all sites with state, period and maintenance page, switch one or many at once.

== Changelog ==

= 0.6.1 =
* Every release is now checked against the WordPress.org plugin guidelines and with Plugin Check before it is published.
* No changes to the plugin's behaviour.

= 0.6.0 =
* New settings page: a status header with preview, cards ordered by task (status, appearance, access, search engines & API, comfort), section navigation, tiles for mode and design, switches, role chips and a live background swatch.
* Network overview redesigned: key figures, filter by state, a card per site with state, period and what it shows, quick switches and bulk actions.
* Network dashboard widget redesigned: key figures and a list of paused and planned sites with switches.
* Dependent fields also show and hide without JavaScript.
* Screenshots and example maintenance pages in the documentation.

= 0.5.0 =
* First public version.
* Maintenance mode off, on or scheduled; maintenance page from WordPress or classic design; exceptions for slugs and IP addresses; roles for site and dashboard access; HTTP 503 with Retry-After.
* Optional REST API lock for visitors who are not logged in.
* Multisite: overview of all sites, bulk switching and a network dashboard widget.
* English as base language, German translation included.
