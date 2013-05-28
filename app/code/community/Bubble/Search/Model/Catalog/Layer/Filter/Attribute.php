<?php
/**
 * Handles attribute filtering in layered navigation.
 *
 * @category Bubble
 * @package Bubble_Search
 * @author Johann Reinke <johann@bubblecode.net>
 */
class Bubble_Search_Model_Catalog_Layer_Filter_Attribute extends Mage_Catalog_Model_Layer_Filter_Attribute
{
    /**
     * Adds facet condition to product collection.
     *
     * @see Bubble_Search_Model_Resource_Catalog_Product_Collection::addFacetCondition()
     * @return Bubble_Search_Model_Catalog_Layer_Filter_Attribute
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
     * @return Bubble_Search_Model_Catalog_Layer_Filter_Attribute
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
     * @return Bubble_Search_Model_Catalog_Layer_Filter_Attribute
     */
    public function applyFilterToCollection($filter, $value)
    {
        if (!$this->_isValidFilter($value)) {
            $value = array();
        } else if (!is_array($value)) {
            $value = array($value);
        }

        $attribute = $filter->getAttributeModel();
        $param = Mage::helper('bubble_search')->getSearchParam($attribute, $value);

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
        /** @var $productCollection Bubble_Search_Model_Resource_Catalog_Product_Collection */
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
        $fieldName = Mage::helper('bubble_search')->getAttributeFieldName($attribute);

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
            $facets = $this->_getFacets();
            $data = array();
            if (array_sum($facets) > 0) {
                if ($attribute->getFrontendInput() != 'text') {
                    $options = $attribute->getFrontend()->getSelectOptions();
                } else {
                    $options = array();
                    foreach ($facets as $label => $count) {
                        $options[] = array(
                            'label' => $label,
                            'value' => $label,
                            'count' => $count,
                        );
                    }
                }
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
     * Returns option label if attribute uses options.
     *
     * @param int $optionId
     * @return bool|int|string
     */
    protected function _getOptionText($optionId)
    {
        if ($this->getAttributeModel()->getFrontendInput() == 'text') {
            return $optionId; // not an option id
        }

        return parent::_getOptionText($optionId);
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
