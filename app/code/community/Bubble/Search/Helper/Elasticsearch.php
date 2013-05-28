<?php
/**
 * Elasticsearch helper.
 *
 * @category Bubble
 * @package Bubble_Search
 * @author Johann Reinke <johann@bubblecode.net>
 */
class Bubble_Search_Helper_Elasticsearch extends Bubble_Search_Helper_Data
{
    /**
     * Returns Elasticsearch engine config data.
     *
     * @param string $prefix
     * @param mixed $store
     * @return array
     */
    public function getEngineConfigData($prefix = '', $store = null)
    {
        $config = parent::getEngineConfigData('elasticsearch_', $store);
        $servers = array();
        foreach (explode(',', $config['servers']) as $server) {
            $pieces = explode(':', $server);
            $host = trim($pieces[0]);
            $port = (int) trim($pieces[1]);
            $servers[] = array('host' => $host, 'port' => $port);
        }
        $config['servers'] = $servers;

        return $config;
    }

    /**
     * Should Elasticsearch also search on options?
     *
     * @return bool
     */
    public function shouldSearchOnOptions()
    {
        return Mage::getStoreConfigFlag('catalog/search/elasticsearch_enable_options_search');
    }
}