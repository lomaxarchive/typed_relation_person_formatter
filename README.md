# Typed Relation Person Formatter

A Drupal module that provides a custom field formatter for [`typed_relation`](https://www.drupal.org/project/typed_relation) fields. It is designed for use with Person taxonomy terms and adds support for:

- Displaying a **preferred name** (given + family) from the `field_person_preferred_name` field, with fallback to the term label
- Optionally **showing or hiding the relator** (relationship type)
- **Filtering displayed entries** by allowed relators via formatter settings
- Linking the person's name to their taxonomy term page

## Requirements

- Drupal 10 or 11
- [Typed Relation](https://www.drupal.org/project/typed_relation) module

## Installation

Install via Composer:

```bash
composer require lomaxarchive/typed_relation_person_formatter
```

Then enable the module:

```bash
drush en typed_relation_person_formatter
```

## Usage

1. Navigate to **Manage display** for a content type that has a `typed_relation` field.
2. Select **Typed Relation Person Formatter** as the formatter for that field.
3. In the formatter settings you can:
   - Toggle **Show relationship type (relator)**
   - Select which **relators** are allowed to appear (leave all unchecked to show all)

## Configuration

The formatter reads relator options from the `node.islandora_object.field_linked_agent` field config by default. If you use a different field, you may need to adjust `getRelatorOptions()` in `TypedRelationPersonFormatter.php`.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE) for details.
