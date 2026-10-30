<?php

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

class CustomAttributeSanitizer implements AttributeSanitizerInterface
{
    private $html;

    /**
     * Constructor to initialize with HTML content.
     * 
     * @param string $html
     */
    public function __construct(string $html)
    {
        $this->html = $html;
    }

    /**
     * Extracts all 'data-*' attributes from the HTML content.
     *
     * @return array|null
     */
    public function extractDataAttributes(): ?array
    {
        // Use regex to extract all 'data-*' attributes from the HTML
        preg_match_all('/\bdata-([a-zA-Z0-9_-]*)="[^"]*"/', $this->html, $matches);

        if (isset($matches[1]) && count($matches[1]) > 0) {
            return array_unique($matches[1]); // Return unique 'data-*' attribute names
        }

        return null;
    }

    /**
     * Extracts all 'aria-*' attributes from the HTML content.
     *
     * @return array|null
     */
    public function extractAriaAttributes(): ?array
    {
        // Use regex to extract all 'data-*' attributes from the HTML
        preg_match_all('/\baria-([a-zA-Z0-9_-]*)="[^"]*"/', $this->html, $matches);

        if (isset($matches[1]) && count($matches[1]) > 0) {
            return array_unique($matches[1]); // Return unique 'aria-*' attribute names
        }

        return null;
    }

    /**
     * Returns the list of element names supported, or null to support all elements.
     *
     * @return list<string>|null
     */
    public function getSupportedElements(): ?array
    {
        return null; // Support all elements
    }

    /**
     * Returns the list of attributes names supported, or null to support all attributes.
     *
     * @return list<string>|null
     */
    public function getSupportedAttributes(): ?array
    {
        $attributes = [];

        // Build the full list of 'data-*' attributes by appending 'data-' prefix
        $dataAttributes = $this->extractDataAttributes();

        if ($dataAttributes) {
            $dataAttributes =  array_map(function ($attr) {
                return 'data-' . $attr; // Prepend 'data-' to each attribute
            }, $dataAttributes);
            $attributes = array_merge($attributes, $dataAttributes);
        } else {
            $dataAttributes = [];
        }

        // Build the full list of 'data-*' attributes by appending 'data-' prefix
        $ariaAttributes = $this->extractAriaAttributes();

        if ($ariaAttributes) {
            $ariaAttributes =  array_map(function ($attr) {
                return 'aria-' . $attr; // Prepend 'aria-' to each attribute
            }, $ariaAttributes);
        } else {
            $ariaAttributes = [];
        }

        return array_merge($dataAttributes, $ariaAttributes); // No data-* attributes found, allow all attributes
    }

    /**
     * Returns the sanitized value of a given attribute for the given element.
     *
     * @param string $element
     * @param string $attribute
     * @param string $value
     * @param HtmlSanitizerConfig $config
     *
     * @return string|null
     */
    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        // Only sanitize 'data-*' attributes by returning their values with enhanced sanitization
        if (strpos($attribute, 'data-') === 0 || strpos($attribute, 'aria-') === 0) {
            // Escaping HTML entities and removing unsafe characters
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }

        return null; // For non-data-* attributes, do not allow
    }
}