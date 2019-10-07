placetopay redirection for magento2
============================================================

## Description ##
placetopay gateway payment available for Colombia

## Table of Contents

* [Installation](#installation)
* [Configuration](#configuration)

## Installation ##

Use composer package manager

```bash
composer require saulmoralespa/magento2-placetopay
```

Execute the commands

```bash
php bin/magento module:enable Saulmoralespa_PlaceToPay --clear-static-content
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy es_CO #on i18n
```
## Configuration ##

### 1. Enter the configuration menu of the payment method ###
![Enter the configuration menu of the payment method](https://4.bp.blogspot.com/-9OczydqYwUQ/XT32hzPJslI/AAAAAAAACws/3phTvB6A5_QFPa7OPyywUUZfp-LhxNikwCLcBGAs/s1600/screenshot.png)
