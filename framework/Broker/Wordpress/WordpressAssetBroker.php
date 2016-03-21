<?php

namespace Nstaeger\Framework\Broker\Wordpress;

use Nstaeger\Framework\Asset\AssetItem;
use Nstaeger\Framework\Broker\AssetBroker;
use Nstaeger\Framework\Configuration;

class WordpressAssetBroker implements AssetBroker
{
    /**
     * @var AssetItem[]
     */
    private $adminAssets;

    /**
     * @var AssetItem[]
     */
    private $assets;

    /**
     * @var string
     */
    private $urlPrefix;

    public function __construct(Configuration $configuration)
    {
        $this->adminAssets = array();
        $this->assets = array();
        $this->urlPrefix = $configuration->getUrl();

        add_action(
            'admin_enqueue_scripts',
            function ($hook) {
                $this->enqueAdminAssets($hook);
            }
        );

        add_action(
            'wp_enqueue_scripts',
            function () {
                $this->enqueAssets();
            }
        );
    }

    public function addAdminAsset(AssetItem $asset)
    {
        $this->adminAssets[] = $asset;
    }

    function addAdminAssets($assets)
    {
        foreach ($assets as $asset) {
            $this->addAdminAsset($asset);
        }
    }

    public function addAsset(AssetItem $asset)
    {
        $this->assets[] = $asset;
    }

    private function enqueAdminAssets($hook)
    {
        foreach ($this->adminAssets as $asset) {
            if (!empty($asset->getHook()) && strpos($hook, $asset->getHook()) === false) {
                continue;
            }

            $path = $this->urlPrefix . $asset->getUrl();
            wp_enqueue_script($asset->getName(), $path);
        }
    }

    private function enqueAssets()
    {
        foreach ($this->assets as $asset) {
            $path = $this->urlPrefix . $asset->getUrl();
            wp_enqueue_script($asset->getName(), $path);

            // TODO solve in another way
            wp_localize_script($asset->getName(), 'ajaxurl', admin_url('admin-ajax.php'));
        }
    }
}
