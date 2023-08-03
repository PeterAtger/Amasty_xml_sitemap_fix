<?php

/**
 * @category  Koene
 * @package   Koene_XmlSitemap
 * @author    Akims Goncars <info@scandiweb.com>
 * @copyright Copyright (c) 2020 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 **/
namespace Koene\XmlSitemap\Model\Hreflang;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Amasty\XmlSitemap\Model\Hreflang\GetCmsPageRelationFieldInterface;
use Amasty\XmlSitemap\Model\Hreflang\DataProviderInterface;
use Amasty\XmlSitemap\Model\Hreflang\XmlTagsProvider as SourceXmlTagsProvider;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class XmlTagsProvider
 * @package Koene\XmlSitemap\Model\Hreflang
 */
class XmlTagsProvider extends SourceXmlTagsProvider
{
    const ENTITY_PRODUCT = 'product';
    const ENTITY_CATEGORY = 'category';
    const ENTITY_CMS_PAGE = 'cms_page';

    /**
     * @var array[]
     */
    private $hreflangTags = [];

    /**
     * @var DataProviderInterface[]
     */
    private $hreflangProviders;

    /**
     * @var int
     */
    private $currentStoreId;

    /**
     * @var GetCmsPageRelationFieldInterface
     */
    private $getCmsPageRelationField;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * XmlTagsProvider constructor.
     * @param GetCmsPageRelationFieldInterface $getCmsPageRelationField
     * @param array $hreflangProviders
     * @param $currentStoreId
     */
    public function __construct(
        GetCmsPageRelationFieldInterface $getCmsPageRelationField,
        array $hreflangProviders,
        $currentStoreId,
        StoreManagerInterface $storeManager
    ) {
        $this->getCmsPageRelationField = $getCmsPageRelationField;
        $this->hreflangProviders = $hreflangProviders;
        $this->currentStoreId = $currentStoreId;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritdoc
     */
    public function getProductTagAsXml(AbstractModel $product)
    {
        $hreflangTags = $this->getTagsByEntity(self::ENTITY_PRODUCT, $product->getId());

        return $this->getTagsAsXml($hreflangTags, self::ENTITY_PRODUCT);
    }

    /**
     * @inheritdoc
     */
    public function getCategoryTagAsXml(AbstractModel $category)
    {
        $hreflangTags = $this->getTagsByEntity(self::ENTITY_CATEGORY, $category->getId());

        return $this->getTagsAsXml($hreflangTags, self::ENTITY_CATEGORY);
    }

    /**
     * @inheritdoc
     */
    public function getCmsTagAsXml(AbstractModel $page)
    {
        $relField = $this->getCmsPageRelationField->execute();
        $hreflangTags = $this->getTagsByEntity(self::ENTITY_CMS_PAGE, $page->getData($relField));

        return $this->getTagsAsXml($hreflangTags, self::ENTITY_CMS_PAGE);
    }

    /**
     * @param $url
     * @param $type
     * @return string
     */
    protected function modifyUrl($url, $type) {
        $newUrl = '';
        $parsedUrl = parse_url($url);
        // For issue with Google Search Console
        $protocol = 'https://';

        $stores = array_map( function($item) {
            return $item->getCode();
        }, $this->storeManager->getStores());
        $path = null;
        if(isset($parsedUrl["path"])) {
            $path = $parsedUrl["path"];
        }
        if($path) {
            foreach ($stores as $store) {
                $needle = '/'.$store.'/';
                if(strpos($path, $needle) !== false) {
                    if(isset($parsedUrl["host"])) {
                        $newUrl = $parsedUrl["host"] . "/";
                    }
                    if($path) {
                        $newUrl = $newUrl . $store . '/' . $type . '/' . substr($path,strpos($path, $needle) + strlen($needle));
                    }
                    break;
                }
            }
        }

        if (!$newUrl) {
            if (isset($parsedUrl["host"])) {
                $newUrl = $parsedUrl["host"] . "/" . $type;
            }
            if ($path) {
                $newUrl = $newUrl . $path;
            }
        }

        if(isset($parsedUrl["query"])) {
            $newUrl = $newUrl . $parsedUrl["query"];
        }

        return $protocol . $newUrl;
    }

    /**
     * @param string[] $tags
     * @return string
     */
    private function getTagsAsXml(array $tags, $type = '')
    {
        $replace = array("-&", "&");
        $result = '';

        foreach ($tags as $lang => $url) {
            if($type == self::ENTITY_PRODUCT || $type == self::ENTITY_CATEGORY) {
                $modUrl = $this->modifyUrl($url, $type);
                $result .= PHP_EOL . "<xhtml:link rel=\"alternate\" hreflang=\"$lang\" href=\"$modUrl\"/>";
            } else {
                $result .= PHP_EOL . "<xhtml:link rel=\"alternate\" hreflang=\"$lang\" href=\"$url\"/>";
            }
        }

        if (strpos($result, '&') !== false) {
            $result = str_replace($replace, '', $result);
        }

        return $result;
    }

    /**
     * @param string $entityType
     * @param string|int $id
     * @return array
     */
    private function getTagsByEntity($entityType, $id)
    {
        if (!isset($this->hreflangTags[$entityType])) {
            $this->hreflangTags[$entityType] =
                $this->getHreflangProviderByCode($entityType)->get($this->currentStoreId);
        }

        $tags = isset($this->hreflangTags[$entityType][$id]) ? $this->hreflangTags[$entityType][$id] : [];

        return $tags;
    }

    /**
     * @param string $code
     * @return DataProviderInterface
     * @throws LocalizedException
     */
    private function getHreflangProviderByCode($code)
    {
      if (!isset($this->hreflangProviders[$code])) {
          throw new LocalizedException(__("hreflang prvider for \"$code\" doesn't exist."));
      }

      return $this->hreflangProviders[$code];
    }
}
