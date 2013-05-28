<?php
/**
 * Overrides default layer view process to define custom filter blocks.
 *
 * @category Bubble
 * @package Bubble_Search
 * @author Johann Reinke <johann@bubblecode.net>
 */
class Bubble_Search_Block_Catalog_Layer_View extends Mage_Catalog_Block_Layer_View
{
    /**
     * Boolean block name.
     *
     * @var string
     */
    protected $_booleanFilterBlockName;

    /**
     * Registers current layer in registry.
     *
     * @see Mage_Catalog_Block_Product_List::getLayer()
     */
    protected function _construct()
    {
        parent::_construct();
        Mage::register('current_layer', $this->getLayer());
    }

    /**
     * Modifies default block names to specific ones if engine is active.
     */
    protected function _initBlocks()
    {
        parent::_initBlocks();

        if (Mage::helper('bubble_search')->isActiveEngine()) {
            $this->_categoryBlockName        = 'bubble_search/catalog_layer_filter_category';
            $this->_attributeFilterBlockName = 'bubble_search/catalog_layer_filter_attribute';
            $this->_priceFilterBlockName     = 'bubble_search/catalog_layer_filter_price';
            $this->_decimalFilterBlockName   = 'bubble_search/catalog_layer_filter_decimal';
            $this->_booleanFilterBlockName   = 'bubble_search/catalog_layer_filter_boolean';
        }
    }

    /**
     * Prepares layout if engine is active.
     * Difference between parent method is addFacetCondition() call on each created block.
     *
     * @return Bubble_Search_Block_Catalog_Layer_View
     */
    protected function _prepareLayout()
    {
        /** @var $helper Bubble_Search_Helper_Data */
        $helper = Mage::helper('bubble_search');
        if (!$helper->isActiveEngine()) {
            parent::_prepareLayout();
        } else {
            $stateBlock = $this->getLayout()->createBlock($this->_stateBlockName)
                ->setLayer($this->getLayer());

            $categoryBlock = $this->getLayout()->createBlock($this->_categoryBlockName)
                ->setLayer($this->getLayer())
                ->init();

            $this->setChild('layer_state', $stateBlock);
            $this->setChild('category_filter', $categoryBlock->addFacetCondition());

            $filterableAttributes = $this->_getFilterableAttributes();
            $filters = array();
            foreach ($filterableAttributes as $attribute) {
                if ($attribute->getAttributeCode() == 'price') {
                    $filterBlockName = $this->_priceFilterBlockName;
                } elseif ($attribute->getBackendType() == 'decimal') {
                    $filterBlockName = $this->_decimalFilterBlockName;
                } elseif ($attribute->getSourceModel() == 'eav/entity_attribute_source_boolean') {
                    $filterBlockName = $this->_booleanFilterBlockName;
                } else {
                    $filterBlockName = $this->_attributeFilterBlockName;
                }

                $filters[$attribute->getAttributeCode() . '_filter'] = $this->getLayout()->createBlock($filterBlockName)
                    ->setLayer($this->getLayer())
                    ->setAttributeModel($attribute)
                    ->init();
            }

            foreach ($filters as $filterName => $block) {
                $this->setChild($filterName, $block->addFacetCondition());
            }

            $this->getLayer()->apply();
        }

        return $this;
    }

    /**
     * Returns current catalog layer.
     *
     * @return Bubble_Search_Model_Catalog_Layer|Mage_Catalog_Model_Layer
     */
    public function getLayer()
    {
        /** @var $helper Bubble_Search_Helper_Data */
        $helper = Mage::helper('bubble_search');
        if ($helper->isActiveEngine()) {
            return Mage::getSingleton('bubble_search/catalog_layer');
        }

        return parent::getLayer();
    }
}
