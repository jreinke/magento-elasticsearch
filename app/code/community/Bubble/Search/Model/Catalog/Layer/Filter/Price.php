<?php
/**
 * Handles price filtering in layered navigation.
 *
 * @category Bubble
 * @package Bubble_Search
 * @author Johann Reinke <johann@bubblecode.net>
 */
class Bubble_Search_Model_Catalog_Layer_Filter_Price extends Mage_Catalog_Model_Layer_Filter_Price
{
    const CACHE_TAG = 'MAXPRICE';

    /**
     * Adds facet condition to product collection.
     *
     * @see Bubble_Search_Model_Resource_Catalog_Product_Collection::addFacetCondition()
     * @return Bubble_Search_Model_Catalog_Layer_Filter_Price
     */
    public function addFacetCondition()
    {
        $range = $this->getPriceRange();
        $maxPrice = $this->getMaxPriceInt();
        if ($maxPrice > 0) {
            $priceFacets = array();
            $facetCount = (int) ceil($maxPrice / $range);

            for ($i = 0; $i < $facetCount + 1; $i++) {
                $from = ($i === 0) ? '' : ($i * $range);
                $to = ($i === $facetCount) ? '' : (($i + 1) * $range);
                $priceFacets[] = array(
                    'from' => $from,
                    'to' => $to,
                    'include_upper' => !($i < $facetCount)
                );
            }

            $this->getLayer()->getProductCollection()->addFacetCondition($this->_getFilterField(), $priceFacets);
        }

        return $this;
    }

    /**
     * Returns cache tag.
     *
     * @return string
     */
    public function getCacheTag()
    {
        return self::CACHE_TAG;
    }

    /**
     * Retrieves max price for ranges definition.
     *
     * @return float
     */
    public function getMaxPriceInt()
    {
        $searchParams = $this->getLayer()->getProductCollection()->getExtendedSearchParams();
        $uniquePart = strtoupper(md5(serialize($searchParams)));
        $cacheKey = 'MAXPRICE_' . $this->getLayer()->getStateKey() . '_' . $uniquePart;

        $cachedData = Mage::app()->loadCache($cacheKey);
        if (!$cachedData) {
            $stats = $this->getLayer()->getProductCollection()->getStats($this->_getFilterField());

            $max = $stats[$this->_getFilterField()]['max'];
            if (!is_numeric($max)) {
                $max = parent::getMaxPriceInt();
            }

            $cachedData = (float) $max;
            $tags = $this->getLayer()->getStateTags();
            $tags[] = self::CACHE_TAG;
            Mage::app()->saveCache($cachedData, $cacheKey, $tags);
        }

        return $cachedData;
    }

    /**
     * Apply price range filter to product collection.
     *
     * @return Bubble_Search_Model_Catalog_Layer_Filter_Price
     */
    protected function _applyPriceRange()
    {
        $interval = $this->getInterval();
        if (!$interval) {
            return $this;
        }

        list($from, $to) = $interval;
        if ($from === '' && $to === '') {
            return $this;
        }

        if ($to !== '') {
            $to = (float) $to;
            if ($from == $to) {
                $to += .01;
            }
        }

        $field = $this->_getFilterField();
        $value = array(
            $field => array(
                'include_upper' => !($to < $this->getMaxPriceInt())
            )
        );

        if (!empty($from)) {
            $value[$field]['from'] = $from;
        }
        if (!empty($to)) {
            $value[$field]['to'] = $to;
        }

        $this->getLayer()->getProductCollection()->addFqRangeFilter($value);

        return $this;
    }

    /**
     * Returns price field according to current customer group and website.
     *
     * @return string
     */
    protected function _getFilterField()
    {
        $websiteId = Mage::app()->getStore()->getWebsiteId();
        $customerGroupId = Mage::getSingleton('customer/session')->getCustomerGroupId();
        $priceField = 'price_' . $customerGroupId . '_' . $websiteId;

        return $priceField;
    }

    /**
     * Retrieves current items data.
     *
     * @return array
     */
    protected function _getItemsData()
    {
        if (Mage::app()->getStore()->getConfig(self::XML_PATH_RANGE_CALCULATION) == self::RANGE_CALCULATION_IMPROVED) {
            return $this->_getCalculatedItemsData();
        } elseif ($this->getInterval()) {
            return array();
        }

        $data = array();
        $facets = $this->getLayer()->getProductCollection()->getFacetedData($this->_getFilterField());
        if (!empty($facets)) {
            foreach ($facets as $key => $count) {
                if (!$count) {
                    unset($facets[$key]);
                }
            }
            $i = 0;
            foreach ($facets as $key => $count) {
                $i++;
                preg_match('/^\[(\d*) TO (\d*)\]$/', $key, $rangeKey);
                $fromPrice = $rangeKey[1];
                $toPrice = ($i < count($facets)) ? $rangeKey[2] : '';
                $data[] = array(
                    'label' => $this->_renderRangeLabel($fromPrice, $toPrice),
                    'value' => $fromPrice . '-' . $toPrice,
                    'count' => $count
                );
            }
        }

        return $data;
    }
}
