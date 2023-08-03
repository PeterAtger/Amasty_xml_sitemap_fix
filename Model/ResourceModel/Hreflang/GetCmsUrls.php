<?php
/**
 * @category  Koene
 * @package   Koene_XmlSitemap
 * @author    Peter Atef <info@scandiweb.com>
 * @copyright Copyright (c) 2023 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */

namespace Koene\XmlSitemap\Model\ResourceModel\Hreflang;

use Amasty\XmlSitemap\Model\Hreflang\GetCmsPageRelationFieldInterface;
use Amasty\XmlSitemap\Model\Hreflang\GetUrlsInterface;
use Amasty\XmlSitemap\Model\Hreflang\GetBaseStoreUrlsInterface;
use Amasty\XmlSitemap\Model\ResourceModel\Hreflang\GetCmsUrls as AmastyGetCmsUrls;

class GetCmsUrls extends AmastyGetCmsUrls
{
    /**
     * @var GetBaseStoreUrlsInterface
     */
    public $getBaseStoreUrls;

    /**
     * @var \Magento\Framework\EntityManager\MetadataPool
     */
    public $metadataPool;

    /**
     * @var GetCmsPageRelationFieldInterface
     */
    public $getCmsPageRelationField;

    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        GetBaseStoreUrlsInterface $getBaseStoreUrls,
        \Magento\Framework\EntityManager\MetadataPool $metadataPool,
        GetCmsPageRelationFieldInterface $getCmsPageRelationField,
        $connectionName = null
    ) {
        parent::__construct(
            $context,
            $getBaseStoreUrls,
            $metadataPool,
            $getCmsPageRelationField,
            $connectionName
        );
        $this->getBaseStoreUrls = $getBaseStoreUrls;
        $this->metadataPool = $metadataPool;
        $this->getCmsPageRelationField = $getCmsPageRelationField;
    }


    /**
     * @inheritdoc
     */
    public function execute($storeIds, array $ids = null)
    {
        $linkField = $this->getLinkField();
        $relationField = $this->getCmsPageRelationField->execute();
        $select = $this->getConnection()->select()
            ->from(
                ['main_table' => $this->getMainTable()],
                ['id' => $relationField, 'url' => 'identifier']
            )->join(
                ['page_store' => $this->getTable('cms_page_store')],
                "main_table.$linkField = page_store.$linkField",
                ['store_id']
            )->where('store_id IN(?)', array_merge($storeIds, [0]))
            ->where('is_active = 1');
        if ($relationField === GetCmsPageRelationFieldInterface::FIELD_CMS_UUID) {
            $select->where("$relationField != ''");
        }

        if ($linkField != $this->getIdFieldName()) { //get only latest entries for non-CE Magento versions with staging.
            $select->order("main_table.$linkField DESC");
        }

        if (!empty($ids)) {
            $select->where("$relationField IN (?)", $ids);
        }

        $urls = [];
        $storesBaseUrl = $this->getBaseStoreUrls->execute();
        $pages = $this->getConnection()->fetchAll($select);

        foreach ($pages as $page) {
            $key = $page['id'] . '_' . $page['store_id'];

            $x  = $page['url'];

            if (!key_exists($key, $urls)) { //get only latest entries for non-CE Magento versions with staging.
                $urls[$key] = null;

                if ($page['store_id'] === '0' && !str_contains($page['url'], 'home')) {
                    foreach ($storeIds as $storeId) {
                        $item = $page;
                        $item['store_id'] = $storeId;
                        $item['url'] = $storesBaseUrl[$storeId] . 'page/' . $page['url'];
                        $urls[] = $item;
                    }
                } elseif (str_contains($page['url'], 'home')) {
                    foreach ($storeIds as $storeId) {
                        $item = $page;
                        $item['store_id'] = $storeId;
                        $item['url'] = $storesBaseUrl[$storeId];
                        $urls[] = $item;
                    }
                } else {
                    $page['url'] = $storesBaseUrl[$page['store_id']] . 'page/' . $page['url'];
                    $urls[] = $page;
                }
            }
        }

        $urls = array_filter($urls);
        return $urls;
    }


    /**
     * @return string
     */
    public function getLinkField()
    {
        return $this->metadataPool
            ->getMetadata(\Magento\Cms\Api\Data\PageInterface::class)->getLinkField();
    }
}
