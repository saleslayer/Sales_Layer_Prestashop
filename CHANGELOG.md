# Changelog

## [2.0.0] - 2023-06-09

### Added

- Prestashop 8.0.x compatibility.

### Changed

- Remove dependency on Prestashop cronjobs plugin.

### Tested

- PrestaShop 8.0.4 / PHP 8.1


## [1.5.3] - 2023-01-17

### Changed

- Minor fixes in multi-processes.
- Stability and performance improvements.

### Tested

- PrestaShop 1.7.8.0 / PHP 7.3

## [1.5.2] - 2022-10-28

### Changed

- Compatibility fix with older version for Prestashop 1.7.7.x >=
- Change plugin tables engine to MyISAM for plugin installed before 1.4.x versions for better performance.

### Tested

- PrestaShop 1.7.7.6 / PHP 7.3

## [1.5.1] - 2022-10-28

### Added

- Better compatibility with 1.7.8.x Prestashop.
- Parent category select for first category: Shop category id if not exist-> PS_HOME_CATEGORY if not exist -> PS_ROOT_CATEGORY

### Changed

- Massive stock update fix.
- Category problem that has been caused by not maintaining the order of processing in categories.
- Small fixes.

### Tested

- PrestaShop 1.7.8.0 / PHP 7.3

## [1.5.0] - 2021-12-17

### Added

- Speed improvements.
- Asynchronous stock update.
- Asynchronous image preload.
- Data comparison with data hash.
- Balancer.
- Work in multi-processes.
- Variants of products are synchronized with products.

### Changed

- Small fixes.

### Tested

- PrestaShop 1.5.0 / PHP 7.3

## [1.4.26] - 2021-10-14

### Added

- Product creation only in stores configured in the connector.
- Creation of features in all stores. (Bug fix in multistore).
- Better control of product deallocation - categories.

### Changed

- Fix variants with same attributes are not created.

### Tested

- PrestaShop 1.4.24 / PHP 7.3

## [1.4.25] - 2021-10-14

### Added

- php 7.3 compatibility improvement and reduce notifications of undeclared variables.
- Improvement in messages of unlinked products in pack.

### Changed

- Fix changing product type from pack configuration.
- Fix in delete accessories.

## [1.4.24] - 2021-10-14

### Changed

- Fix of the compression on feature values.
- Fix of the minimum quantity field.
- Fix of quantity field, permission to set to 0.