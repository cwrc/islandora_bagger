<?php

/**
 * @file
 * Defines the AbstractIbPlugin class.
 */

namespace App\Plugin;

/**
 * Abstract class for plugins.
 */
abstract class AbstractIbPlugin
{
    /**
     * Constructor.
     *
     * @param array $config
     *    The configuration data from the .ini file.
     * @param object $logger
     *    The Monolog logger from the main Command.
     */
    public function __construct($settings, $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * Modifies the current Bag.
     *
     * All plugins must implement this method.
     *
     * @param object $bag
     *    The Bag object.
     * @param string $bag_temp_dir
     *    The absolute path to the directory where content files, etc. are to be downloaded.
     * @param int $nid
     *    The node ID.
     * @param string $node_json
     *    The node's JSON representation.
     *
     * @return The modified Bag.
     */
    abstract public function execute($bag, $bag_temp_dir, $nid, $node_json);
}
