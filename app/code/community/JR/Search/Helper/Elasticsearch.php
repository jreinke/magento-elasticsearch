<?php
/**
 * Elasticsearch helper.
 *
 * @package JR_Search
 * @subpackage JR_Search_Helper
 * @author Johann Reinke <johann.reinke@gmail.com>
 */
class JR_Search_Helper_Elasticsearch extends JR_Search_Helper_Data
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
}