<?php
/**
 * Handles decimal attribute filtering in layered navigation.
 *
 * @category Bubble
 * @package Bubble_Search
 * @author Johann Reinke <johann@bubblecode.net>
 */
class Bubble_Search_Block_Catalog_Layer_Filter_Price extends Mage_Catalog_Block_Layer_Filter_Abstract
{
    /**
     * Defines specific filter model name.
     *
     * @see Bubble_Search_Model_Catalog_Layer_Filter_Price
     */
    public function __construct()
    {
        parent::__construct();
        $this->_filterModelName = 'bubble_search/catalog_layer_filter_price';
    }

    /**
     * Prepares filter model.
     *
     * @return Bubble_Search_Block_Catalog_Layer_Filter_Price
     */
    protected function _prepareFilter()
    {
        $this->_filter->setAttributeModel($this->getAttributeModel());

        return $this;
    }

    /**
     * Adds facet condition to filter.
     *
     * @see Bubble_Search_Model_Catalog_Layer_Filter_Price::addFacetCondition()
     * @return Bubble_Search_Block_Catalog_Layer_Filter_Price
     */
    public function addFacetCondition()
    {
        if (!$this->getRequest()->getParam('price')) {
            $this->_filter->addFacetCondition();
        }

        return $this;
    }
}
