<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 *
 * Released under the GNU General Public License
 */

// @codingStandardsIgnoreFile

namespace Payout\Payment\Model;

use Magento\Directory\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Config model that is aware of all \Payout\Payment payment methods
 * Works with Payout-specific system configuration
 * @SuppressWarnings(PHPMD.ExcesivePublicCount)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Config extends AbstractConfig
{

    /**
     * @var string Payout this is a model which we will use.
     */
    const string METHOD_CODE = 'payout';

    /**
     * Core
     * data @var Data
     */
    protected Data $directoryHelper;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $_storeManager;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $_logger;

    /**
     * @var UrlInterface
     */
    protected UrlInterface $_urlBuilder;

    /**
     * @var Repository
     */
    protected Repository $_assetRepo;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Data $directoryHelper
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param Repository $assetRepo
     * @throws NoSuchEntityException
     */
    public function __construct(
        ScopeConfigInterface  $scopeConfig,
        Data                  $directoryHelper,
        StoreManagerInterface $storeManager,
        LoggerInterface       $logger,
        Repository            $assetRepo
    )
    {
        $this->_logger = $logger;
        parent::__construct($scopeConfig);
        $this->directoryHelper = $directoryHelper;
        $this->_storeManager = $storeManager;
        $this->_assetRepo = $assetRepo;
        $this->scopeConfig = $scopeConfig;

        $this->setMethod('payout');
        $currentStoreId = $this->_storeManager->getStore()->getStoreId();
        $this->setStoreId($currentStoreId);
    }

    /**
     * Check whether method available for checkout or not
     * Logic based on merchant country, methods dependence
     *
     * @param string|null $methodCode
     *
     * @return bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function isMethodAvailable($methodCode = null): bool
    {
        return parent::isMethodAvailable($methodCode);
    }

    /**
     * Return merchant country code, use default country if it not specified in General settings
     *
     * @return string
     */
    public function getMerchantCountry(): string
    {
        return $this->directoryHelper->getDefaultCountry($this->_storeId);
    }

    /**
     * Check whether method supported for specified country or not
     * Use $_methodCode and merchant country by default
     *
     * @param string|null $method
     * @param string|null $countryCode
     *
     * @return bool
     */
    public function isMethodSupportedForCountry(string $method = null, string $countryCode = null): bool
    {
        if ($method === null) {
            $method = $this->getMethodCode();
        }

        if ($countryCode === null) {
            $countryCode = $this->getMerchantCountry();
        }

        return in_array($method, $this->getCountryMethods($countryCode));
    }

    /**
     * Return list of allowed methods for specified country iso code
     *
     * @param string|null $countryCode 2-letters iso code
     *
     * @return array
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getCountryMethods(string $countryCode = null): array
    {
        $countryMethods = [
            'other' => [
                self::METHOD_CODE,
            ],

        ];
        if ($countryCode === null) {
            return $countryMethods;
        }

        return $countryMethods[$countryCode] ?? $countryMethods['other'];
    }

    /**
     * Get Payout "mark" image URL
     * TODO - Maybe this can be placed in the config xml
     *
     * @return string
     */
    public function getPaymentMarkImageUrl(): string
    {
        return $this->_assetRepo->getUrl('Payout_Payment::images/logo.png');
    }

    /**
     * Get "What Is Payout" localized URL
     * Supposed to be used with "mark" as popup window
     *
     * @return string
     */
    public function getPaymentMarkWhatIsPayout(): string
    {
        return 'Payout Payment gateway';
    }

    /**
     * _mapPayoutFieldset
     * Map Payout config fields
     *
     * @param string $fieldName
     *
     * @return string|null
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _mapPayoutFieldset(string $fieldName): ?string
    {
        return "payment/$this->_methodCode/$fieldName";
    }

    /**
     * Map any supported payment method into a config path by specified field name
     *
     * @param string $fieldName
     *
     * @return string|null
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function _getSpecificConfigPath(string $fieldName): ?string
    {
        return $this->_mapPayoutFieldset($fieldName);
    }
}
