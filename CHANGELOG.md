# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/placetopay-org/magento2-placetopay/tree/development)

## [1.6.3] - 2020-08-11

### Added
- Support magento 2.0 / 2.1 / 2.2
- Optional parameters in redirection request method.

## [1.6.2] - 2020-05-29

### Updated
- Merged list of taxes by key / value.

## [1.6.1] - 2020-02-26

### Added
- Add email after payment success option.

### Updated
- Apply php-cs-fixer.
- Changed payment method icons list.

## [1.5.3] - 2020-01-21

### Updated
- Set hyperlink to brand image in the frontend.

## [1.5.2] - 2020-01-21

### Updated
- Refactor string in cron log.

### Fixed
- Refactor parse person from address.

## [1.5.1] - 2020-01-20

### Updated
- Add tax parsing for different countries.

## [1.5.0] - 2020-01-20

### Updated
- Add failure, success, pending redirect views to checkout.

### Fixed
- Remove mobile from request.

## [1.2.1] - 2019-12-13

### Updated
- Add minimum stable version to composer

## [1.2.0] - 2019-12-04

### Fixed
- Stable php and magento 2 version in Dockerfile

### Updated
- Add support to php versions >= 7.1

## [1.1.2] - 2019-11-21

### Fixed
- Bug in checkout payment view
- Remove logger in payment checkout method.

### Updated
- Updated dependency redirection from 0.6.1 to 1.0.0
- Update translation changing P2P -> message
