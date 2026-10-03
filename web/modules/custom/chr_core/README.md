# CHR Core

CHR Core (`chr_core`) is the custom module for the Court House Regular site, a directory where attorneys and law firms find appearance attorneys by court. It holds the site-specific code that doesn't belong in a contributed module: the courthouse search filter, field plugins for the courthouse taxonomy, member profile pages, invite settings, and a few one-off data migration tools left over from the Drupal 7 upgrade.

## Requirements

The module requires Drupal 10 or 11.2+, plus the core Taxonomy and Views modules and the contributed [Webform](https://www.drupal.org/project/webform) and [Simple hierarchical select (SHS)](https://www.drupal.org/project/shs) modules. Composer and Drupal's module installer resolve these as normal.

Most of the module's features expect the courts vocabulary, `vocabulary_4`, to exist. Its terms are organised in four levels: Jurisdiction → Courthouse → Court → Division. The member pages' templates include the `chr_theme:user` component, so they only render correctly under the site's own theme.

## Installation

Install it like any custom module, with `drush en chr_core` or from the Extend page, then run `drush cr`. Once it's enabled, assign the permissions described below to the right roles at `/admin/people/permissions`.

## Features

### Courthouse search filter

The "Custom View Filter" block (`custom_views_filter`) renders `CustomViewsFilterForm`, the search form on the `/charter-search` page. It shows four cascading selects for Jurisdiction, Courthouse, Court and Division. Choosing a value reloads the next level over AJAX, so each level only offers children of the term chosen above it. Levels without a parent selection are disabled.

The Jurisdiction select only lists State Courts, Federal Courts, District of Columbia Courts and Territorial Courts, in that order, whatever their order in the vocabulary. Any other top-level term is left out. To change the list, edit `JURISDICTION_OPTIONS` in the form class.

Pressing Apply redirects to `/charter-search/{tid}`, where `{tid}` is the deepest level selected, or to `/charter-search/all` when nothing is selected. The view uses that term as its contextual filter. When the results page loads, the form rebuilds every select from that term's ancestors, so the search options stay selected. The form reads the term from the Views route parameter (`{arg_0}` or a named placeholder, via the route's `_view_argument_map`). It falls back to a plain `{tid}` parameter on other routes.

A Reset button appears once anything is selected and returns to `/charter-search/all` with every select cleared. The block is never cached, so the form always matches the current URL.

### Courthouse field plugins

The **Courthouse hierarchy** formatter (`courthouse_default`) displays an entity reference to a courts term as its full chain, for example State Courts › Alpha Courthouse › Superior Court. It renders as a list where ancestors have the `shs-parent` class and the referenced term has `shs-term-selected`. Turning on the "linked" setting makes each term link to its page.

The **Courthouse Simple Hierarchical Select** widget (`courthouse_shs`) is the editing counterpart. It's only partly ported from Drupal 7; see Known issues before using it.

### Views integration

`hook_views_data_alter()` registers an extra filter on taxonomy term IDs, labelled "Counties, Courthouses and Courts (CHR: Simple hierarchical select)". It uses the `chr_core_filter_term_node_tid` plugin, which extends SHS's taxonomy filter and defaults to `vocabulary_4` with the full hierarchy shown as an SHS widget.

The module also implements `hook_shs_my_field_js_settings_alter()`, which changes the SHS "any" label to "- Any -" and speeds up the widget animation for the `my_field` instance.

### Member pages

`ProfilePage` provides the account pages linked from a member's profile. Each one is themed by a template in `templates/`. The two invite pages embed their webforms, `invite_appearance_attorneys` and `invite_law_firms`.

| Path | Page |
| --- | --- |
| `/user/{user}/affiliate` | Affiliate Center (sales and click counters, currently placeholders) |
| `/user/{user}/bookmarks` | Bookmarks |
| `/user/{user}/friends` | All Colleagues |
| `/user/{user}/invite` | Invite Appearance Attorneys |
| `/user/{user}/invites/invite_by_email` | Invite Law Firms |

### Settings

Two settings forms live under Configuration → Court House Regular settings. Both store a default e-mail subject and body. `/admin/config/courthouseregular` saves to `chr_core.adminsettings`, and `/admin/config/courthouseregular/config` saves to `chr_core.adminsettingsinvite`. The invite form comes pre-filled with a message that uses the `[yourname]`, `[home_link]` and `[registration_link]` tokens.

### Permissions

The module defines two permissions in `chr_core.permissions.yml`. "Invite Appearance Attorneys" (`invite new users`) controls sending invites from the site. "Administer Court House Regular" (`administer courthouseregular`) is marked as restricted. Both keep their Drupal 7 machine names, so migrated role assignments still apply. The routes don't check them yet: the settings pages use `access administration pages`, and the member pages use the authenticated role or `access content`.

## Migration tools

These tools moved data across during the Drupal 7 upgrade. They aren't needed for day-to-day running of the site.

The **Sync Missing Profile Information** form at `/admin/config/courthouseregular/sync-missing-profile-info` runs a batch over every profile in the old site's database. That database must be defined as the `migrate` connection in `settings.php`. The batch copies data the migration missed onto each member's `recieve_assignments` profile. It's currently set to copy profile images; the phone and fax steps in `DataSyncOperations::processProfile()` are commented out.

`chr_state_report.php` and `chr_state_reparent.php` are Drush scripts that clean up the courts vocabulary. They move US state terms that ended up at the top level back under State Courts. Run the report first with `drush scr chr_state_report.php`. Copy the confirmed term IDs into the reparent script, then run `drush scr chr_state_reparent.php` to preview the changes. Add `-- apply` to save them. The reparent script skips any term that isn't in `vocabulary_4` or isn't currently top-level.

## Testing

The module has kernel and functional tests under `tests/`, all in the `chr_core` group. They cover the search form, the formatter, the hooks and Views filter, the permissions, the settings forms and route access. A hidden test module, `chr_core_test`, provides a stand-in for the charter-search view's route so the block can be tested without the real view. With `SIMPLETEST_DB` and `SIMPLETEST_BASE_URL` set, run the tests from the project root:

```
ddev exec vendor/bin/phpunit -c web/core web/modules/custom/chr_core
```

## Known issues

The `courthouse_shs` widget still calls Drupal 7 SHS functions (`_shs_create_hash()`, `shs_term_get_children()`, `_shs_entityreference_views_get_vocabularies()`) and attaches the `shs/shs` library. None of these exist in the current SHS module, so any form using the widget will error until it's rewritten on top of SHS's own widget.

The migration batch switches the active database connection to `migrate` and never switches back. Anything later in the same request then runs against the old database. It also has no automated tests, because it needs that database.

The member pages are open to any logged-in user for any `{user}` ID. They don't yet check that the visitor is viewing their own account.

The custom Views filter is registered on `taxonomy_term_field_data`, so it only applies to term-based views. To filter attorneys by courthouse, it needs to be attached to the courthouse field's table on the user or profile entity instead.
