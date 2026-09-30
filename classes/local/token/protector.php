<?php
// This file is part of Moodle - http://moodle.org/

namespace local_coursetranslate\local\token;

use moodle_exception;

/**
 * Protect non-translatable structures before text is sent to AI.
 *
 * Every HTML tag is tokenised byte-for-byte. This is intentionally stricter
 * than asking the model to "preserve HTML": the model never receives writable
 * markup in the first place.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class protector {
    /** Token prefix. */
    private const PREFIX = '__CTP_';

    /**
     * Protect a field.
     *
     * @param string $text Source text.
     * @param array $terminology Required source => target terminology.
     * @return array Protected payload.
     */
    public function protect(string $text, array $terminology = []): array {
        $state = [
            'source' => $text,
            'tokens' => [],
            'tagtokens' => [],
            'next' => 1,
        ];
        $protected = $text;

        // Entire code-like blocks first, before generic HTML tags.
        foreach ([
            '/<pre\b[^>]*>.*?<\/pre>/isu',
            '/<code\b[^>]*>.*?<\/code>/isu',
            '/```.*?```/su',
            '/`[^`\r\n]+`/u',
            '/\$\$.*?\$\$/su',
            '/\\\(.*?\\\)/su',
            '/\\\[.*?\\\]/su',
        ] as $pattern) {
            $protected = $this->replace_pattern($protected, $pattern, 'code', $state);
        }

        // Comments and all HTML tags, including attributes/URLs/IDs inside them.
        $protected = $this->replace_pattern($protected, '/<!--.*?-->/su', 'tag', $state);
        $protected = $this->replace_pattern($protected, '/<[^>]+>/u', 'tag', $state);

        // Moodle/file/link constructs outside tags.
        foreach ([
            '/@@PLUGINFILE@@(?:\/[^\s"\'<>)]*)?/u',
            '/https?:\/\/[^\s"\'<>]+/iu',
            '/\bwww\.[^\s"\'<>]+/iu',
            '/\[\[[^\]\r\n]+\]\]/u',
            '/\{\/?mlang(?:\s+[^}]*)?\}/iu',
            '/\{\{\{.*?\}\}\}/su',
            '/\{\{.*?\}\}/su',
            '/\$a(?:->[A-Za-z_][A-Za-z0-9_]*)?/u',
            '/%(?:\d+\$)?[bcdeEfFgGosuxX]/u',
            '/\{[A-Za-z_][A-Za-z0-9_.:\-]*\}/u',
            '/\b[A-Za-z_][A-Za-z0-9_]*::[A-Za-z_][A-Za-z0-9_]*\b/u',
            '/(?<![\w.])[A-Za-z0-9][A-Za-z0-9._-]*\.(?:pdf|docx?|xlsx?|pptx?|odt|ods|odp|zip|rar|7z|tar|gz|png|jpe?g|gif|svg|webp|mp[34]|m4a|wav|ogg|webm|csv|json|xml|html?|php|js|css|scss|sql)(?![\w.])/iu',
        ] as $pattern) {
            $protected = $this->replace_pattern($protected, $pattern, 'literal', $state);
        }

        // Mandatory terminology becomes a protected token whose restored value is
        // the required target term, guaranteeing consistency even if the model
        // would otherwise choose a synonym.
        uksort($terminology, static fn(string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        foreach ($terminology as $source => $target) {
            $source = (string)$source;
            if ($source === '' || !str_contains($protected, $source)) {
                continue;
            }
            $protected = $this->replace_literal($protected, $source, (string)$target, 'terminology', $state);
        }

        $state['text'] = $protected;
        unset($state['next']);
        return $state;
    }

    /**
     * Restore a translated protected field after strict validation.
     *
     * @param array $payload Payload returned by protect().
     * @param string $translated Protected translated text.
     * @return string Restored field.
     */
    public function restore(array $payload, string $translated): string {
        $expected = array_keys($payload['tokens']);
        preg_match_all('/' . preg_quote(self::PREFIX, '/') . '\d{6}__/', $translated, $matches);
        $found = $matches[0];

        foreach ($expected as $token) {
            if (substr_count($translated, $token) !== 1) {
                throw new moodle_exception('protectionerror', 'local_coursetranslate', '', 'missing or duplicated token ' . $token);
            }
        }
        foreach ($found as $token) {
            if (!array_key_exists($token, $payload['tokens'])) {
                throw new moodle_exception('protectionerror', 'local_coursetranslate', '', 'unknown token ' . $token);
            }
        }

        $translatedtags = array_values(array_filter(
            $found,
            static fn(string $token): bool => in_array($token, $payload['tagtokens'], true)
        ));
        if ($translatedtags !== array_values($payload['tagtokens'])) {
            throw new moodle_exception('protectionerror', 'local_coursetranslate', '', 'HTML tag order changed');
        }
        if ($this->tag_region_signature($payload['text'], $payload['tagtokens']) !==
                $this->tag_region_signature($translated, $payload['tagtokens'])) {
            throw new moodle_exception('protectionerror', 'local_coursetranslate', '', 'text moved across HTML boundaries');
        }
        foreach ($payload['tokens'] as $token => $data) {
            if ($data['kind'] === 'tag') {
                continue;
            }
            if ($this->tag_region_index($payload['text'], $token, $payload['tagtokens']) !==
                    $this->tag_region_index($translated, $token, $payload['tagtokens'])) {
                throw new moodle_exception('protectionerror', 'local_coursetranslate', '',
                    'protected token moved across HTML boundaries: ' . $token);
            }
        }

        // New markup/URLs/placeholders are not accepted. Original ones are currently tokens.
        if (preg_match('/<\/?[A-Za-z][^>]*>/u', $translated) ||
                preg_match('/https?:\/\//iu', $translated) ||
                preg_match('/\bwww\./iu', $translated) ||
                str_contains($translated, '@@PLUGINFILE@@') ||
                str_contains($translated, '{{') ||
                str_contains($translated, '[[')) {
            throw new moodle_exception('protectionerror', 'local_coursetranslate', '', 'unprotected markup, URL or placeholder introduced');
        }

        $replacements = [];
        foreach ($payload['tokens'] as $token => $data) {
            $replacements[$token] = $data['restore'];
        }
        $restored = strtr($translated, $replacements);

        if ($this->html_signature($payload['source']) !== $this->html_signature($restored)) {
            throw new moodle_exception('protectionerror', 'local_coursetranslate', '', 'HTML signature differs from source');
        }
        return $restored;
    }

    /**
     * Replace regex matches by unique tokens.
     *
     * @param string $text Text.
     * @param string $pattern Regex.
     * @param string $kind Token kind.
     * @param array $state Mutable token state.
     * @return string
     */
    private function replace_pattern(string $text, string $pattern, string $kind, array &$state): string {
        $parts = preg_split('/(' . preg_quote(self::PREFIX, '/') . '\d{6}__)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $text;
        }
        foreach ($parts as &$part) {
            if (preg_match('/^' . preg_quote(self::PREFIX, '/') . '\d{6}__$/', $part)) {
                continue;
            }
            $part = preg_replace_callback($pattern, function(array $matches) use ($kind, &$state): string {
                return $this->new_token($matches[0], $matches[0], $kind, $state);
            }, $part) ?? $part;
        }
        unset($part);
        return implode('', $parts);
    }

    /**
     * Replace every literal occurrence with a token restoring to another value.
     *
     * @param string $text Text.
     * @param string $search Source literal.
     * @param string $restore Restore value.
     * @param string $kind Token kind.
     * @param array $state Mutable token state.
     * @return string
     */
    private function replace_literal(
        string $text,
        string $search,
        string $restore,
        string $kind,
        array &$state
    ): string {
        $parts = preg_split('/(' . preg_quote(self::PREFIX, '/') . '\d{6}__)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $text;
        }
        foreach ($parts as &$part) {
            if (preg_match('/^' . preg_quote(self::PREFIX, '/') . '\d{6}__$/', $part)) {
                continue;
            }
            $offset = 0;
            while (($pos = strpos($part, $search, $offset)) !== false) {
                $token = $this->new_token($search, $restore, $kind, $state);
                $part = substr($part, 0, $pos) . $token . substr($part, $pos + strlen($search));
                $offset = $pos + strlen($token);
            }
        }
        unset($part);
        return implode('', $parts);
    }

    /**
     * Create one token.
     *
     * @param string $original Original bytes.
     * @param string $restore Restore bytes.
     * @param string $kind Kind.
     * @param array $state Mutable state.
     * @return string
     */
    private function new_token(string $original, string $restore, string $kind, array &$state): string {
        $token = self::PREFIX . str_pad((string)$state['next']++, 6, '0', STR_PAD_LEFT) . '__';
        $state['tokens'][$token] = [
            'original' => $original,
            'restore' => $restore,
            'kind' => $kind,
        ];
        if ($kind === 'tag') {
            $state['tagtokens'][] = $token;
        }
        return $token;
    }


    /**
     * Record whether each region delimited by HTML-tag tokens contains textual content.
     *
     * This prevents a model from keeping the same tag order while moving text outside
     * the element that originally contained it.
     *
     * @param string $text Protected text.
     * @param array $tagtokens Ordered tag tokens.
     * @return array<bool>
     */
    private function tag_region_signature(string $text, array $tagtokens): array {
        if (!$tagtokens) {
            return [trim($this->strip_all_tokens($text)) !== ''];
        }
        $pattern = '/(' . implode('|', array_map(static fn(string $token): string => preg_quote($token, '/'), $tagtokens)) . ')/';
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return [];
        }
        $signature = [];
        $buffer = '';
        foreach ($parts as $part) {
            if (in_array($part, $tagtokens, true)) {
                $signature[] = trim($this->strip_all_tokens($buffer)) !== '';
                $buffer = '';
            } else {
                $buffer .= $part;
            }
        }
        $signature[] = trim($this->strip_all_tokens($buffer)) !== '';
        return $signature;
    }

    /**
     * Find the HTML-tag region containing one protected token.
     *
     * @param string $text Protected text.
     * @param string $needle Protected token.
     * @param array $tagtokens Ordered tag tokens.
     * @return int
     */
    private function tag_region_index(string $text, string $needle, array $tagtokens): int {
        $pos = strpos($text, $needle);
        if ($pos === false) {
            return -1;
        }
        $prefix = substr($text, 0, $pos);
        $count = 0;
        foreach ($tagtokens as $tagtoken) {
            $count += substr_count($prefix, $tagtoken);
        }
        return $count;
    }

    /**
     * Remove all internal protection tokens from a protected fragment.
     *
     * @param string $text Protected text.
     * @return string
     */
    private function strip_all_tokens(string $text): string {
        return preg_replace('/' . preg_quote(self::PREFIX, '/') . '\d{6}__/', '', $text) ?? $text;
    }

    /**
     * Exact ordered HTML tag signature.
     *
     * @param string $text Text.
     * @return array
     */
    private function html_signature(string $text): array {
        preg_match_all('/<[^>]+>/u', $text, $matches);
        return $matches[0];
    }
}
