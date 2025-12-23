# CodGuard OpenCart 4.x Upgrade Guide

## Version 2.0.0 - OpenCart 4.x Compatible

### Major Changes from v1.x to v2.0

#### 1. **File Structure Changes**
- **OLD (OpenCart 3.x):**
  - `upload/admin/controller/extension/module/codguard.php`
  - `upload/catalog/controller/extension/module/codguard.php`

- **NEW (OpenCart 4.x):**
  - `admin/controller/module/codguard.php`
  - `catalog/controller/module/codguard.php`

The `/extension/` directory has been removed from the path structure.

#### 2. **Namespace Implementation**
All classes now use proper PHP namespaces:

**Admin Controller:**
```php
namespace Opencart\Admin\Controller\Extension\Codguard\Module;
class Codguard extends \Opencart\System\Engine\Controller
```

**Catalog Controller:**
```php
namespace Opencart\Catalog\Controller\Extension\Codguard\Module;
class Codguard extends \Opencart\System\Engine\Controller
```

#### 3. **Type Hints & Return Types**
All methods now use PHP 8.1+ type hints:
```php
public function install(): void
public function getStatistics(): array
public function getCustomerRating(string $email): ?float
```

#### 4. **Event System Changes**
Event registration updated for OpenCart 4.x format:
```php
$this->model_setting_event->addEvent([
    'code' => 'codguard_order_status_change',
    'description' => 'CodGuard order status change handler',
    'trigger' => 'catalog/model/checkout/order/addHistory/after',
    'action' => 'extension/codguard/module/codguard.eventOrderStatusChange',
    'status' => 1,
    'sort_order' => 0
]);
```

#### 5. **Database Character Set**
Updated to `utf8mb4`:
```sql
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
```

#### 6. **Extension Loading**
Payment methods loading updated:
```php
// OLD: $this->model_setting_extension->getInstalled('payment')
// NEW: $this->model_setting_extension->getExtensionsByType('payment')
```

#### 7. **URL Link Generation**
Removed third parameter (SSL flag) from `url->link()` calls:
```php
// OLD: $this->url->link('path', 'params', true)
// NEW: $this->url->link('path', 'params')
```

### Installation Instructions

1. **Backup your database** before installing
2. If you had v1.x installed, **uninstall it first** from Extensions > Installer
3. Upload `codguard-opencart-v2.0.0-oc4.ocmod.zip` via Extensions > Installer
4. Install the extension
5. Navigate to Extensions > Extensions > Modules > CodGuard
6. Configure your API credentials (Shop ID, Public Key, Private Key)
7. Enable the extension

### Compatibility

- **OpenCart Version:** 4.0.x or higher
- **PHP Version:** 8.1 or higher
- **Previous Version:** Not compatible with OpenCart 3.x (use v1.x for OpenCart 3.x)

### New Features in v2.0.0

- Full OpenCart 4.x compatibility
- PHP 8.1+ support with strict typing
- Improved event system integration
- Better database performance with utf8mb4

### File Listing

```
admin/
  controller/module/codguard.php
  model/module/codguard.php
  language/en-gb/module/codguard.php
  view/template/module/codguard.twig
catalog/
  controller/module/codguard.php
  model/module/codguard.php
  language/en-gb/module/codguard.php
install.json
```

### Notes

- All functionality from v1.x is preserved
- API endpoints remain unchanged
- Database tables are backward compatible
- Configuration settings are preserved during upgrade

---

**Package:** codguard-opencart-v2.0.0-oc4.ocmod.zip
**Release Date:** 2025-11-21
**License:** GPL v2 or later
