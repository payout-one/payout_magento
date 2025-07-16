<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 *
 * Released under the GNU General Public License
 */

namespace Payout\Payment\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class AbstractConfig
 */
abstract class AbstractConfig implements ConfigInterface
{
    /**
     * Core store config
     *
     * @var ScopeConfigInterface
     */
    public ScopeConfigInterface $_scopeConfig;
    /**
     * Current payment method code
     *
     * @var string
     */
    protected string $_methodCode;
    /**
     * Current store id
     *
     * @var int
     */
    protected int $_storeId;
    /**
     * @var string
     */
    protected string $pathPattern;
    /**
     * @var MethodInterface
     */
    protected MethodInterface $methodInstance;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * Sets method instance used for retrieving method specific data
     *
     * @param MethodInterface $method
     *
     * @return AbstractConfig
     */
    public function setMethodInstance(MethodInterface $method): AbstractConfig
    {
        $this->methodInstance = $method;

        return $this;
    }

    /**
     * Method code setter
     *
     * @param string|MethodInterface $method
     *
     * @return AbstractConfig
     */
    public function setMethod(MethodInterface|string $method): AbstractConfig
    {
        if ($method instanceof MethodInterface) {
            $this->_methodCode = $method->getCode();
        } else {
            $this->_methodCode = $method;
        }

        return $this;
    }

    /**
     * Payment method instance code getter
     *
     * @return string
     */
    public function getMethodCode(): string
    {
        return $this->_methodCode;
    }

    /**
     * Store ID setter
     *
     * @param int $storeId
     *
     * @return AbstractConfig
     */
    public function setStoreId(int $storeId): AbstractConfig
    {
        $this->_storeId = $storeId;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue($field, $storeId = null)
    {
        $underscored = strtolower(preg_replace('/(.)([A-Z])/', "$1_$2", $field));
        $path = $this->_getSpecificConfigPath($underscored);

        if ($path !== null) {
            $value = $this->_scopeConfig->getValue(
                $path,
                ScopeInterface::SCOPE_STORE,
                $this->_storeId
            );

            return $this->_prepareValue($value);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function setMethodCode($methodCode): void
    {
        $this->_methodCode = $methodCode;
    }

    /**
     * {@inheritdoc}
     */
    public function setPathPattern($pathPattern): void
    {
        $this->pathPattern = $pathPattern;
    }


    /**
     * Check whether method available for checkout or not
     *
     * @param null $methodCode
     *
     * @return bool
     */
    public function isMethodAvailable($methodCode = null): bool
    {
        $methodCode = $methodCode ?: $this->_methodCode;

        return $this->isMethodActive($methodCode);
    }

    /**
     * Check whether method active in configuration and supported for merchant country or not
     *
     * @param string $method Method code
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function isMethodActive(string $method): bool
    {
        switch ($method) {
            case Config::METHOD_CODE:
                $isEnabled = $this->_scopeConfig->isSetFlag(
                        'payment/' . Config::METHOD_CODE . '/active',
                        ScopeInterface::SCOPE_STORE,
                        $this->_storeId
                    ) ||
                    $this->_scopeConfig->isSetFlag(
                        'payment/' . Config::METHOD_CODE . '/active',
                        ScopeInterface::SCOPE_STORE,
                        $this->_storeId
                    );
                $method = Config::METHOD_CODE;
                break;
            default:
                $isEnabled = $this->_scopeConfig->isSetFlag(
                    "payment/$method/active",
                    ScopeInterface::SCOPE_STORE,
                    $this->_storeId
                );
        }

        return $this->isMethodSupportedForCountry($method) && $isEnabled;
    }

    /**
     * Check whether method supported for specified country or not
     *
     * @param string|null $method
     * @param string|null $countryCode
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function isMethodSupportedForCountry(string $method = null, string $countryCode = null): bool
    {
        return true;
    }

    /**
     * Map any supported payment method into a config path by specified field name
     *
     * @param string $fieldName
     *
     * @return string|null
     */
    protected function _getSpecificConfigPath(string $fieldName): ?string
    {
        if ($this->pathPattern) {
            return sprintf($this->pathPattern, $this->_methodCode, $fieldName);
        }

        return "payment/$this->_methodCode/$fieldName";
    }

    /**
     * Perform additional config value preparation and return new value if needed
     *
     * @param string $value Old value
     *
     * @return string Modified value or old value
     */
    protected function _prepareValue(string $value): string
    {
        return $value;
    }
}
