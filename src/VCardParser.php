<?php

namespace JeroenDesloovere\VCard;

/*
 * This file is part of the VCard PHP Class from Jeroen Desloovere.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use Iterator;

/**
 * VCard PHP Class to parse .vcard files.
 *
 * This class is heavily based on the Zendvcard project (seemingly abandoned),
 * which is licensed under the Apache 2.0 license.
 * More information can be found at https://code.google.com/archive/p/zendvcard/
 */
class VCardParser implements Iterator
{
    /**
     * The raw VCard content.
    *
     * @var string
     */
    protected $content;

    /**
     * The VCard data objects.
     *
     * @var array
     */
    protected $vcardObjects;

    /**
     * The iterator position.
     *
     * @var int
     */
    protected $position;

    /**
     * Helper function to parse a file directly.
     *
     * @param string $filename
     * @return self
     * @throws \RuntimeException
     */
    public static function parseFromFile($filename)
    {
        if (file_exists($filename) && is_readable($filename)) {
            return new self(file_get_contents($filename));
        } else {
            throw new \RuntimeException(sprintf("File %s is not readable, or doesn't exist.", $filename));
        }
    }

    public function __construct($content)
    {
        $this->content = $content;
        $this->vcardObjects = [];
        $this->rewind();
        $this->parse();
    }

    public function rewind()
    {
        $this->position = 0;
    }

    /**
     * @return \JeroenDesloovere\VCard\VCardParserModel
     * @throws \OutOfBoundsException
     */
    public function current()
    {
        if ($this->valid()) {
            return $this->getCardAtIndex($this->position);
        }
    }

    /**
     * @return int
     */
    public function key()
    {
        return $this->position;
    }

    public function next()
    {
        $this->position++;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return !empty($this->vcardObjects[$this->position]);
    }

    /**
     * Fetch all the imported VCards.
     *
     * @return array
     *    A list of VCard card data objects.
     */
    public function getCards()
    {
        return $this->vcardObjects;
    }

    /**
     * Fetch the imported VCard at the specified index.
     *
     * @throws \OutOfBoundsException
     *
     * @param int $i
     *
     * @return \JeroenDesloovere\VCard\VCardParserModel
     *    The card data object.
     */
    public function getCardAtIndex($i)
    {
        if (isset($this->vcardObjects[$i])) {
            return $this->vcardObjects[$i];
        }
        throw new \OutOfBoundsException();
    }

