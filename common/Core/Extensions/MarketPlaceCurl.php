<?php

namespace Core\Extensions;

use Core\Http\Curl;
use Core\Extensions\ExtensionsService;
use Core\Core;

defined('ROOT') or exit('Access denied!');

class MarketPlaceCurl extends Curl
{
    protected string $siteID = '';

    protected string $endPoint = 'https://299ko-api.flexcb.fr/';

    protected ExtensionsService $service;

    public function __construct(string $url, ExtensionsService $service)
    {
        $this->service = $service;
        $core = Core::getInstance();

        $configuredEndpoint = $core->getConfigVal('marketplaceUrl');
        if (!is_string($configuredEndpoint) || $configuredEndpoint === '') {
            $configuredEndpoint = $core->getEnv('marketplaceUrl', '');
        }
        if (is_string($configuredEndpoint) && $configuredEndpoint !== '') {
            $this->endPoint = rtrim($configuredEndpoint, '/') . '/';
        }

        parent::__construct($this->endPoint . $url);
        $marketConfig = $this->service->getMarketplaceConfig();
        if (!isset($marketConfig['siteID'])) {
            $marketConfig['siteID'] = uniqid('299ko-', true);
            $this->service->saveMarketplaceConfig($marketConfig);
        }
        $this->siteID = $marketConfig['siteID'];
    }

    public function url(string $url): Curl
    {
        $this->url = $this->endPoint . $url;
        return $this;
    }

    public function setDatas($datas): self
    {
        $core = Core::getInstance();
        $this->datas = $datas;
        $this->datas['siteID'] = $this->siteID;
        $this->datas['siteURL'] = $core->getConfigVal('siteUrl');
        $this->datas['siteLang'] = $core->getConfigVal('siteLang');
        $this->datas['siteVersion'] = VERSION;
        $licenseKey = (string)$core->getConfigVal('marketplaceLicenseKey');
        if (!empty($licenseKey)) {
            $this->datas['licenseKey'] = $licenseKey;
            $this->datas['token'] = $licenseKey;
        }
        return $this;
    }

    public function execute(): Curl
    {
        if (empty($this->datas)) {
            $this->setDatas([]);
        }
        $this->addOption(CURLOPT_USERAGENT, '299ko-curl-marketplace');
        $licenseKey = (string)Core::getInstance()->getConfigVal('marketplaceLicenseKey');
        if (!empty($licenseKey)) {
            $this->addOption(CURLOPT_HTTPHEADER, [
                'X-Marketplace-Token: ' . $licenseKey,
                'Authorization: Bearer ' . $licenseKey
            ]);
        }
        return parent::execute();
    }
}


