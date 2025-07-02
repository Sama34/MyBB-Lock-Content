<?php

/***************************************************************************
 *
 *    Lock Content plugin (/inc/plugins/lock/shortcodes.class.php)
 *    Author: Neko
 *    Maintainer: © 2024 Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    Allow users to hide content in their posts in exchange for replies or NewPoints currency.
 *
 ***************************************************************************
 ****************************************************************************
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 ****************************************************************************/

declare(strict_types=1);

// stop direct access to the file.
use Random\RandomException;

if (!defined('IN_MYBB')) {
    die('no');
}

/**
 * Shortcodes
 *
 * @package shortcodes
 * @author cronhound/senpai & WordPress Team
 **/
class Shortcodes
{
    public array $shortCodes = [];

    public static bool $strict = true;

    /**
     * @throws RandomException
     */
    public function __construct(
        public readonly string $lock_tag = 'hide',
        private string $highlightReplacement = '',
        private readonly string $defaultCallback = 'LockContent\Core\hideMessageContents',
    ) {
        if ($this->highlightReplacement === '') {
            $this->refresh_highlight_replacement();
        }

        $this->add($this->get_lock_tag(), $this->defaultCallback);
    }

    public function get_lock_tag(): string
    {
        return $this->lock_tag;
    }

    /**
     * @throws RandomException
     */
    public function refresh_highlight_replacement(): void
    {
        $this->highlightReplacement = bin2hex(random_bytes(10));
    }

    public function get_highlight_replacement(): string
    {
        return $this->highlightReplacement;
    }

    private function add(string $shortCode, callable $callback): void
    {
        if (is_callable($callback)) {
            $this->shortCodes[$shortCode] = $callback;
        }
    }

    //everything below this line was pretty much pulled from wordpress, no need to reinvent the wheel.
    private function shortcode_regex(): string
    {
        $tagnames = array_keys($this->shortCodes);
        $tagregexp = join('|', array_map('preg_quote', $tagnames));

        // WARNING! Do not change this regex without changing do_shortcode_tag() and strip_shortcode_tag()
        // Also, see shortcode_unautop() and shortcode.js.
        return
            '\\['                              // Opening bracket
            . '(\\[?)'                           // 1: Optional second opening bracket for escaping shortcodes: [[tag]]
            . "($tagregexp)"                     // 2: Shortcode name
            . '(?![\\w-])'                       // Not followed by word character or hyphen
            . '('                                // 3: Unroll the loop: Inside the opening shortcode tag
            . '[^\\]\\/]*'                   // Not a closing bracket or forward slash
            . '(?:'
            . '\\/(?!\\])'               // A forward slash not followed by a closing bracket
            . '[^\\]\\/]*'               // Not a closing bracket or forward slash
            . ')*?'
            . ')'
            . '(?:'
            . '(\\/)'                        // 4: Self closing tag ...
            . '\\]'                          // ... and closing bracket
            . '|'
            . '\\]'                          // Closing bracket
            . '(?:'
            . '('                        // 5: Unroll the loop: Optionally, anything between the opening and closing shortcode tags
            . '[^\\[]*+'             // Not an opening bracket
            . '(?:'
            . '\\[(?!\\/\\2\\])' // An opening bracket not followed by the closing shortcode tag
            . '[^\\[]*+'         // Not an opening bracket
            . ')*+'
            . ')'
            . '\\[\\/\\2\\]'             // Closing shortcode tag
            . ')?'
            . ')'
            . '(\\]?)';                          // 6: Optional second closing brocket for escaping shortcodes: [[tag]]
    }

    public function parse(string $message): string
    {
        if (!str_contains($message, '[')) {
            return $message;
        }

        if (empty($this->shortCodes)) {
            return $message;
        }

        $pattern = self::shortcode_regex();

        return preg_replace_callback("/$pattern/s", Shortcodes::run_shortcode(...), $message);
    }

    private function run_shortcode(array $message): string
    {
        // allow [[foo]] syntax for escaping a tag
        if ($message[1] === '[' && $message[6] === ']') {
            return substr($message[0], 1, -1);
        }

        $foundTag = $message[2];

        $attributes = self::fetch_attributes($message[3]);

        if (isset($message[5])) {
            // enclosing tag - extra parameter
            return $message[1] . call_user_func(
                    $this->shortCodes[$foundTag],
                    $attributes,
                    $message[5],
                    $foundTag
                ) . $message[6];
        } else {
            // self-closing tag
            return $message[1] . call_user_func(
                    $this->shortCodes[$foundTag],
                    $attributes,
                    null,
                    $foundTag
                ) . $message[6];
        }
    }

    private static function fetch_attributes(string $text): array
    {
        $attributes = [];

        $pattern = '/(\w+)\s*=\s*"([^"]*)"(?:\s|$)|(\w+)\s*=\s*\'([^\']*)\'(?:\s|$)|(\w+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|(\S+)(?:\s|$)/';

        $text = preg_replace('/[\x{00a0}\x{200b}]+/u', ' ', $text);

        if (preg_match_all($pattern, $text, $match, PREG_SET_ORDER)) {
            foreach ($match as $m) {
                if (!empty($m[1])) {
                    $attributes[strtolower($m[1])] = stripcslashes($m[2]);
                } elseif (!empty($m[3])) {
                    $attributes[strtolower($m[3])] = stripcslashes($m[4]);
                } elseif (!empty($m[5])) {
                    $attributes[strtolower($m[5])] = stripcslashes($m[6]);
                } elseif (isset($m[7]) and strlen($m[7])) {
                    $attributes[] = stripcslashes($m[7]);
                } elseif (isset($m[8])) {
                    $attributes[] = stripcslashes($m[8]);
                }
            }
        } else {
            $attributes[] = ltrim($text);
        }

        return $attributes;
    }

    public function get_higher_points_from_message(string $message, float &$higherContentPoints): float
    {
        $pattern = self::shortcode_regex();

        preg_match_all("/$pattern/s", $message, $matches, PREG_SET_ORDER);

        $higherContentPoints = 0;

        foreach ($matches as $match) {
            if (
                empty($match[0]) ||
                my_strpos($match[0], '[' . $this->get_lock_tag() . '=') === false ||
                !($content_points = (float)str_replace('=', '', $match[3]))
            ) {
                continue;
            }

            $higherContentPoints = max($higherContentPoints, $content_points);
        }

        return $higherContentPoints;
    }
} // END class Shortcodes
