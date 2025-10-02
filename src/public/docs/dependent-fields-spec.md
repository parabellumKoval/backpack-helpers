# Specification: Dependent Fields Script for Laravel Backpack 4.1

## Goal
Implement a **JavaScript (jQuery) script** for Laravel Backpack 4.1 that allows one field to depend on another, regardless of their position on the page.  

- Example:  
  - `field_1` and `field_2` are dropdown fields.  
  - The **available options in `field_2`** depend on the currently selected value of `field_1`.  
  - When `field_1` changes, the options in `field_2` should reload/update.  
  - `field_2` itself can be a driver for `field_3`, and so on (chained dependencies).

---

## Requirements

### General
1. The script must be placed in:
   ```
   public/packages/backpack/helpers/js/dependent-fields.js
   ```
2. It must support **static maps** and **AJAX loading** of dependent options.
3. It must handle:
   - **Existing values** on edit pages (pre-fill with current selections).
   - **Dynamic fields** added via Backpack UI (e.g., `repeatable`, `conditional_fields`, `repeatable_conditional`).
4. It must **initialize correctly** using Backpack’s `data-init-function` mechanism.

---

### Field Configuration

#### Satellite (Dependent) Example
```php
CRUD::addField([
    'name'  => 'block_type',
    'label' => 'Block Type',
    'type'  => 'select_from_array',
    'attributes' => [
        // Initialization
        'data-init-function' => 'bpFieldInitDependentOptions',

        // Dependency source (field name or driver attribute)
        'data-depends-on' => 'page',

        // Scope (where to look for driver field)
        // - "auto": search the whole form
        // - querySelector string: find closest parent
        'data-dep-scope' => '.form-group',

        // Static dependency map
        'data-dep-map' => json_encode([
            'home'    => ['hero' => 'Hero Banner', 'grid' => 'Grid', 'cta' => 'CTA Block'],
            'product' => ['buybox' => 'Buy', 'specs' => 'Specs', 'reviews' => 'Reviews'],
            'blog'    => ['teaser' => 'Teaser', 'toc' => 'Table of Contents'],
            '[category,brand]' => ['teaser' => 'Teaser', 'toc' => 'Table of Contents'],
            '*'       => [] // default
        ]),

        // URL fetching (alternative to static map)
        // {value} will be replaced with active driver value
        'data-dep-url' => url('admin/deps/block-types/{value}'),
    ],
]);
```

#### Driver Example
```php
CRUD::addField([
    'name'  => 'page',
    'label' => 'Page Type',
    'type'  => 'select_from_array',
    'attributes' => [
        'data-dep-source' => 'page'
    ]
]);
```

---

### Supported Field Types
Both drivers and satellites can be of these Backpack field types:

- `select_from_array`  
- `select_and_order`  
- `select_multiple`  
- `select`  
- `select2_from_ajax_multiple`  
- `select2_from_ajax`  
- `select2_from_array`  
- `select2_grouped`  
- `select2_multiple`  
- `select2_nested`  
- `select2`  

---

### Initialization Rules
- Backpack fields that require initialization use `data-init-function`.  
  The script must register a function:
  ```js
  function bpFieldInitDependentOptions(element) { ... }
  ```
- For **select2** fields:
  - Initialized fields have the class `.select2-hidden-accessible`.
  - If present, **do not re-initialize** select2 unnecessarily.

---

### Dynamic Fields Support
- Fields may appear dynamically (e.g., inside `repeatable` or `conditional_fields`).  
- The script must re-bind dependencies **each time new fields are added**.  
- Dependencies should work even if the driver is added **after** the satellite.

---

### Behavior Details
1. **Satellite refresh:**
   - On driver change → update satellite options:
     - From `data-dep-map` (static mapping) **OR**
     - From `data-dep-url` (AJAX fetch).
2. **Pre-population:**
   - When editing an entry, if the satellite already has a value, ensure it is **preserved** and **visible** in the updated options.
3. **Chaining:**
   - A field may be **both** a driver and a satellite (cascading dependencies).

---

## Deliverables
1. A script at:
   ```
   public/packages/backpack/helpers/js/dependent-fields.js
   ```
2. Implementation of:
   - `bpFieldInitDependentOptions`
   - Internal helpers for:
     - Finding driver fields by `data-depends-on` and `data-dep-scope`
     - Resolving dependencies (`map` or `url`)
     - Refreshing options
     - Handling pre-selected values
3. Must be compatible with Backpack 4.1 form lifecycle.