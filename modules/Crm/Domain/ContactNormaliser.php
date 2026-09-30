<?php

namespace Modules\Crm\Domain;

/**
 * Turns however a human typed a contact detail into one comparable form.
 *
 * Duplicate detection (spec §8) is only as good as this. "Jane@Example.com " and
 * "jane@example.com" are the same person; so are "(512) 555-0134", "512-555-0134" and
 * "+1 512 555 0134". Without normalisation the merge feature would find almost nothing and
 * the customer book would fill with near-identical rows.
 *
 * Deliberately conservative: it makes strings comparable, it does not attempt to validate
 * them or guess what was meant. A phone number that normalises to nothing usable is left as
 * null rather than turned into a wrong number.
 */
final class ContactNormaliser
{
    /**
     * Lowercased and trimmed. No further cleverness.
     *
     * Gmail's dot-and-plus aliasing is deliberately NOT collapsed: jane+dog@gmail.com and
     * jane@gmail.com are the same inbox at Gmail but not at most other providers, and
     * treating two addresses as one person when they are not is the expensive direction of
     * this mistake — it merges two real customers.
     */
    public static function email(?string $email): ?string
    {
        $email = trim((string) $email);

        if ($email === '') {
            return null;
        }

        return mb_strtolower($email);
    }

    /**
     * Digits only, with the US country code dropped so it compares equal either way.
     *
     * Spec §1 is explicit that this is a US-market product, so assuming NANP here is
     * reasonable. The rule is narrow on purpose: eleven digits starting with 1 loses the 1;
     * anything else is kept whole, so an international number is never mangled into a
     * different valid number.
     */
    public static function phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        // Too short to identify anybody. Better to hold nothing than to match two customers
        // on a fragment like "0134".
        if (strlen($digits) < 7) {
            return null;
        }

        return $digits;
    }

    /**
     * Collapses case, accents and spacing so names can be compared.
     *
     * Only ever used as one weak signal alongside email or phone — never on its own. Two
     * people called John Smith are extremely ordinary, and merging them would combine two
     * families' pets into one record.
     */
    public static function name(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '';
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $name = $ascii === false ? $name : $ascii;

        $name = preg_replace('/[^a-zA-Z\s]/', '', $name) ?? $name;
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        return mb_strtolower(trim($name));
    }
}
