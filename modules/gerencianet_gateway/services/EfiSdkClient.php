<?php

declare(strict_types=1);
defined('BASEPATH') or exit('No direct script access allowed');
require_once dirname(__DIR__) . '/vendor/autoload.php';

/** Preserve bundled SDK behavior without PHP8.2+ dynamic-property creation. */
final class EfiSdkClient extends \Gerencianet\Gerencianet
{
    public array $options = [];
}
