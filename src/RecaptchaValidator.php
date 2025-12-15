<?php

namespace giantbits\crelish\recaptcha;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Yii;
use yii\validators\Validator;

/**
 * RecaptchaValidator validates Google reCAPTCHA v3 responses
 *
 * Usage in model rules:
 * ```php
 * public function rules()
 * {
 *     return [
 *         ['recaptcha', RecaptchaValidator::class],
 *         // Or with custom threshold:
 *         ['recaptcha', RecaptchaValidator::class, 'scoreThreshold' => 0.7],
 *     ];
 * }
 * ```
 */
class RecaptchaValidator extends Validator
{
    /**
     * @var string|null Secret key. If null, will be loaded from recaptcha component.
     */
    public $secret;

    /**
     * @var float|null Minimum score threshold (0.0 - 1.0). If null, uses component default.
     */
    public $scoreThreshold;

    /**
     * @var string|null Expected action name. If null, action is not verified.
     */
    public $action;

    /**
     * @var bool Whether to verify hostname
     */
    public $verifyHostname = false;

    /**
     * @var string Component name in Yii application
     */
    public $componentName = 'recaptcha';

    /**
     * @var string Google reCAPTCHA verify URL
     */
    public $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * @var array|null Last verification response from Google (for debugging)
     */
    public $lastResponse;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        // Load config from component if not set
        $component = Yii::$app->get($this->componentName, false);
        if ($component instanceof RecaptchaConfig) {
            if ($this->secret === null) {
                $this->secret = $component->secret;
            }
            if ($this->scoreThreshold === null) {
                $this->scoreThreshold = $component->scoreThreshold;
            }
            if ($this->verifyUrl === null) {
                $this->verifyUrl = $component->verifyUrl;
            }
            if ($this->verifyHostname === null) {
                $this->verifyHostname = $component->verifyHostname;
            }
        }

        // Set default threshold if still null
        if ($this->scoreThreshold === null) {
            $this->scoreThreshold = 0.5;
        }

        if (empty($this->secret)) {
            throw new \yii\base\InvalidConfigException(
                'RecaptchaValidator::$secret must be set or configure the recaptcha component.'
            );
        }

        if ($this->message === null) {
            $this->message = Yii::t('yii', 'The verification code is incorrect.');
        }
    }

    /**
     * @inheritdoc
     */
    public function validateAttribute($model, $attribute)
    {
        $value = $model->$attribute;

        if (empty($value)) {
            $this->addError($model, $attribute, 'reCAPTCHA verification failed. Please try again.');
            return;
        }

        $result = $this->verifyToken($value);

        if ($result === false) {
            $this->addError($model, $attribute, $this->message);
        }
    }

    /**
     * @inheritdoc
     */
    protected function validateValue($value)
    {
        if (empty($value)) {
            return ['reCAPTCHA verification failed. Please try again.', []];
        }

        $result = $this->verifyToken($value);

        if ($result === false) {
            return [$this->message, []];
        }

        return null;
    }

    /**
     * Verify the reCAPTCHA token with Google's API
     *
     * @param string $token The reCAPTCHA response token
     * @return bool Whether verification succeeded
     */
    protected function verifyToken($token)
    {
        try {
            $client = new Client(['timeout' => 10]);

            $response = $client->post($this->verifyUrl, [
                'form_params' => [
                    'secret' => $this->secret,
                    'response' => $token,
                    'remoteip' => Yii::$app->request->userIP,
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            $this->lastResponse = $result;

            // Check success
            if (empty($result['success'])) {
                Yii::warning('reCAPTCHA verification failed: ' . json_encode($result), __METHOD__);
                return false;
            }

            // Check score threshold
            if (isset($result['score']) && $result['score'] < $this->scoreThreshold) {
                Yii::warning(
                    "reCAPTCHA score {$result['score']} below threshold {$this->scoreThreshold}",
                    __METHOD__
                );
                return false;
            }

            // Verify action if specified
            if ($this->action !== null && isset($result['action'])) {
                if ($result['action'] !== $this->action) {
                    Yii::warning(
                        "reCAPTCHA action mismatch: expected {$this->action}, got {$result['action']}",
                        __METHOD__
                    );
                    return false;
                }
            }

            // Verify hostname if enabled
            if ($this->verifyHostname && isset($result['hostname'])) {
                $expectedHostname = Yii::$app->request->hostName;
                if ($result['hostname'] !== $expectedHostname) {
                    Yii::warning(
                        "reCAPTCHA hostname mismatch: expected {$expectedHostname}, got {$result['hostname']}",
                        __METHOD__
                    );
                    return false;
                }
            }

            return true;

        } catch (GuzzleException $e) {
            Yii::error('reCAPTCHA API request failed: ' . $e->getMessage(), __METHOD__);
            return false;
        } catch (\Exception $e) {
            Yii::error('reCAPTCHA verification error: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }
}
