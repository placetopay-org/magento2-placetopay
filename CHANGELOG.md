# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.7.6] - 2020-12-18

### Added
- Support to multiples stores in the same magento instance

### Fixed
- Get return url from current store for multiples stores
- Show current notification url on admin panel for multiples stores configuration

## [1.7.5] - 2020-12-17

### Added
- Custom placetopay logger

### Fixed
- Error when returning to view from checkout

## [1.7.4] - 2020-11-11

### Changed
- Brand name in views.

## [1.7.3] - 2020-09-14

### Added
- Support to php 7.4
- Costa Rica to Countries list

### Changed
- Payment gateway production endpoint

## [1.7.2] - 2020-08-14

### Added
- Success custom page.

### Fixed
- Return page bug for magento 2.0 / 2.1 / 2.2

## [1.7.1] - 2020-08-12

### Added
- Support magento 2.0 / 2.1 / 2.2
- Optional parameters in redirection request method.

## [1.7.0] - 2020-06-17

### Changed
- Using magento success info block instead of overriding success default page.
- Text translations for failure texts and others.

### Added
- Failure custom page.
- Checkbox for terms and conditions in a checkout.

## [1.6.2] - 2020-05-29

### Changed
- Merged list of taxes by key / value.

## [1.6.1] - 2020-02-26

### Added
- Add email after payment success option.

### Changed
- Apply php-cs-fixer.
- Changed payment method icons list.

## [1.5.3] - 2020-01-21

### Changed
- Set hyperlink to brand image in the frontend.

## [1.5.2] - 2020-01-21

### Changed
- Refactor string in cron log.

### Fixed
- Refactor parse person from address.

## [1.5.1] - 2020-01-20

### Changed
- Add tax parsing for different countries.

## [1.5.0] - 2020-01-20

### Changed
- Add failure, success, pending redirect views to checkout.

### Fixed
- Remove mobile from request.

## [1.2.1] - 2019-12-13

### Changed
- Add minimum stable version to composer

## [1.2.0] - 2019-12-04

### Fixed
- Stable php and magento 2 version in Dockerfile

### Changed
- Add support to php versions >= 7.1

## [1.1.2] - 2019-11-21

### Fixed
- Bug in checkout payment view
- Remove logger in payment checkout method.

### Changed
- Updated dependency redirection from 0.6.1 to 1.0.0
- Update translation changing P2P -> message

## [1.0.0] - 2019-11-20

### Added
- First release

[Unreleased]: https://github.com/placetopay-org/magento2-placetopay/compare/1.7.6...HEAD
[1.7.6]: https://github.com/placetopay-org/magento2-placetopay/compare/1.7.5...1.7.6
[1.7.5]: https://github.com/placetopay-org/magento2-placetopay/compare/1.7.4...1.7.5
[1.7.4]: https://github.com/placetopay-org/magento2-placetopay/compare/1.7.3...1.7.4
[1.7.3]: https://github.com/placetopay-org/magento2-placetopay/compare/1.7.2...1.7.3
[1.7.2]: https://github.com/placetopay-org/magento2-placetopay/compare/1.7.1...1.7.2
[1.7.1]: https://github.com/placetopay-org/magento2-placetopay/compare/1.7.0...1.7.1
[1.7.0]: https://github.com/placetopay-org/magento2-placetopay/compare/v1.6.2...1.7.0
[1.6.2]: https://github.com/placetopay-org/magento2-placetopay/compare/v1.6.1...v1.6.2
[1.6.1]: https://github.com/placetopay-org/magento2-placetopay/compare/v1.5.3...v1.6.1
[1.5.3]: https://github.com/placetopay-org/magento2-placetopay/compare/v1.5.2...v1.5.3
[1.5.2]: https://github.com/placetopay-org/magento2-placetopay/compare/v1.5.1...v1.5.2
[1.5.1]: https://github.com/placetopay-org/magento2-placetopay/compare/v1.5.0...v1.5.1
[1.5.0]: https://github.com/placetopay-org/magento2-placetopay/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/placetopay-org/magento2-placetopay/compare/v1.2.1...v1.4.0
[1.2.1]: https://github.com/placetopay-org/magento2-placetopay/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/placetopay-org/magento2-placetopay/compare/v1.1.2...v1.2.0
[1.1.2]: https://github.com/placetopay-org/magento2-placetopay/compare/v1.0.0...v1.1.2
[1.0.0]: https://github.com/placetopay-org/magento2-placetopay/releases/tag/v1.0.0
