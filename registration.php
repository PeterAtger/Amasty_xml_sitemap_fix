<?php
/**
 * @category  Koene
 * @package   Koene_XmlSitemap
 * @author    Deniss Kolesins <info@scandiweb.com>
 * @copyright Copyright (c) 2020 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Koene_XmlSitemap',
    __DIR__
);
