<?php

namespace giantbits\crelish\recaptcha;

use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * RecaptchaConfig component for Google reCAPTCHA v3
 *
 * Configure in your application config:
 * ```php
 * 'components' => [
 *     'recaptcha' => [
 *         'class' => \giantbits\crelish\recaptcha\RecaptchaConfig::class,
 *         'siteKey' => 'your-site-key',
 *         'secret' => 'your-secret-key',
 *         'scoreThreshold' => 0.5, // optional, default 0.5
 *     ],
 * ]
 * ```
 */
class RecaptchaConfig extends Component
{
    /**
     * @var string Google reCAPTCHA v3 site key
     */
    public $siteKey;

    /**
     * @var string Google reCAPTCHA v3 secret key
     */
    public $secret;

    /**
     * @var float Minimum score threshold (0.0 - 1.0). Default 0.5
     * Higher = more strict (more likely to be human)
     */
    public $scoreThreshold = 0.5;

    /**
     * @var string Google reCAPTCHA API URL
     */
    public $apiUrl = 'https://www.google.com/recaptcha/api.js';

    /**
     * @var string Google reCAPTCHA verify URL
     */
    public $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * @var bool Whether to verify hostname matches
     */
    public $verifyHostname = false;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if (empty($this->siteKey)) {
            throw new InvalidConfigException('RecaptchaConfig::$siteKey must be set.');
        }

        if (empty($this->secret)) {
            throw new InvalidConfigException('RecaptchaConfig::$secret must be set.');
        }
    }
}
