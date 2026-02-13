<?php
/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace app\Plugin;

use whikloj\BagItTools\Bag;

/** 
 * Add a node using the Drupal Cora JSON:API's serialization.
 * https://www.drupal.org/docs/core-modules-and-themes/core-modules/jsonapi-module/api-overview
 * This plugin retrieves the JSON:API serialization of the node, including all relationships, and adds it to the Bag.
 */
class AddNodeJsonViaJsonApi extends AbstractIbPlugin
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
     * Adds a node using the Drupal Cora JSON:API's serialization
     */
    public function execute(Bag $bag, $bag_temp_dir, $nid, $node_json, $token = NULL)
    {
        $node_data = json_decode($node_json, true);
        $uuid = $node_data['uuid'][0]['value'];
        $node_type = $node_data['type'][0]['target_id'];

        // Get the node's JSON:API serialization: dynamically lookup relationship includes.
        $url = $this->settings['drupal_base_url'] . '/jsonapi/node/' . $node_type . '/' . $uuid;
        $response = $this->getNodeJsonApi($url, [], $token);

        $query_includes = []; 
        $response_json = json_decode($response, true);
        foreach ($response_json['data']['relationships'] ?? [] as $relationship_name => $relationship) {
            if ($relationship_name !== '')
            {
                $query_includes[] = $relationship_name;
            }
        }

        // Get a second level of relationships dynamically, if they exist.
        $query_includes_string = implode(',',$query_includes);
        $response = $this->getNodeJsonApi($url, ['include' => $query_includes_string], $token);
        $response_json = json_decode($response, true);
        foreach ($response_json['included'] ?? [] as $included_entity) {
            if (isset($included_entity['type']) && $included_entity['type'] === 'paragraph--large_text_with_accordion') {
                // Should the type comparison be dynamic? For now, this is the only paragraph type with relationships, so we can hardcode it.
                // Otherwise, add a config setting like some other plugins.
                foreach ($included_entity['relationships'] as $relationship_name => $relationship) {
                    if ($relationship_name !== '') {
                        $include_str = $included_entity['attributes']['parent_field_name'] . '.' . $relationship_name;
                        if (!in_array($include_str, $query_includes)) {
                            $query_includes[] = $include_str;
                        }
                    }
                }
            }
        }

        // Run the request again with the includes.
        $query_includes_string = implode(',',$query_includes);
        $response = $this->getNodeJsonApi($url, ['include' => $query_includes_string], $token);
        if ($response) {
            $bag->createFile($response, 'node_json_api_translation_default.json');
        }

        // Get translations, if present
        // Ignore the default (no langnode in the URL) and captured above.
        $url = $this->settings['drupal_base_url'] . '/api/translations/node/' . $nid;
        $response = $this->getNodeJsonApi($url, [], $token);
        $response_json = json_decode($response, true);
        foreach ($response_json ?? [] as $translation) {
            if (isset($translation['default_langcode']) && $translation['default_langcode'] !== "True") {
                if (isset($translation['langcode'])) {
                    $langcode = $translation['langcode'];
                    $url = $this->settings['drupal_base_url'] . '/jsonapi/node/' . $node_type . '/' . $uuid;
                    $response = $this->getNodeJsonApi($url, ['include' => $query_includes_string], $token);
                    if ($response) {
                        $bag->createFile($response, "node_json_api_translation_$langcode.json");
                    }
                }
            }
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
            $this->logger->debug("Successfully retrieved JSON:API serialization $url with query " . json_encode($query));
            return (string) $response->getBody();
        }
        else {
            $this->logger->error("Failed to retrieve JSON:API serialization $url with query " . json_encode($query));
            return false;
        }
    }
}