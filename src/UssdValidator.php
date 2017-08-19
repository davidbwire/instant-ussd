<?php

namespace Bitmarshals\InstantUssd;

use Zend\Validator\InArray;

/**
 * Description of UssdValidator
 *
 * @author David Bwire
 */
abstract class UssdValidator {

    /**
     *
     * @var array 
     */
    protected $lastServedMenuConfig;

    /**
     *
     * @var string 
     */
    protected $lastServedMenuId;

    /**
     * 
     * @param string $lastServedMenuId
     * @param array $lastServedMenuConfig
     */
    public function __construct($lastServedMenuId, array $lastServedMenuConfig) {

        $this->lastServedMenuConfig = $lastServedMenuConfig;
        $this->lastServedMenuId     = $lastServedMenuId;
    }

    /**
     * @param array $ussdData
     */
    public abstract function isValidResponse(array &$ussdData);

    /**
     * Checks if $latestResponse is within acceptable range of valid values
     * 
     * @return boolean
     */
    protected function isWithinValidRange($latestResponse, array &$ussdData) {
        // set default validation state as valid
        // to prevent validation errors
        $isValid = true;
        // Validate the USSD keys provided
        if (array_key_exists('valid_keys', $this->lastServedMenuConfig) &&
                !empty($this->lastServedMenuConfig['valid_keys'])) {
            $validValues = $this->lastServedMenuConfig['valid_keys'];
            $isValid     = $this->inArrayValidation($validValues, $latestResponse);
            if (!$isValid) {
                $ussdData['error_message'] = "Invalid choice. Reply with " . reset($validValues) . '-' . end($validValues) . '.';
            }
        }
        // IMPORTANT set validity status to prevent invalid menu from being tracked/saved
        $ussdData['is_valid'] = $isValid;
        return $isValid;
    }

    /**
     * 
     * @param array $validValues
     * @param string $latestResponse
     * @return bool
     */
    protected function inArrayValidation(array $validValues, $latestResponse) {
        $inArrayValidator = new InArray();
        $inArrayValidator->setHaystack($validValues)
                ->setStrict(InArray::COMPARE_NOT_STRICT_AND_PREVENT_STR_TO_INT_VULNERABILITY);
        return $inArrayValidator->isValid($latestResponse);
    }

    /**
     * Example - Full name consists of First Name and Last Name (2 name parts)
     * 
     * @param string $latestResponse
     * @param array $ussdData
     * @return boolean
     */
    protected function fullNameValidation($latestResponse, array &$ussdData) {
        $isValid         = true;
        $trimmedResponse = trim($latestResponse);
        if (empty($trimmedResponse)) {
            $isValid                   = false;
            $ussdData['error_message'] = "Invalid name.";
            return false;
        }
        $nameParts = explode(" ", $trimmedResponse);
        // ensure you have both names by avoiding possible empty values
        foreach ($ussdData as $key => $namePart) {
            if (empty($namePart)) {
                unset($nameParts[$key]);
            }
        }
        $namePartCount = count($nameParts);

        if ($namePartCount < 2) {
            // set a custom error message
            $ussdData['error_message'] = "Invalid.";
            return false;
        }
        return true;
    }

}