    /**
     * Start the parsing process.
     *
     * This method will populate the data object.
     */
    protected function parse()
    {
        // Normalize new lines.
        $this->content = str_replace(["\r\n", "\r"], "\n", $this->content);

        // RFC2425 5.8.1. Line delimiting and folding
        // Unfolding is accomplished by regarding CRLF immediately followed by
        // a white space character (namely HTAB ASCII decimal 9 or. SPACE ASCII
        // decimal 32) as equivalent to no characters at all (i.e., the CRLF
        // and single white space character are removed).
        $this->content = preg_replace("/\n(?:[ \t])/", '', $this->content);

        $this->content = preg_replace("/=\n=/", '=', $this->content);
        $lines = explode("\n", $this->content);

        $cardData = null;

        // Parse the VCard, line by line.
        foreach ($lines as $line) {
            $line = trim($line);

            if (strtoupper($line) === 'BEGIN:VCARD') {
                $cardData = new VCardParserModel();
            } elseif (strtoupper($line) === 'END:VCARD') {
                $this->vcardObjects[] = $cardData;
                $cardData = null;
            } elseif (!empty($line)) {
                $type = '';
                $value = '';
                @list($type, $value) = explode(':', $line, 2);

                $types = explode(';', $type);
                $element = strtoupper($types[0]);

                array_shift($types);
                $i = 0;
                $rawValue = false;
                foreach ($types as $type) {
                    if (preg_match('/base64/', strtolower($type))) {
                        $value = base64_decode($value);
                        unset($types[$i]);
                        $rawValue = true;
                    } elseif (preg_match('/encoding=b/', strtolower($type))) {
                        $value = base64_decode($value);
                        unset($types[$i]);
                        $rawValue = true;
                    } elseif (preg_match('/quoted-printable/', strtolower($type))) {
                        $value = quoted_printable_decode($value);
                        unset($types[$i]);
                        $rawValue = true;
                    } elseif (strpos(strtolower($type), 'charset=') === 0) {
                        try {
                            $value = mb_convert_encoding($value, 'UTF-8', substr($type, 8));
                        } catch (\Exception $e) {
                        }
                        unset($types[$i]);
                    }
                    $i++;
                }

                if (preg_match('/^item(\d{1,2})\.([^\:]+)$/', $element, $matches)) {
                    list($all, $itemidx, $part) = $matches;

                    switch ($part) {
                        case 'URL':
                            if (!is_array($cardData->item)) {
                                $cardData->item = array();
                            }
                            $value = explode(';', $value);
                            if (count($value) === 1) {
                                $value = preg_replace(array('_$!<', '>!$_'), '', $value);
                                $value = preg_replace('\\:', ':', $value);
                            }
                            $cardData->item[$itemidx][$part] = $value;
                            break;
                    }
                } else {
                    switch (strtoupper($element)) {
                        case 'FN':
                            $cardData->fullname = $value;
                            break;
                        case 'N':
                            foreach ($this->parseName($value) as $key => $val) {
                                $cardData->{$key} = $val;
                            }
                            break;
                        case 'BDAY':
                            $cardData->birthday = $this->parseBirthday($value);
                            break;
                        case 'ADR':
                            if (!is_array($cardData->address)) {
                                $cardData->address = [];
                            }
                            $key = !empty($types) ? implode(';', $types) : 'default;undefined';
                            $cardData->address[$key][] = $this->parseAddress($value);
                            break;
                        case 'TEL':
                            if (!is_array($cardData->phone)) {
                                $cardData->phone = [];
                            }
                            $key = !empty($types) ? implode(';', $types) : 'default;undefined';
                            $cardData->phone[$key][] = $value;
                            break;
                        case 'EMAIL':
                            if (!is_array($cardData->email)) {
                                $cardData->email = [];
                            }
                            $key = !empty($types) ? implode(';', $types) : 'default;undefined';
                            $cardData->email[$key][] = $value;
                            break;
                        case 'REV':
                            $cardData->revision = $value;
                            break;
                        case 'VERSION':
                            $cardData->version = $value;
                            break;
                        case 'ORG':
                            $cardData->organization = $value;
                            break;
                        case 'URL':
                            if (!is_array($cardData->url)) {
                                $cardData->url = [];
                            }
                            $key = !empty($types) ? implode(';', $types) : 'default;undefined';
                            $cardData->url[$key][] = $value;
                            break;
                        case 'TITLE':
                            $cardData->title = $value;
                            break;
                        case 'PHOTO':
                            if ($rawValue) {
                                $cardData->rawPhoto = $value;
                            } else {
                                $cardData->photo = $value;
                            }
                            break;
                        case 'LOGO':
                            if ($rawValue) {
                                $cardData->rawLogo = $value;
                            } else {
                                $cardData->logo = $value;
                            }
                            break;
                        case 'NOTE':
                            $value = str_replace(array("\\:", "\\,"), array(':', ','), $value);
                            $cardData->note = $this->unescape($value);
                            break;
                        case 'CATEGORIES':
                            $cardData->categories = array_map('trim', explode(',', $value));
                            break;
                        case 'GEO':
                            $cardData->geo = $value;
                            break;
                        case 'GENDER':
                            $cardData->gender = $value;
                            break;
                        case 'NICKNAME':
                            if (!is_array($cardData->nickname)) {
                                $cardData->nickname = array();
                            }
                            $cardData->nickname[] = $value;
                            break;
                        case 'X-SKYPE':
                        case 'X-SKYPE-USERNAME':
                            if (!is_array($cardData->skype)) {
                                $cardData->skype = array();
                            }
                            $cardData->skype[] = $value;
                            break;
                        case 'X-ANDROID-CUSTOM':
                            $values = explode(';', $value);
                            switch ($values[0]) {
                                case 'vnd.android.cursor.item/nickname':
                                    if (!is_array($cardData->nickname)) {
                                        $cardData->nickname = array();
                                    }
                                    $cardData->nickname[] = $values[1];
                                    break;
                            }
                            break;
                    }
                }
            }
        }
    }

    /**
     * @param string $value
     * @return object
     */
    protected function parseName($value)
    {
        @list(
            $lastname,
            $firstname,
            $additional,
            $prefix,
            $suffix
        ) = explode(';', $value);
        return (object) [
            'lastname' => $lastname,
            'firstname' => $firstname,
            'additional' => $additional,
            'prefix' => $prefix,
            'suffix' => $suffix,
        ];
    }

    /**
     * @param string $value
     * @return \DateTime
     */
    protected function parseBirthday($value)
    {
        return new \DateTime($value);
    }

    /**
     * @param string $value
     * @return object
     */
    protected function parseAddress($value)
    {
        @list(
            $name,
            $extended,
            $street,
            $city,
            $region,
            $zip,
            $country,
        ) = explode(';', $value);
        return (object) [
            'name' => $name,
            'extended' => $extended,
            'street' => $street,
            'city' => $city,
            'region' => $region,
            'zip' => $zip,
            'country' => $country,
        ];
    }

    /**
     * Unescape newline characters according to RFC2425 section 5.8.4.
     * This function will replace escaped line breaks with PHP_EOL.
     *
     * @link http://tools.ietf.org/html/rfc2425#section-5.8.4
     * @param  string $text
     * @return string
     */
    protected function unescape($text)
    {
        return str_replace("\\n", PHP_EOL, $text);
    }
}
