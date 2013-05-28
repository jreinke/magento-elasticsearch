<?php
/**
 * Search engine index.
 *
 * @category Bubble
 * @package Bubble_Search
 * @author Johann Reinke <johann@bubblecode.net>
 */
class Bubble_Search_Model_Resource_Engine_Index extends Mage_CatalogSearch_Model_Resource_Fulltext
{
    /**
     * Adds advanced index data.
     *
     * @param array $index
     * @param int $storeId
     * @param array $productIds
     * @return mixed
     */
    public function addAdvancedIndex($index, $storeId, $productIds = null)
    {
        if (is_null($productIds) || !is_array($productIds)) {
            $productIds = array();
            foreach ($index as $productData) {
                $productIds[] = $productData['entity_id'];
            }
        }

        $prefix = $this->_engine->getFieldsPrefix();
        $categoryData = $this->_getCatalogCategoryData($storeId, $productIds);
        $priceData = $this->_getCatalogProductPriceData($productIds);
        foreach ($index as $productId => &$productData) {
            if (isset($categoryData[$productId]) && isset($priceData[$productId])) {
                $productData += $categoryData[$productId];
                $productData += $priceData[$productId];
            } else {
                $productData += array(
                    $prefix . 'categories' => array(),
                    $prefix . 'show_in_categories' => array(),
                    $prefix . 'visibility' => 0
                );
            }
        }

        unset($productData);
        unset($categoryData);
        unset($priceData);

        return $index;
    }

    /**
     * Retrieves category data for advanced index.
     *
     * @param int $storeId
     * @param array $productIds
     * @param bool $visibility
     * @return array
     */
    protected function _getCatalogCategoryData($storeId, $productIds, $visibility = true)
    {
        $adapter = $this->_getWriteAdapter();
        $prefix = $this->_engine->getFieldsPrefix();
        $columns = array(
            'product_id' => 'product_id',
            'parents' => new Zend_Db_Expr("GROUP_CONCAT(IF(is_parent = 1, category_id, '') SEPARATOR ' ')"),
            'anchors' => new Zend_Db_Expr("GROUP_CONCAT(IF(is_parent = 0, category_id, '') SEPARATOR ' ')"),
            'positions' => new Zend_Db_Expr("GROUP_CONCAT(CONCAT(category_id, '_', position) SEPARATOR ' ')"),
        );

        if ($visibility) {
            $columns['visibility'] = 'visibility';
        }

        $select = $adapter->select()
            ->from(array($this->getTable('catalog/category_product_index')), $columns)
            ->where('product_id IN (?)', $productIds)
            ->where('store_id = ?', $storeId)
            ->group('product_id');

        $result = array();
        foreach ($adapter->fetchAll($select) as $row) {
            $data = array(
                $prefix . 'categories' => array_values(array_filter(explode(' ', $row['parents']))), // array_values to reorder keys
                $prefix . 'show_in_categories' => array_values(array_filter(explode(' ', $row['anchors']))), // array_values to reorder keys
            );
            foreach (explode(' ', $row['positions']) as $value) {
                list($categoryId, $position) = explode('_', $value);
                $key = sprintf('%sposition_category_%d', $prefix, $categoryId);
                $data[$key] = $position;
            }
            if ($visibility) {
                $data[$prefix . 'visibility'] = $row['visibility'];
            }

            $result[$row['product_id']] = $data;
        }

        return $result;
    }

    /**
     * Retrieves product price data for advanced index.
     *
     * @param array $productIds
     * @return array
     */
    protected function _getCatalogProductPriceData($productIds = null)
    {
        $adapter = $this->_getWriteAdapter();
        $prefix = $this->_engine->getFieldsPrefix();
        $select = $adapter->select()
            ->from($this->getTable('catalog/product_index_price'),
                array('entity_id', 'customer_group_id', 'website_id', 'min_price'));

        if ($productIds) {
            $select->where('entity_id IN (?)', $productIds);
        }

        $result = array();
        foreach ($adapter->fetchAll($select) as $row) {
            if (!isset($result[$row['entity_id']])) {
                $result[$row['entity_id']] = array();
            }
            $key = sprintf('%sprice_%s_%s', $prefix, $row['customer_group_id'], $row['website_id']);
            $result[$row['entity_id']][$key] = round($row['min_price'], 2);
        }

        return $result;
    }
}
