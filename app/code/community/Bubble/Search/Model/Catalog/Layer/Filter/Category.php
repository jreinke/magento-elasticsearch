<?php
/**
 * Handles category filtering in layered navigation.
 *
 * @category Bubble
 * @package Bubble_Search
 * @author Johann Reinke <johann@bubblecode.net>
 */
class Bubble_Search_Model_Catalog_Layer_Filter_Category extends Mage_Catalog_Model_Layer_Filter_Category
{
    /**
     * Adds category filter to product collection.
     *
     * @param Mage_Catalog_Model_Category $category
     * @return Bubble_Search_Model_Catalog_Layer_Filter_Category
     */
    public function addCategoryFilter($category)
    {
        $value = array(
            'categories' => $category->getId()
        );
        $this->getLayer()->getProductCollection()
            ->addFqFilter($value);

        return $this;
    }

    /**
     * Adds facet condition to product collection.
     *
     * @see Bubble_Search_Model_Resource_Catalog_Product_Collection::addFacetCondition()
     * @return Bubble_Search_Model_Catalog_Layer_Filter_Category
     */
    public function addFacetCondition()
    {
        /** @var $category Mage_Catalog_Model_Category */
        $category = $this->getCategory();
        $childrenCategories = $category->getChildrenCategories();

        $useFlat = (bool) Mage::getStoreConfig('catalog/frontend/flat_catalog_category');
        $categories = ($useFlat)
            ? array_keys($childrenCategories)
            : array_keys($childrenCategories->toArray());

        $this->getLayer()->getProductCollection()->addFacetCondition('categories', $categories);

        return $this;
    }

    /**
     * Retrieves request parameter and applies it to product collection.
     *
     * @param Zend_Controller_Request_Abstract $request
     * @param Mage_Core_Block_Abstract $filterBlock
     * @return Bubble_Search_Model_Catalog_Layer_Filter_Category
     */
    public function apply(Zend_Controller_Request_Abstract $request, $filterBlock)
    {
        $filter = (int) $request->getParam($this->getRequestVar());
        if ($filter) {
            $this->_categoryId = $filter;
        }

        /** @var $category Mage_Catalog_Model_Category */
        $category = $this->getCategory();
        if (!Mage::registry('current_category_filter')) {
            Mage::register('current_category_filter', $category);
        }

        if (!$filter) {
            $this->addCategoryFilter($category, null);
            return $this;
        }

        $this->_appliedCategory = Mage::getModel('catalog/category')
            ->setStoreId(Mage::app()->getStore()->getId())
            ->load($filter);

        if ($this->_isValidCategory($this->_appliedCategory)) {
            $this->getLayer()->getProductCollection()
                ->addCategoryFilter($this->_appliedCategory);
            $this->addCategoryFilter($this->_appliedCategory);
            $this->getLayer()->getState()->addFilter(
                $this->_createItem($this->_appliedCategory->getName(), $filter)
            );
        }

        return $this;
    }

    /**
     * Retrieves current items data.
     *
     * @return array
     */
    protected function _getItemsData()
    {
        $layer = $this->getLayer();
        $key = $layer->getStateKey().'_SUBCATEGORIES';
        $data = $layer->getCacheData($key);

        if ($data === null) {
            $categories = $this->getCategory()->getChildrenCategories();

            /** @var $productCollection Bubble_Search_Model_Resource_Catalog_Product_Collection */
            $productCollection = $layer->getProductCollection();
            $facets = $productCollection->getFacetedData('categories');

            $data = array();
            foreach ($categories as $category) {
                /** @var $category Mage_Catalog_Model_Category */
                $categoryId = $category->getId();
                if (isset($facets[$categoryId])) {
                    $category->setProductCount($facets[$categoryId]);
                } else {
                    $category->setProductCount(0);
                }
                if ($category->getIsActive() && $category->getProductCount()) {
                    $data[] = array(
                        'label' => Mage::helper('core')->escapeHtml($category->getName()),
                        'value' => $categoryId,
                        'count' => $category->getProductCount(),
                    );
                }
            }
            $tags = $layer->getStateTags();
            $layer->getAggregator()->saveCacheData($data, $key, $tags);
        }

        return $data;
    }
}
