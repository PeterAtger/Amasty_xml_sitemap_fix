<?php
/**
 * @category  Koene
 * @package   Koene_XmlSitemap
 * @author    Deniss Kolesins <info@scandiweb.com>
 * @copyright Copyright (c) 2020 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */

namespace Koene\XmlSitemap\Model;

use Amasty\XmlSitemap\Helper\Data;
use Amasty\XmlSitemap\Model\Hreflang\XmlTagsProviderFactory;
use Amasty\XmlSitemap\Model\Sitemap as ModelSitemap;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Gallery\ReadHandler as GalleryReadHandler;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\CatalogInventory\Helper\Stock;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Module\Manager;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface as XmlUrlInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class PlaceOrder
 * @package Koene\QuoteGraphQl\Plugin\Resolver
 */
class Sitemap extends ModelSitemap
{
    /**
     * @var Stock
     */
    private $stockHelper;

    /**
     * @var ProductCollectionFactory $_productCollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var Visibility $productVisibility
     */
    private $productVisibility;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Sitemap constructor.
     * @param Context $context
     * @param Registry $registry
     * @param File $ioFile
     * @param DirectoryList $dir
     * @param DateTime $dateTime
     * @param StoreManagerInterface $storeManager
     * @param Filesystem $filesystem
     * @param Visibility $productVisibility
     * @param Manager $moduleManager
     * @param ProductCollectionFactory $productCollectionFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param PageCollectionFactory $pageCollectionFactory
     * @param ManagerInterface $messageManager
     * @param Data $helper
     * @param Image $imageHelper
     * @param GalleryReadHandler $galleryReadHandler
     * @param Stock $stockHelper
     * @param Emulation $appEmulation
     * @param CategoryRepositoryInterface $categoryRepository
     * @param XmlTagsProviderFactory $hreflangTagsProviderFactory
     * @param Escaper $escaper
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        File $ioFile,
        DirectoryList $dir,
        DateTime $dateTime,
        StoreManagerInterface $storeManager,
        Filesystem $filesystem,
        Visibility $productVisibility,
        Manager $moduleManager,
        ProductCollectionFactory $productCollectionFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        PageCollectionFactory $pageCollectionFactory,
        ManagerInterface $messageManager,
        Data $helper,
        Image $imageHelper,
        GalleryReadHandler $galleryReadHandler,
        Stock $stockHelper,
        Emulation $appEmulation,
        CategoryRepositoryInterface $categoryRepository,
        XmlTagsProviderFactory $hreflangTagsProviderFactory,
        Escaper $escaper,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->stockHelper = $stockHelper;
        $this->productVisibility = $productVisibility;
        $this->storeManager = $storeManager;

        parent::__construct(
            $context,
            $registry,
            $ioFile,
            $dir,
            $dateTime,
            $storeManager,
            $filesystem,
            $productVisibility,
            $moduleManager,
            $productCollectionFactory,
            $categoryCollectionFactory,
            $pageCollectionFactory,
            $messageManager,
            $helper,
            $imageHelper,
            $galleryReadHandler,
            $stockHelper,
            $appEmulation,
            $categoryRepository,
            $hreflangTagsProviderFactory,
            $escaper,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * @param $product
     * @return string
     */
    public function getProductUrl($product)
    {
        $productUrl = 'product/' . $product->getUrlKey();

        return $productUrl;
    }

    /**
     * @return Collection
     */
    public function getProductCollection()
    {
        /** @var Collection $productCollection */
        $productCollection = $this->productCollectionFactory->create()
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->setVisibility($this->productVisibility->getVisibleInSiteIds())
            ->setStoreId($this->getStoreId())
            ->addUrlRewrite()
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('url_key')
            ->setPageSize(self::PAGE_SIZE);

        if ($this->getExcludeOutOfStock()) {
            $this->stockHelper->addInStockFilterToCollection($productCollection);
        }
        $this->excludeProductType($productCollection);

        return $productCollection;
    }

    /**
     * @param Collection $productCollection
     */
    private function excludeProductType(Collection $productCollection)
    {
        if ($this->getExcludeProductType()) {
            $productCollection->addAttributeToFilter(
                'type_id',
                ['nin' => $this->getExcludeProductType()]
            );
        }
    }

    protected function _getStoreBaseUrl($type = \Magento\Framework\UrlInterface::URL_TYPE_LINK)
    {
        $store = $this->storeManager->getStore($this->getStoreId());
        $isSecure = $store->isUrlSecure();

        $url = rtrim($store->getBaseUrl($type, $isSecure), '/') . '/';

        $parentFunction = debug_backtrace()[1];
        if ($parentFunction['function'] == 'generateCategories') {
            $url .= 'category/';
        }

        return $url;
    }

}
