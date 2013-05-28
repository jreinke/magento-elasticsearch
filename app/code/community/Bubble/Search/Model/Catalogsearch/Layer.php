<?php

class Bubble_Search_Model_Catalogsearch_Layer extends Mage_CatalogSearch_Model_Layer
{
    public function getProductCollection()
    {
        $category = $this->getCurrentCategory();
        if (isset($this->_productCollections[$category->getId()])) {
            $collection = $this->_productCollections[$category->getId()];
        } else {
            /** @var $collection Bubble_Search_Model_Resource_Catalog_Product_Collection */
            $collection = Mage::helper('catalogsearch')
                ->getEngine()
                ->getResultCollection()
                ->setStoreId($category->getStoreId())
                ->addFqFilter(array('store_id' => $category->getStoreId()));
            $this->prepareProductCollection($collection);
            $this->_productCollections[$category->getId()] = $collection;
        }

        return $collection;
    }
}
