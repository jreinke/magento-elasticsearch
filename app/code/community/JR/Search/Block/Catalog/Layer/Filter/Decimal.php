<?php
/**
 * Handles decimal attribute filtering in layered navigation.
 *
 * @package JR_Search
 * @subpackage JR_Search_Block
 * @author Johann Reinke <johann.reinke@gmail.com>
 */
class JR_Search_Block_Catalog_Layer_Filter_Decimal extends Mage_Catalog_Block_Layer_Filter_Abstract
{
    /**
     * Defines specific filter model name.
     *
     * @see JR_Search_Model_Catalog_Layer_Filter_Decimal
     */
    public function __construct()
    {
        parent::__construct();
        $this->_filterModelName = 'jr_search/catalog_layer_filter_decimal';
    }

    /**
     * Prepares filter model.
     *
     * @return JR_Search_Block_Catalog_Layer_Filter_Decimal
     */
    protected function _prepareFilter()
    {
        $this->_filter->setAttributeModel($this->getAttributeModel());

        return $this;
    }

    /**
     * Adds facet condition to filter.
     *
     * @see JR_Search_Model_Catalog_Layer_Filter_Decimal::addFacetCondition()
     * @return JR_Search_Block_Catalog_Layer_Filter_Decimal
     */
    public function addFacetCondition()
    {
        $this->_filter->addFacetCondition();

        return $this;
    }
}
