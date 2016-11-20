<?php
/**
 * Created by PhpStorm.
 * User: Tom Reinders
 * Date: 20-11-2016
 * Time: 16:58
 */

namespace JeroenDesloovere\VCard;


class VCardParserModel extends \stdClass {
    /**
     * @var string
     */
    public $fullname;

    /**
     * @var string
     */
    public $lastname;

    /**
     * @var string
     */
    public $firstname;

    /**
     * @var string
     */
    public $additional;

    /**
     * @var string
     */
    public $prefix;

    /**
     * @var string
     */
    public $suffix;

    /**
     * @var \DateTime
     */
    public $birthday;

    /**
     * @var array
     */
    public $address;

    /**
     * @var array
     */
    public $phone;

    /**
     * @var array
     */
    public $email;

    /**
     * @var string
     */
    public $revision;

    /**
     * @var string
     */
    public $version;

    /**
     * @var string
     */
    public $organization;

    /**
     * @var array
     */
    public $url;

    /**
     * @var string
     */
    public $title;

    public $rawPhoto;

    public $photo;

    public $rawLogo;

    public $logo;

    /**
     * @var string
     */
    public $note;

    /**
     * @var string
     */
    public $geo;

    /**
     * @var string
     */
    public $gender;

    /**
     * @var array
     */
    public $nickname;

    /**
     * @var array
     */
    public $skype;

    /**
     * @var array
     */
    public $item;
}