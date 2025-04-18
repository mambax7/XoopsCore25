<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty truncateHtml modifier plugin
 *
 * Type:     modifier
 * Name:     truncateHtml
 * Purpose:  Truncate an HTML string to a certain number of words, while ensuring that
 *           valid markup is maintained.
 * Example:  <{$body|truncateHtml:30:'...'}>
 *
 * @param string  $string HTML to be truncated
 * @param integer $count  truncate to $count words
 * @param string  $etc    ellipsis
 *
 * @return string
 */
function smarty_modifier_truncateHtml($string, $count = 80, $etc = '…')
{
    if($count <= 0) {
        return '';
    }
    return BaseStringHelper::truncateWords($string, $count, $etc, true);
}

if (!class_exists('\HTMLPurifier_Bootstrap', false)) {
    require_once XOOPS_TRUST_PATH . '/vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php';
}

if (!class_exists('\BaseStringHelper', false)) {
    /**
     * The Yii framework is free software. It is released under the terms of the following BSD License.
     *
     * Copyright © 2008-2018 by Yii Software LLC, All rights reserved.
     *
     * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the
     * following conditions are met:
     *
     * - Redistributions of source code must retain the above copyright notice, this list of
     *   conditions and the following disclaimer.
     * - Redistributions in binary form must reproduce the above copyright notice, this list of
     *   conditions and the following disclaimer in the documentation and/or other materials provided
     *   with the distribution.
     * - Neither the name of Yii Software LLC nor the names of its contributors may be used to endorse
     *   or promote products derived from this software without specific prior written permission.
     *
     * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS
     * OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
     * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR
     * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
     * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
     * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
     * WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY
     * WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
     */
    class BaseStringHelper
    {
        /**
         * Returns the number of bytes in the given string.
         * This method ensures the string is treated as a byte array by using `mb_strlen()`.
         *
         * @param string $string the string being measured for length
         *
         * @return int the number of bytes in the given string.
         */
        public static function byteLength($string)
        {
            return mb_strlen($string, '8bit');
        }

        /**
         * Returns the portion of string specified by the start and length parameters.
         * This method ensures the string is treated as a byte array by using `mb_substr()`.
         *
         * @param string $string the input string. Must be one character or longer.
         * @param int    $start  the starting position
         * @param int    $length the desired portion length. If not specified or `null`, there will be
         *                       no limit on length i.e. the output will be until the end of the string.
         *
         * @return string the extracted part of string, or FALSE on failure or an empty string.
         * @see http://www.php.net/manual/en/function.substr.php
         */
        public static function byteSubstr($string, $start, $length = null)
        {
            return mb_substr($string, $start, $length ?? mb_strlen($string, '8bit'), '8bit');
        }

        /**
         * Returns the trailing name component of a path.
         * This method is similar to the php function `basename()` except that it will
         * treat both \ and / as directory separators, independent of the operating system.
         * This method was mainly created to work on php namespaces. When working with real
         * file paths, php's `basename()` should work fine for you.
         * Note: this method is not aware of the actual filesystem, or path components such as "..".
         *
         * @param string $path   A path string.
         * @param string $suffix If the name component ends in suffix this will also be cut off.
         *
         * @return string the trailing name component of the given path.
         * @see http://www.php.net/manual/en/function.basename.php
         */
        public static function basename($path, $suffix = '')
        {
            if (($len = mb_strlen($suffix)) > 0 && mb_substr($path, -$len) === $suffix) {
                $path = mb_substr($path, 0, -$len);
            }
            $path = rtrim(str_replace('\\', '/', $path), '/\\');
            if (($pos = mb_strrpos($path, '/')) !== false) {
                return mb_substr($path, $pos + 1);
            }

            return $path;
        }

        /**
         * Returns parent directory's path.
         * This method is similar to `dirname()` except that it will treat
         * both \ and / as directory separators, independent of the operating system.
         *
         * @param string $path A path string.
         *
         * @return string the parent directory's path.
         * @see http://www.php.net/manual/en/function.basename.php
         */
        public static function dirname($path)
        {
            $pos = mb_strrpos(str_replace('\\', '/', $path), '/');
            if ($pos !== false) {
                return mb_substr($path, 0, $pos);
            }

            return '';
        }

        /**
         * Truncates a string to the number of characters specified.
         *
         * @param string $string   The string to truncate.
         * @param int    $length   How many characters from original string to include into truncated string.
         * @param string $suffix   String to append to the end of truncated string.
         * @param string $encoding The charset to use, defaults to charset currently used by application.
         * @param bool   $asHtml   Whether to treat the string being truncated as HTML and preserve proper HTML tags.
         *                         This parameter is available since version 2.0.1.
         *
         * @return string the truncated string.
         */
        public static function truncate($string, $length, $suffix = '...', $encoding = null, $asHtml = false)
        {
            if ($encoding === null) {
                $encoding = 'UTF-8';
            }
            if ($asHtml) {
                return static::truncateHtml($string, $length, $suffix, $encoding);
            }

            if (mb_strlen($string, $encoding) > $length) {
                return rtrim(mb_substr($string, 0, $length, $encoding)) . $suffix;
            }

            return $string;
        }

        /**
         * Truncates a string to the number of words specified.
         *
         * @param string $string The string to truncate.
         * @param int    $count  How many words from original string to include into truncated string.
         * @param string $suffix String to append to the end of truncated string.
         * @param bool   $asHtml Whether to treat the string being truncated as HTML and preserve proper HTML tags.
         *                       This parameter is available since version 2.0.1.
         *
         * @return string the truncated string.
         */
        public static function truncateWords($string, $count, $suffix = '...', $asHtml = false)
        {
            if ($asHtml) {
                return static::truncateHtml($string, $count, $suffix);
            }

            $words = preg_split('/(\s+)/u', trim($string), -1, PREG_SPLIT_DELIM_CAPTURE);
            if (count($words) / 2 > $count) {
                return implode('', array_slice($words, 0, ($count * 2) - 1)) . $suffix;
            }

            return $string;
        }

        /**
         * Truncate a string while preserving the HTML.
         *
         * @param string      $string The string to truncate
         * @param int         $count
         * @param string      $suffix String to append to the end of the truncated string.
         * @param string|bool $encoding
         *
         * @return string
         * @since 2.0.1
         */
        protected static function truncateHtml($string, $count, $suffix, $encoding = false)
        {
            $config = \HTMLPurifier_Config::create(null);
            $lexer = \HTMLPurifier_Lexer::create($config);
            $tokens = $lexer->tokenizeHTML($string, $config, new \HTMLPurifier_Context());
            $openTokens = [];
            $totalCount = 0;
            $depth = 0;
            $truncated = [];
            foreach ($tokens as $token) {
                if ($token instanceof \HTMLPurifier_Token_Start) { //Tag begins
                    $openTokens[$depth] = $token->name;
                    $truncated[] = $token;
                    ++$depth;
                } elseif ($token instanceof \HTMLPurifier_Token_Text && $totalCount <= $count) { //Text
                    if (false === $encoding) {
                        preg_match('/^(\s*)/um', $token->data, $prefixSpace) ?: $prefixSpace = ['', ''];
                        $token->data = $prefixSpace[1] . self::truncateWords(ltrim($token->data), $count - $totalCount, '');
                        $currentCount = self::countWords($token->data);
                    } else {
                        $token->data = self::truncate($token->data, $count - $totalCount, '', $encoding);
                        $currentCount = mb_strlen($token->data, $encoding);
                    }
                    $totalCount += $currentCount;
                    $truncated[] = $token;
                } elseif ($token instanceof \HTMLPurifier_Token_End) { //Tag ends
                    if ($token->name === $openTokens[$depth - 1]) {
                        --$depth;
                        unset($openTokens[$depth]);
                        $truncated[] = $token;
                    }
                } elseif ($token instanceof \HTMLPurifier_Token_Empty) { //Self contained tags, i.e. <img/> etc.
                    $truncated[] = $token;
                }
                if ($totalCount >= $count) {
                    if (0 < count($openTokens)) {
                        krsort($openTokens);
                        foreach ($openTokens as $name) {
                            $truncated[] = new \HTMLPurifier_Token_End($name);
                        }
                    }
                    break;
                }
            }
            $context = new \HTMLPurifier_Context();
            $generator = new \HTMLPurifier_Generator($config, $context);
            return $generator->generateFromTokens($truncated) . ($totalCount >= $count ? $suffix : '');
        }

        /**
         * Check if given string starts with specified substring.
         * Binary and multibyte safe.
         *
         * @param string $string        Input string
         * @param string $with          Part to search inside the $string
         * @param bool   $caseSensitive Case-sensitive search. Default is true. When case-sensitive is enabled, $with must exactly match the starting of the string in order to get a true value.
         *
         * @return bool Returns true if first input starts with second input, false otherwise
         */
        public static function startsWith($string, $with, $caseSensitive = true)
        {
            if (!$bytes = static::byteLength($with)) {
                return true;
            }
            if ($caseSensitive) {
                return strncmp($string, $with, $bytes) === 0;
            }
            $encoding = 'UTF-8';
            return mb_strtolower(mb_substr($string, 0, $bytes, '8bit'), $encoding) === mb_strtolower($with, $encoding);
        }

        /**
         * Check if given string ends with specified substring.
         * Binary and multibyte safe.
         *
         * @param string $string        Input string to check
         * @param string $with          Part to search inside the $string.
         * @param bool   $caseSensitive Case-sensitive search. Default is true. When case-sensitive is enabled, $with must exactly match the ending of the string in order to get a true value.
         *
         * @return bool Returns true if first input ends with second input, false otherwise
         */
        public static function endsWith($string, $with, $caseSensitive = true)
        {
            if (!$bytes = static::byteLength($with)) {
                return true;
            }
            if ($caseSensitive) {
                // Warning check, see http://php.net/manual/en/function.substr-compare.php#refsect1-function.substr-compare-returnvalues
                if (static::byteLength($string) < $bytes) {
                    return false;
                }

                return substr_compare($string, $with, -$bytes, $bytes) === 0;
            }

            $encoding = 'UTF-8';
            return mb_strtolower(mb_substr($string, -$bytes, mb_strlen($string, '8bit'), '8bit'), $encoding) === mb_strtolower($with, $encoding);
        }

        /**
         * Explodes string into array, optionally trims values and skips empty ones.
         *
         * @param string $string    String to be exploded.
         * @param string $delimiter Delimiter. Default is ','.
         * @param mixed  $trim      Whether to trim each element. Can be:
         *                          - boolean - to trim normally;
         *                          - string - custom characters to trim. Will be passed as a second argument to `trim()` function.
         *                          - callable - will be called for each value instead of trim. Takes the only argument - value.
         * @param bool   $skipEmpty Whether to skip empty strings between delimiters. Default is false.
         *
         * @return array
         * @since 2.0.4
         */
        public static function explode($string, $delimiter = ',', $trim = true, $skipEmpty = false)
        {
            $result = explode($delimiter, $string);
            if ($trim) {
                if ($trim === true) {
                    $trim = 'trim';
                } elseif (!is_callable($trim)) {
                    $trim = fn($v) => trim($v, $trim);
                }
                $result = array_map($trim, $result);
            }
            if ($skipEmpty) {
                // Wrapped with array_values to make array keys sequential after empty values removing
                $result = array_values(array_filter($result, fn($value) => $value !== ''));
            }

            return $result;
        }

        /**
         * Counts words in a string.
         *
         * @since 2.0.8
         *
         * @param string $string
         *
         * @return int
         */
        public static function countWords($string)
        {
            return count(preg_split('/\s+/u', $string, -1, PREG_SPLIT_NO_EMPTY));
        }

        /**
         * Returns string representation of number value with replaced commas to dots, if decimal point
         * of current locale is comma.
         *
         * @param int|float|string $value
         *
         * @return string
         * @since 2.0.11
         */
        public static function normalizeNumber($value)
        {
            $value = (string)$value;

            $localeInfo = localeconv();
            $decimalSeparator = $localeInfo['decimal_point'] ?? null;

            if ($decimalSeparator !== null && $decimalSeparator !== '.') {
                $value = str_replace($decimalSeparator, '.', $value);
            }

            return $value;
        }

        /**
         * Encodes string into "Base 64 Encoding with URL and Filename Safe Alphabet" (RFC 4648).
         *
         * > Note: Base 64 padding `=` may be at the end of the returned string.
         * > `=` is not transparent to URL encoding.
         *
         * @see   https://tools.ietf.org/html/rfc4648#page-7
         *
         * @param string $input the string to encode.
         *
         * @return string encoded string.
         * @since 2.0.12
         */
        public static function base64UrlEncode($input)
        {
            return strtr(base64_encode($input), '+/', '-_');
        }

        /**
         * Decodes "Base 64 Encoding with URL and Filename Safe Alphabet" (RFC 4648).
         *
         * @see   https://tools.ietf.org/html/rfc4648#page-7
         *
         * @param string $input encoded string.
         *
         * @return string decoded string.
         * @since 2.0.12
         */
        public static function base64UrlDecode($input)
        {
            return base64_decode(strtr($input, '-_', '+/'));
        }

        /**
         * Safely casts a float to string independent of the current locale.
         *
         * The decimal separator will always be `.`.
         *
         * @param float|int $number a floating point number or integer.
         *
         * @return string the string representation of the number.
         * @since 2.0.13
         */
        public static function floatToString($number)
        {
            // . and , are the only decimal separators known in ICU data,
            // so it's safe to call str_replace here
            return str_replace(',', '.', (string)$number);
        }

        /**
         * Checks if the passed string would match the given shell wildcard pattern.
         * This function emulates [[fnmatch()]], which may be unavailable at certain environment, using PCRE.
         *
         * @param string $pattern the shell wildcard pattern.
         * @param string $string  the tested string.
         * @param array  $options options for matching. Valid options are:
         *
         * - caseSensitive: bool, whether pattern should be case-sensitive. Defaults to `true`.
         * - escape: bool, whether backslash escaping is enabled. Defaults to `true`.
         * - filePath: bool, whether slashes in string only matches slashes in the given pattern. Defaults to `false`.
         *
         * @return bool whether the string matches pattern or not.
         * @since 2.0.14
         */
        public static function matchWildcard($pattern, $string, $options = [])
        {
            if ($pattern === '*' && empty($options['filePath'])) {
                return true;
            }

            $replacements = [
                '\\\\\\\\' => '\\\\',
                '\\\\\\*' => '[*]',
                '\\\\\\?' => '[?]',
                '\*' => '.*',
                '\?' => '.',
                '\[\!' => '[^',
                '\[' => '[',
                '\]' => ']',
                '\-' => '-',
            ];

            if (isset($options['escape']) && !$options['escape']) {
                unset($replacements['\\\\\\\\']);
                unset($replacements['\\\\\\*']);
                unset($replacements['\\\\\\?']);
            }

            if (!empty($options['filePath'])) {
                $replacements['\*'] = '[^/\\\\]*';
                $replacements['\?'] = '[^/\\\\]';
            }

            $pattern = strtr(preg_quote($pattern, '#'), $replacements);
            $pattern = '#^' . $pattern . '$#us';

            if (isset($options['caseSensitive']) && !$options['caseSensitive']) {
                $pattern .= 'i';
            }

            return preg_match($pattern, $string) === 1;
        }
    }
}
