<?php
/**
 * Handles category filtering in layered navigation.
 *
 * @package JR_Search
 * @subpackage JR_Search_Block
 * @author Johann Reinke <johann.reinke@gmail.com>
 */
class JR_Search_Block_Catalog_Layer_Filter_Category extends Mage_Catalog_Block_Layer_Filter_Abstract
{
    /**
     * Defines specific filter model name.
     *
     * @see JR_Search_Model_Catalog_Layer_Filter_Category
     */
    public function __construct()
    {
        parent::__construct();
        $this->_filterModelName = 'jr_search/catalog_layer_filter_category';
    }

    /**
     * Adds facet condition to filter.
     *
     * @see JR_Search_Model_Catalog_Layer_Filter_Category::addFacetCondition()
     * @return JR_Search_Block_Catalog_Layer_Filter_Attribute
     */
    public function addFacetCondition()
    {
        $this->_filter->addFacetCondition();

        return $this;
    }
}
