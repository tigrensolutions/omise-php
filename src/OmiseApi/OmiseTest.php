<?php

namespace OmiseApi;

use OmiseApi\Res\OmiseApiResource;

class OmiseTest extends OmiseApiResource
{
    /**
     * (non-PHPdoc)
     *
     * @see OmiseApiResource::getInstance()
     */
    public static function resource()
    {
        return parent::getInstance(get_class());
    }
}
