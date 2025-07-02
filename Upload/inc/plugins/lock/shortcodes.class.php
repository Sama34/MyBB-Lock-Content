<?php

/***************************************************************************
 *
 *    Lock plugin (/inc/plugins/lock/shortcodes.class.php)
 *    Author: Neko
 *    Maintainer: Omar Gonzalez
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
    public static bool $strict = true;

    public function __construct(
        public string $tag = 'hide',
        private string $highlight_replacement = '',
        private array $shortcodes = [],
    ) {
        if ($highlight_replacement === '') {
            $this->refresh_highlight_replacement();
        }

        $this->add($this->get_tag(), 'lock_hide');
    }

    public function get_tag(): string
    {
        return $this->tag;
    }

    public function refresh_highlight_replacement(): void
    {
        $this->highlight_replacement = substr(
            str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWYXZ', 20)),
            0,
            20
        );
    }

    public function get_highlight_replacement(): string
    {
        return $this->highlight_replacement;
    }

    private function add(string $shortcode, string $function): void
    {
        if (is_callable($function)) {
            $this->shortcodes[$shortcode] = $function;
        }
    }

    //everything below this line was pretty much pulled from wordpress, no need to reinvent the wheel.
    private function shortcode_regex(): string
    {
        $tagnames = array_keys($this->shortcodes);
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

        if (empty($this->shortcodes)) {
            return $message;
        }

        $pattern = self::shortcode_regex();

        return preg_replace_callback("/$pattern/s", Closure::fromCallable([self::class, 'run_shortcode']), $message);
    }

    private function run_shortcode(array $m): string
    {
        // allow [[foo]] syntax for escaping a tag
        if ($m[1] === '[' && $m[6] === ']') {
            return substr($m[0], 1, -1);
        }

        $foundTag = $m[2];
        $attr = self::fetch_attributes($m[3]);

        if (isset($m[5])) {
            // enclosing tag - extra parameter
            return $m[1] . call_user_func($this->shortcodes[$foundTag], $attr, $m[5], $foundTag) . $m[6];
        } else {
            // self-closing tag
            return $m[1] . call_user_func($this->shortcodes[$foundTag], $attr, null, $foundTag) . $m[6];
        }
    }

    private static function fetch_attributes(string $text): array
    {
        $atts = [];
        $pattern = '/(\w+)\s*=\s*"([^"]*)"(?:\s|$)|(\w+)\s*=\s*\'([^\']*)\'(?:\s|$)|(\w+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|(\S+)(?:\s|$)/';
        $text = preg_replace('/[\x{00a0}\x{200b}]+/u', ' ', $text);
        if (preg_match_all($pattern, $text, $match, PREG_SET_ORDER)) {
            foreach ($match as $m) {
                if (!empty($m[1])) {
                    $atts[strtolower($m[1])] = stripcslashes($m[2]);
                } elseif (!empty($m[3])) {
                    $atts[strtolower($m[3])] = stripcslashes($m[4]);
                } elseif (!empty($m[5])) {
                    $atts[strtolower($m[5])] = stripcslashes($m[6]);
                } elseif (isset($m[7]) and strlen($m[7])) {
                    $atts[] = stripcslashes($m[7]);
                } elseif (isset($m[8])) {
                    $atts[] = stripcslashes($m[8]);
                }
            }
        } else {
            $atts[] = ltrim($text);
        }

        return $atts;
    }

    public function validate_post(PostDataHandler &$ph): PostDataHandler
    {
        global $mybb, $lang;

        if (
            !empty($mybb->usergroup['lock_maxcost']) === '' ||
            !function_exists('newpoints_format_points') ||
            $ph->data['uid'] !== $mybb->user['uid'] && is_moderator(
                $ph->data['fid']
            ) // but moderators could bypass this in others's posts? ...
        ) {
            return $ph;
        }

        $message = $ph->data['message'];

        if (
            empty($this->shortcodes) ||
            my_strpos($message, '[' . $this->get_tag()) === false
        ) {
            return $ph;
        }

        $price = 0;

        shortcodeObject()->get_higher_price_from_message($message, $price);

        if (!empty($mybb->usergroup['lock_maxcost']) && $price > (int)$mybb->usergroup['lock_maxcost'] && function_exists(
                'newpoints_format_points'
            )) {
            isset($lang->lock) || $lang->load('lock');

            $cost = newpoints_format_points((float)$mybb->usergroup['lock_maxcost']);

            $ph->set_error($lang->sprintf($lang->lock_permission_maxcost, strip_tags($cost)));
        }

        return $ph;
    }

    public function get_higher_price_from_message(string $message, int &$higher_price): int
    {
        $pattern = self::shortcode_regex();

        preg_match_all("/$pattern/s", $message, $matches, PREG_SET_ORDER);

        $higher_price = 0;

        foreach ($matches as $match) {
            if (
                empty($match[0]) ||
                my_strpos($match[0], '[' . $this->get_tag() . '=') === false ||
                !($price = (int)str_replace('=', '', $match[3]))
            ) {
                continue;
            }

            $higher_price = max($higher_price, $price);
        }

        return $higher_price;
    }
} // END class Shortcodes
