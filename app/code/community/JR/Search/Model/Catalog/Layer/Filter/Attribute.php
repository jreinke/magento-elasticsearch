<?php
/**
 * Handles attribute filtering in layered navigation.
 *
 * @package JR_Search
 * @subpackage JR_Search_Model
 * @author Johann Reinke <johann.reinke@gmail.com>
 */
class JR_Search_Model_Catalog_Layer_Filter_Attribute extends Mage_Catalog_Model_Layer_Filter_Attribute
{
    /**
     * Adds facet condition to product collection.
     *
     * @see JR_Search_Model_Resource_Catalog_Product_Collection::addFacetCondition()
     * @return JR_Search_Model_Catalog_Layer_Filter_Attribute
     */
    public function addFacetCondition()
    {
        $this->getLayer()
            ->getProductCollection()
            ->addFacetCondition($this->_getFilterField());

        return $this;
    }

    /**
     * Retrieves request parameter and applies it to product collection.
     *
     * @param Zend_Controller_Request_Abstract $request
     * @param Mage_Core_Block_Abstract $filterBlock
     * @return JR_Search_Model_Catalog_Layer_Filter_Attribute
     */
    public function apply(Zend_Controller_Request_Abstract $request, $filterBlock)
    {
        $filter = $request->getParam($this->_requestVar);
        if (is_array($filter) || null === $filter) {
            return $this;
        }

        $text = $this->_getOptionText($filter);
        if ($this->_isValidFilter($filter) && strlen($text)) {
            $this->applyFilterToCollection($this, $filter);
            $this->getLayer()->getState()->addFilter($this->_createItem($text, $filter));
            $this->_items = array();
        }

        return $this;
    }

    /**
     * Applies filter to product collection.
     *
     * @param $filter
     * @param $value
     * @return JR_Search_Model_Catalog_Layer_Filter_Attribute
     */
    public function applyFilterToCollection($filter, $value)
    {
        if (!$this->_isValidFilter($value)) {
            $value = array();
        } else if (!is_array($value)) {
            $value = array($value);
        }

        $attribute = $filter->getAttributeModel();
        $param = Mage::helper('jr_search')->getSearchParam($attribute, $value);

        $this->getLayer()
            ->getProductCollection()
            ->addSearchQfFilter($param);

        return $this;
    }

    /**
     * Returns facets data of current attribute.
     *
     * @return array
     */
    protected function _getFacets()
    {
        /** @var $productCollection JR_Search_Model_Resource_Catalog_Product_Collection */
        $productCollection = $this->getLayer()->getProductCollection();
        $fieldName = $this->_getFilterField();
        $facets = $productCollection->getFacetedData($fieldName);

        return $facets;
    }

    /**
     * Returns attribute field name.
     *
     * @return string
     */
    protected function _getFilterField()
    {
        /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
        $attribute = $this->getAttributeModel();
        $fieldName = Mage::helper('jr_search')->getAttributeFieldName($attribute);

        return $fieldName;
    }

    /**
     * Retrieves current items data.
     *
     * @return array
     */
    protected function _getItemsData()
    {
        /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
        $attribute = $this->getAttributeModel();
        $this->_requestVar = $attribute->getAttributeCode();

        $layer = $this->getLayer();
        $key = $layer->getStateKey() . '_' . $this->_requestVar;
        $data = $layer->getAggregator()->getCacheData($key);

        if ($data === null) {
            /** @var $productCollection JR_Search_Model_Resource_Catalog_Product_Collection */
            $options = $attribute->getFrontend()->getSelectOptions();
            $facets = $this->_getFacets();

            $data = array();
            if (array_sum($facets) > 0) {
                foreach ($options as $option) {
                    if (is_array($option['value']) || !Mage::helper('core/string')->strlen($option['value'])) {
                        continue;
                    }
                    $count = 0;
                    $label = $option['label'];
                    if (isset($facets[$option['value']])) {
                        $count = (int) $facets[$option['value']];
                    }
                    if (!$count && $this->_getIsFilterableAttribute($attribute) == self::OPTIONS_ONLY_WITH_RESULTS) {
                        continue;
                    }
                    $data[] = array(
                        'label' => $label,
                        'value' => $option['value'],
                        'count' => (int) $count,
                    );
                }
            }

            $tags = array(
                Mage_Eav_Model_Entity_Attribute::CACHE_TAG . ':' . $attribute->getId()
            );

            $tags = $layer->getStateTags($tags);
            $layer->getAggregator()->saveCacheData($data, $key, $tags);
        }

        return $data;
    }

    /**
     * Checks if given filter is valid before being applied to product collection.
     *
     * @param string $filter
     * @return bool
     */
    protected function _isValidFilter($filter)
    {
        return !empty($filter);
    }
}
