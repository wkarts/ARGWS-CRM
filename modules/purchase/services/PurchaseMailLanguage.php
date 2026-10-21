<?php

declare(strict_types=1);

/** Bounded vendor-language filter. Never applies a vendor preference to another mail class. */
final class PurchaseMailLanguage
{
    private const VENDOR_TEMPLATES = [
        'purchase_order_to_contact' => 'purchase-order-to-contact',
        'purchase_request_to_contact' => 'purchase-request-to-contact',
        'purchase_quotation_to_contact' => 'purchase-quotation-to-contact',
        'purchase_contract_to_contact' => 'purchase-contract-to-contact',
        'purchase_statement_to_contact' => 'purchase-statement-to-contact',
        'debit_note_to_contact' => 'debit-note-to-contact',
        'vendor_registration_confirmed' => 'vendor-registration-confirmed',
        'vendor_welcome_new_contact' => 'new-contact-created',
    ];

    public static function resolve(string $language, array $context, string $activeLanguage, callable $lookup): string
    {
        $template = $context['template'] ?? null;
        if (!is_object($template)) return $language;
        $class = get_class($template);
        $slug = $template->slug ?? null;
        if (strcasecmp($class, 'App_mail_template') === 0) {
            // Native preview uses the generic base object with an already-reflected mail class.
            $class = $context['template_class'] ?? '';
            $slug = $context['template_slug'] ?? null;
            if (!is_string($class) || !class_exists($class, false) || !is_subclass_of($class, 'App_mail_template')) return $language;
        }
        $expectedSlug = self::VENDOR_TEMPLATES[strtolower($class)] ?? null;
        if ($expectedSlug === null || $slug !== $expectedSlug) return $language;
        // An earlier recipient-specific resolver/filter has already made an explicit choice.
        if ($language !== '' && $language !== $activeLanguage) return $language;
        $email = $context['email'] ?? null;
        if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) return $language;
        $contacts = $lookup($email);
        if (!is_array($contacts) || count($contacts) !== 1) return $language;
        $preference = $contacts[0]['default_language'] ?? '';
        if (!is_string($preference) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/D', $preference)
            || !is_dir(APPPATH.'language/'.$preference)) return $language;
        return $preference;
    }
}
