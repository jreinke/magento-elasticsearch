<?php
/**
 * Handles attribute filtering in layered navigation.
 *
 * @category Bubble
 * @package Bubble_Search
 * @author Johann Reinke <johann@bubblecode.net>
 */
class Bubble_Search_Block_Catalog_Layer_Filter_Attribute extends Mage_Catalog_Block_Layer_Filter_Abstract
{
    /**
     * Defines specific filter model name.
     *
     * @see Bubble_Search_Model_Catalog_Layer_Filter_Attribute
     */
    public function __construct()
    {
        parent::__construct();
        $this->_filterModelName = 'bubble_search/catalog_layer_filter_attribute';
    }

    /**
     * Prepares filter model.
     *
     * @return Bubble_Search_Block_Catalog_Layer_Filter_Attribute
     */
    protected function _prepareFilter()
    {
        $this->_filter->setAttributeModel($this->getAttributeModel());

        return $this;
    }

    /**
     * Adds facet condition to filter.
     *
     * @see Bubble_Search_Model_Catalog_Layer_Filter_Attribute::addFacetCondition()
     * @return Bubble_Search_Block_Catalog_Layer_Filter_Attribute
     */
    public function addFacetCondition()
    {
        $this->_filter->addFacetCondition();

        return $this;
    }
}
