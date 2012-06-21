<?php

class JR_Search_Model_Catalogsearch_Layer_Filter_Attribute extends JR_Search_Model_Catalog_Layer_Filter_Attribute
{
    protected function _getIsFilterableAttribute($attribute)
    {
        return $attribute->getIsFilterableInSearch();
    }
}
