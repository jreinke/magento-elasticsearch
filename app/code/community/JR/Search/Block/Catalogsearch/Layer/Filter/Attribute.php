<?php
/**
 * Handles attribute filtering in layered navigation in a query search context.
 *
 * @package JR_Search
 * @subpackage JR_Search_Block
 * @author Johann Reinke <johann.reinke@gmail.com>
 */
class JR_Search_Block_Catalogsearch_Layer_Filter_Attribute extends Mage_Catalog_Block_Layer_Filter_Abstract
{
    /**
     * Defines specific filter model name.
     *
     * @see JR_Search_Model_Catalogsearch_Layer_Filter_Attribute
     */
    public function __construct()
    {
        parent::__construct();
        $this->_filterModelName = 'jr_search/catalogsearch_layer_filter_attribute';
    }

    /**
     * Prepares filter model.
     *
     * @return JR_Search_Block_Catalogsearch_Layer_Filter_Attribute
     */
    protected function _prepareFilter()
    {
        $this->_filter->setAttributeModel($this->getAttributeModel());

        return $this;
    }

    /**
     * Adds facet condition to filter.
     *
     * @see JR_Search_Model_Catalog_Layer_Filter_Attribute::addFacetCondition()
     * @return JR_Search_Block_Catalogsearch_Layer_Filter_Attribute
     */
    public function addFacetCondition()
    {
        $this->_filter->addFacetCondition();

        return $this;
    }
}
