


<a href="https://support.saleslayer.com"><img src="https://saleslayer.com/assets/images/logo.svg" alt="Sales Layer WooCommerce" width="460"></a>

# Sales Layer PrestaShop plugin

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.3-8892BF.svg?style=flat-square)](https://php.net/) [![Minimum Prestashop Version](https://img.shields.io/badge/Prestashop-%3E%3D%208.0.0-AA92BF.svg?style=flat-square)](https://github.com/PrestaShop/PrestaShop) [![GitHub release](https://img.shields.io/github/v/release/saleslayer/Sales_Layer_Prestashop)](https://github.com/saleslayer/Sales_Layer_Prestashop)

PrestaShop plugin that allows you to easily synchronize your Sales Layer catalogue information with PrestaShop online stores.
[Sales Layer - Global Leading PIM][saleslayer-home]

## Download

[Download latest module version][latest-release-download]
Check out the latest changes at our [Changelog][changelog-md]

## Important notes & documentation
Check the [full module documentation][sc-connector-about] available at our support center.
In some cases, a Sales Layer PIM platform account might be needed to access the documentation.

## Quick Start

#### Upload & Install in your PrestaShop online store the downloaded version of this module as a manual module installation.
* After installed correctly, you will see a new element in your navigation bar named -sales layer-. Go -how to use- menu for further information on module configuration.
* Follow the - How to synchronize by cron - guidelines to enable automatic synchronization.

#### Under channels tab, into your Sales Layer PIM account, create a Prestashop connector and assign the fields.	
* The plugin needs the connector ID code and the private key, you will find them in the connector details inside Sales Layer, after saving your new connector.

#### Add the connector credencials in Prestashop.
* Go to Sales Layer >> Add New Connector. Set the connector id and secret key and press Save Connector.
* Finally, In Sales Layer >> Connectors >> The connector you created. In the Autosync select, choose the frequency of hours in which the synchronization must be performed, in order to synchronize products automatically.
Wait a few minutes and enter the template Sales Layer >> How to use , to verify that we have everything necessary for working.
    
  ![Synchronizing](images/image5.png)
  
## Requirements for synchronization

* Active cronjobs.
* Define the fields relationship in the Sales Layer Prestashop connector:
	* Most Prestashop fields are already defined in each section, extra fields for products are converted to features and extra fields for variants are converted to attributes in order to synchronize.
* Inside categories, products and variants there will be attributes; Sales Layer Product Identification, Sales Layer Product Company Identification and Sales Layer Format Identification, don't modify or delete these attributes or its values, otherwise, the products will be created again as new ones in the next synchronization.
* Inside the connector configuration you can set different values before the synchronization in the different tabs, such as:
	* Auto-synchronization and preferred hour for it.
	* The stores where the information will be updated.
	* Overwrite stock status (stock will be updated only at creation of new items)

 		
## Version Guidance

| Version        | Status | Branch | Prestashop version     | Recommended Configuration                | Changelog                         |
|----------------|--------|--------|------------------------|------------------------------------------|-----------------------------------|
| [1.5.2][1.5.2] | EOL   |[1.X]        | \>= 1.6.1.6, < 1.7.6.x | Prestashop 1.7.6.1 / PHP 7.3 / Apache2.4   | [changelog-1.x][changelog-1.x-md]
| [1.5.3][1.5.3] | Stable | [1.X]      | \>= 1.6.1.6, < 1.7.8.x | Prestashop 1.7.8.2 / PHP 7.3 / Apache2.4   | [changelog-1.x][changelog-1.x-md]
| [1.6.0][1.6.0] | Latest | [1.X]      | \>= 1.7.8.x            | Prestashop 1.7.8.10 / PHP 7.4  / Apache2.4 | [changelog-1.x][changelog-1.x-md] |
| [2.0.0][2.0.0] | Stable | [2.X]        | \>= 1.7.8.x,  8.0.x    | Prestashop 8.0.4 / PHP 8.1  / Apache2.4 | [changelog-2.x][changelog-2.x-md] |
| [2.1.0][2.1.0] | Latest | [2.X]        | \>= 8.1.x             | Prestashop 8.1.2 / PHP 8.1  / Apache2.4 | [changelog-2.x][changelog-2.x-md] |


> **Warning** 
> Prestashop releases frequently new software versions fixing bugs and adding new functionality. Some of this versions could be in conflict with this plugin. We highly encourage you to set the prestashop configuration recommended in the guidance table for running correctly this plugin.

> **Note** 
> See [System requirements for PrestaShop 8][prestashop8-system-requirements] and [System requirements for PrestaShop 1.7][prestashop1.7-system-requirements] for best setting up on your system environment.

[saleslayer-home]: https://www.saleslayer.com
[latest-release-download]: https://github.com/saleslayer/Sales_Layer_Prestashop/releases/latest
[changelog-md]: ./CHANGELOG.md
[sc-connector-about]: https://support.saleslayer.com/prestashop/important-notes-about-connector
[prestashop8-system-requirements]: https://devdocs.prestashop-project.org/8/basics/installation/system-requirements/
[prestashop1.7-system-requirements]: https://devdocs.prestashop-project.org/1.7/basics/installation/system-requirements/
[1.5.2]:https://github.com/saleslayer/Sales_Layer_Prestashop/releases/tag/1.5.2-stable
[1.5.3]:https://github.com/saleslayer/Sales_Layer_Prestashop/releases/tag/1.5.3-stable
[1.6.0]:https://github.com/saleslayer/Sales_Layer_Prestashop/releases/tag/1.6.0-stable
[2.0.0]:https://github.com/saleslayer/Sales_Layer_Prestashop/releases/tag/2.0.05.3-stable
[2.1.0]:https://github.com/saleslayer/Sales_Layer_Prestashop/releases/tag/2.1.0-stable
[1.X]:https://github.com/saleslayer/Sales_Layer_Prestashop/tree/1.x
[2.X]:https://github.com/saleslayer/Sales_Layer_Prestashop/tree/2.x
[changelog-1.x-md]: https://github.com/saleslayer/Sales_Layer_Prestashop/blob/1.x/CHANGELOG.md
[changelog-2.x-md]: https://github.com/saleslayer/Sales_Layer_Prestashop/blob/2.x/CHANGELOG.md