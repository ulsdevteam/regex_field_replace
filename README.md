# Regex Field Replace

A Drupal module that provides a [Views Bulk Operations (VBO)](https://www.drupal.org/project/views_bulk_operations) Action for applying a regular expression find/replace to a specified field across a bulk selection of entities.

---

## Requirements

- Drupal 10 or 11
- [Views Bulk Operations (VBO)](https://www.drupal.org/project/views_bulk_operations) ^4

---

## Installation

1. Place the module in your Drupal installation's `modules/custom/` directory:
   ```
   modules/custom/regex_field_replace/
   ```
2. Enable the module:
   ```bash
   drush en regex_field_replace
   ```
   Or enable it via **Extend** (`/admin/modules`) in the Drupal admin UI.

---

## Configuration

No global configuration is required. The action is self-contained and configured at the point of use within a VBO-enabled View.

### Adding the action to a View

1. Navigate to **Structure → Views** and edit (or create) a View that lists the entities you want to update.
2. Add the **"Bulk operations"** field to the View (under the **Fields** section).
3. In the Bulk operations field settings, enable **"Regex field replace"** from the list of available actions.
4. Save the View.

---

## Usage

1. Open the View and select the entities you wish to update using the bulk-select checkboxes.
2. Choose **"Regex field replace"** from the action dropdown and click **Apply**.
3. Fill in the three-field configuration form:

   | Field | Description |
   |---|---|
   | **Field name** | Machine name of the entity field to update (e.g. `title`, `field_subtitle`). |
   | **Regex pattern** | A PHP `preg_replace()`-compatible pattern, **including delimiters** (e.g. `/^(\w+)-(\w+)$/`). Flags such as `i` (case-insensitive) or `m` (multiline) may be appended after the closing delimiter. |
   | **Replacement** | The replacement string. Use `$1`, `$2`, … to reference capture groups from the pattern. May be empty to delete matched text. |

4. Click **Apply** to execute. Entities whose field value does not match the pattern are silently skipped — no changes are written for those entities.

---

## Examples

### Swap a hyphen-delimited ID and index

Transform titles of the form `{id}-{index}` (e.g. `abc-42`) into `{index},{id}` (e.g. `42,abc`):

| Setting | Value |
|---|---|
| Field name | `title` |
| Regex pattern | `/^([^-]+)-([^-]+)$/` |
| Replacement | `$2,$1` |

### Strip a prefix from a custom text field

Remove a leading `DRAFT: ` prefix from `field_subtitle`:

| Setting | Value |
|---|---|
| Field name | `field_subtitle` |
| Regex pattern | `/^DRAFT:\s*/i` |
| Replacement | *(empty)* |

### Normalise a URL domain across body fields

Replace `http://old-domain.com` with `https://new-domain.com` in the `body` field:

| Setting | Value |
|---|---|
| Field name | `body` |
| Regex pattern | `/https?:\/\/old-domain\.com/` |
| Replacement | `https://new-domain.com` |

---

## Behaviour notes

- **Multi-value fields** — all delta values on a field are processed independently; each item is replaced (or skipped) on its own merits.
- **No match = no write** — if `preg_replace()` returns the original string unchanged, the entity is not saved, avoiding unnecessary database writes and revision noise.
- **Revision safety** — `$entity->save()` is called normally, so any revision-tracking configured on the entity type will create a new revision as expected.
- **Access control** — the action respects Drupal's entity `update` access; users cannot apply the action to entities they could not otherwise edit.
- **Pattern validation** — the configuration form validates the supplied pattern before execution; an invalid regex surfaces as a form error rather than a PHP warning at runtime.

---

## Limitations

- Operates on string-type field properties only (`value` property, or the first available property for non-standard field types).
- Does not currently support formatted text fields where the `format` property also needs updating.
- Only works with `FieldableEntityInterface` entities (i.e. not config entities).

---

## Maintainers

- University Library System, University of Pittsburgh

## License

Released under MIT and BSD.
