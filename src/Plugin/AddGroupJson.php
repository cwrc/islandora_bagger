<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

use whikloj\BagItTools\Bag;

/**
 * Adds Drupal Group metadata for a given nodeto the Bag.
 */
class AddGroupJson extends AbstractIbPlugin
{
    /**
     * Constructor.
     *
     * @param array $settings
     *    The configuration data from the .ini file.
     * @param object $logger
     *    The Monolog logger from the main Command.
     */
    public function __construct($settings, $logger)
    {
        parent::__construct($settings, $logger);
    }

    /**
     * {@inheritdoc}
     *
     * Adds Drupal Group metadata for a given node to the Bag.
     */
    public function execute(Bag $bag, $bag_temp_dir, $nid, $node_json, $token = NULL)
    {

        // Get translations, if present
        $url = $this->settings['drupal_base_url'] . '/views/preservation_entity_drupal_group/node/' . $nid;
        $response = $this->getNodeJsonApi($url, [], $token);
        if ($response) {
            $bag->createFile($response, "node_group_metadata.json");
        }

        return $bag;
    }

    /**
     * {@inheritdoc}
     * 
     * Drupal Core JSON:API request
     */
    private function getNodeJsonApi($url, $query, $token)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', $url, [
            'http_errors' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/vnd.api+json'
                ],
            'query' => $query
        ]);
        if ($response->getStatusCode() == 200) {
            $this->logger->debug("Successfully retrieved $url with query " . json_encode($query));
            return (string) $response->getBody();
        }
        else {
            $this->logger->error("Failed to retrieve JSON:API serialization $url with query " . json_encode($query));
            return false;
        }
    }

}
