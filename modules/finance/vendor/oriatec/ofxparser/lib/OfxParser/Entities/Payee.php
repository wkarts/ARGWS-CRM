<?php

namespace OfxParser\Entities;

class Payee extends AbstractEntity
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var array
     */
    public $address;

    /**
     * @var string
     */
    public $city;

    /**
     * @var string
     */
    public $state;

    /**
     * @var string
     */
    public $postalCode;

    /**
     * @var string
     */
    public $country;

    /**
     * @var string
     */
    public $phone;
}
