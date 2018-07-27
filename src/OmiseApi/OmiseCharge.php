<?php

namespace OmiseApi;

use OmiseApi\Res\OmiseApiResource;

class OmiseCharge extends OmiseApiResource
{
    const ENDPOINT = 'charges';

    /**
     * Retrieves a charge.
     *
     * @param  string $id
     * @param  string $publickey
     * @param  string $secretkey
     *
     * @return OmiseCharge
     */
    public static function retrieve($id = '', $publickey = null, $secretkey = null)
    {
        return parent::g_retrieve(get_class(), self::getUrl($id), $publickey, $secretkey);
    }

    /**
     * @param  string $id
     *
     * @return string
     */
    private static function getUrl($id = '')
    {
        return OMISE_API_URL . self::ENDPOINT . '/' . $id;
    }

    public static function retrieve_dtac($id = '', $isproxy, $proxyurl, $key)
    {
        return parent::g_retrieve_dtac(get_class(), self::getUrl($id), $isproxy, $proxyurl, $key);
    }

    /**
     * Creates a new charge.
     *
     * @param  array $params
     * @param  string $publickey
     * @param  string $secretkey
     *
     * @return OmiseCharge
     */
    public static function create($params, $url, $isproxy, $proxyurl)
    {
        //get Token from Dtac
        // return parent::g_create(get_class(), self::getUrl(), $params, $publickey, $secretkey);
        return parent::dtac_create(get_class(), $params, $url, $isproxy, $proxyurl);
    }

    /**
     * (non-PHPdoc)
     *
     * @see OmiseApiResource::g_reload()
     */
    public function reload()
    {
        if ($this['object'] === 'charge') {
            parent::g_reload(self::getUrl($this['id']));
        } else {
            parent::g_reload(self::getUrl());
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see OmiseApiResource::g_update()
     */
    public function update($params)
    {
        parent::g_update(self::getUrl($this['id']), $params);
    }

    /**
     * Captures a charge.
     *
     * @return OmiseCharge
     */
    public function capture()
    {
        $result = parent::execute(self::getUrl($this['id']) . '/capture', parent::REQUEST_POST,
            parent::getResourceKey());
        $this->refresh($result);

        return $this;
    }

    /**
     * Reverses a charge.
     *
     * @return OmiseCharge
     */
    public function reverse()
    {
        $result = parent::execute(self::getUrl($this['id']) . '/reverse', parent::REQUEST_POST,
            parent::getResourceKey());
        $this->refresh($result);

        return $this;
    }

    /**
     * list refunds
     *
     * @return OmiseRefundList
     */
    public function refunds()
    {
        $result = parent::execute(self::getUrl($this['id']) . '/refunds', parent::REQUEST_GET,
            parent::getResourceKey());

        return new OmiseRefundList($result, $this['id'], $this->_publickey, $this->_secretkey);
    }
}
