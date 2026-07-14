# Court House Regular / Find Lawyer By Courthouse

> *"Appearances Are Everything In This Business! ℠"*

A Drupal 10/11 multi-domain platform connecting attorneys for **court appearance coverage**: law firms post appearance assignments at specific courthouses, and local appearance attorneys pick them up. One codebase and one database serve two public-facing sites via the [Domain](https://www.drupal.org/project/domain) module suite.

## Sites

| Domain | Role |
|---|---|
| `www.courthouseregular.com` | Default domain — Court House Regular |
| `findlawyerbycourthouse.com` | Affiliate site — Find Lawyer By Court House |
| `court-house-regular-drupal11.ddev.site` | Local development (ddev) |

Content, users, and configuration are shared. Per-node visibility is controlled by **Domain Access** fields (`field_domain_access` / `field_domain_all_affiliates`) present on all content types and on user accounts. `domain_config` + `domain_config_ui` allow per-domain configuration overrides; `domain_alias` handles hostname variants and environment mapping.

## What the site does

- **Attorney/firm directory** — a `virtual_law_firm` content type ("Create your virtual law firm") and node-based `profile` content tagged against courthouse taxonomies, plus `profile` module profiles per subscription type.
- **Courthouse geography** — the heart of the data model. Taxonomies for **Federal District Courthouses**, **Federal Bankruptcy Courthouses**, **State and Territorial Courthouses**, EOIR (immigration courts), ODAR (Social Security hearing offices), plus per-state vocabularies (Arizona, California, Georgia, Illinois, Nebraska, Nevada, Pennsylvania, Rhode Island, South Carolina, Tennessee, Texas, Utah, Virginia, Washington), Cities, Counties, and States & Territories.
- **Subscription tiers** — roles and profile types distinguish **"Regular"** subscribers (receive *and* place assignments), **"Firm"** subscribers (place assignments only), and **Charter Subscribers**, with dedicated search views (`charter_search`, `receive_search`) and a Charter Subscription menu.
- **Groups & community** — Organic Groups (`og`) powers group membership (`group` and `post` content types, members-overview view); `flag` provides Bookmark, Following, and Colleague relationships; forums and comments are enabled; `message`/`message_notify` handle notifications; `mass_contact` archives bulk mailings.
- **Lead capture** — webforms for Contact, **Invite Appearance Attorneys**, and **Invite Law Firms** (plus the standard webform template library).
- **Commerce (legacy)** — `product` and `uc_recurring_subscription` content types survive from the site's Ubercart past; no commerce module is currently enabled, so these are dormant/migrated content.

## Tech stack

- **Core**: Drupal 10/11, `standard` install profile. The content model (story, page/book, Garland-era blocks, OG, Ubercart types) shows a lineage migrated up from Drupal 6/7 via `migrate_drupal` / `migrate_plus` / `migrate_tools`.
- **Themes**: `chr_theme` (custom, default), `gin` (admin), with `basecore`, `bootstrap5`, `radix`, and `venture` also installed.
- **Custom modules**: `chr_core`, `basecore`, `riddler`.
- **Multi-domain**: `domain`, `domain_access`, `domain_alias`, `domain_config`, `domain_config_ui`.
- **Community/social**: `og`, `flag` (+ bookmark/count/follower), `message`, `message_notify`, `masquerade`, `profile`, `forum`.
- **Content & UX**: `webform`, `pathauto`, `easy_breadcrumb`, `admin_toolbar`, `ckeditor5`, `inline_entity_form`, `shs`, `taxonomy_manager`, `views_bulk_operations`, `twig_tweak`.
- **Ops & safety**: `backup_migrate` (daily schedule to private files), `config_ignore`, `captcha`/`image_captcha`, `security_review`, `upgrade_status`, `dblog`/`watchdog` view.
- **Login flow**: custom login paths registered with Domain (`/user/login`, `/user/password`); 403/404 pages configured in `system.site`.

## User roles

Administrator, Developer, Content editor, Evangelist, Charter Subscriber, Receive and Place Assignments Subscriber ("Regular"), Place Assignments Only Subscriber ("Firm"), Self, plus core anonymous/authenticated.

## Configuration management

- Config lives in `config/sync` (this export: ~910 objects).
- `config_ignore` (simple mode) excludes `system.performance`.
- `backup_migrate` runs a daily database backup schedule to the private files destination.
- Domain records are exported as `domain.record.*`; per-domain overrides (Domain 3.x) export as config collections.

## Local development

The site runs under **ddev** locally (`court-house-regular-drupal11.ddev.site`). Domain-sensitive drush/cron operations should pass `--uri=` explicitly, since CLI requests negotiate to the default domain.
