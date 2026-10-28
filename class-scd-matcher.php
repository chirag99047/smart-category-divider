<?php
/** Deterministic, local matching. No category-size quotas or external services. */
defined('ABSPATH') || exit;
class SCD_Matcher {
    public static function clean($text) {
        $text = preg_replace('~<(script|style|nav|footer)\b[^>]*>.*?</\1>~is', ' ', $text);
        $text = preg_replace('~<[^>]+>~u', ' ', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
    public static function phrases($input) {
        return array_values(array_unique(array_filter(array_map(array(__CLASS__, 'clean'), preg_split('/[,\r\n]+/', $input)))));
    }
    private static function has($text, $phrase) {
        return $phrase !== '' && preg_match('~(?<![\p{L}\p{N}])' . preg_quote($phrase, '~') . '(?![\p{L}\p{N}])~u', $text);
    }
    public static function suggest($title, $html, $rules) {
        $title = self::clean($title);
        $safe = preg_replace('~<(script|style|nav|footer)\b[^>]*>.*?</\1>~is', ' ', $html);
        preg_match_all('~<h[1-6]\b[^>]*>(.*?)</h[1-6]>~is', $safe, $headings);
        $heading = self::clean(implode(' ', $headings[1]));
        $body = self::clean($safe);
        $candidates = array();
        foreach ($rules as $id => $rule) {
            if (empty($rule['enabled'])) { continue; }
            $exclude = self::phrases($rule['exclude']);
            $blocked = false;
            foreach ($exclude as $phrase) { if (self::has($title . ' ' . $body, $phrase)) { $blocked = true; break; } }
            if ($blocked) { continue; }
            $score = 0; $strong = false; $hits = array();
            foreach (self::phrases($rule['include']) as $phrase) {
                if (self::has($title, $phrase)) { $score += 10; $strong = true; $hits[] = $phrase . ' (title)'; }
                elseif (self::has($heading, $phrase)) { $score += 6; $strong = true; $hits[] = $phrase . ' (heading)'; }
                elseif (self::has($body, $phrase)) { $score += 1; $hits[] = $phrase . ' (body only)'; }
            }
            if ($score) { $candidates[] = array('id' => (int)$id, 'score' => $score, 'strong' => $strong, 'hits' => $hits, 'ancestors' => $rule['ancestors']); }
        }
        usort($candidates, function($a, $b) { return $b['score'] <=> $a['score']; });
        // Prefer a strongly matched descendant over its broadly matched ancestor.
        $specific = array_values(array_filter($candidates, function($candidate) use ($candidates) {
            foreach ($candidates as $other) {
                if ($other['strong'] && in_array($candidate['id'], $other['ancestors'], true)) { return false; }
            }
            return true;
        }));
        if (!$specific) { return array('category' => 0, 'reason' => 'No matching category. Add a relevant rule or choose manually.', 'candidates' => array()); }
        $top = $specific[0];
        $reason = implode('; ', $top['hits']);
        if (!$top['strong'] || $top['score'] < 6) {
            return array('category' => 0, 'reason' => 'Weak evidence: ' . $reason, 'candidates' => $specific);
        }
        if (isset($specific[1]) && $specific[1]['score'] >= $top['score'] * 0.65) {
            return array('category' => 0, 'reason' => 'Competing categories: review the main subject. Best evidence: ' . $reason, 'candidates' => $specific);
        }
        return array('category' => $top['id'], 'reason' => $reason, 'candidates' => $specific);
    }
}
