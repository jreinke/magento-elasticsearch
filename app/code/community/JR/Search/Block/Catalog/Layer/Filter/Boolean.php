<?php
/**
 * Handles boolean attribute filtering in layered navigation.
 *
 * @package JR_Search
 * @subpackage JR_Search_Block
 * @author Johann Reinke <johann.reinke@gmail.com>
 */
class JR_Search_Block_Catalog_Layer_Filter_Boolean extends JR_Search_Block_Catalog_Layer_Filter_Attribute
{
    /**
     * Defines specific filter model name.
     *
     * @see JR_Search_Model_Catalog_Layer_Filter_Boolean
     */
    public function __construct()
    {
        parent::__construct();
        $this->_filterModelName = 'jr_search/catalog_layer_filter_boolean';
    }
}
