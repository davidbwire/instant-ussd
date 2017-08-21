# InstantUssd

<p align="center"><img src="https://avatars1.githubusercontent.com/u/30041331?v=4&s=80"></p>

InstantUssd is a USSD development library meant to provide you with a set of tools to help you easily and quickly build your own USSD applications.

## Goals

- Speed up USSD development
- Ease maintenance of USSD code

## Features

- Minimal coding (Provide USSD menus as config)
- Automatic screen to screen navigation
- Out of the box validation of user inputs
- Ready solutions for complex USSD flows involving going back and forth,
optional screens, looping set of screens,  jumping from screen to screen and 
resuming timed-out USSD sessions

## Usage

### Initialization

Instantiate `Bitmarshals\InstantUssd\InstantUssd` passing in `instant_ussd` config and your controller instance.

```php
// Within your Controller
use Bitmarshals\InstantUssd\InstantUssd;
use Bitmarshals\InstantUssd\Response;
use InstantUssd\UssdValidator;

$instantUssdConfig = $config['instant_ussd'];
$instantUssd       = new InstantUssd($instantUssdConfig, $this);

```

Retrieve an instance of `Bitmarshals\InstantUssd\UssdService` from `InstantUssd`. This service provides utilities for parsing, packaging and handling incoming USSD data.

```php
$ussdParams  = $_POST;
$ussdText    = $ussdParams['text'];

// package incoming data in a format instant-ussd understands
$ussdService = $instantUssd->getUssdService($ussdText);
$ussdData    = $ussdService->packageUssdData($ussdParams);

```
### Navigation Checks

Before proceeding further we need to run a few test to check if we should exit early, show home page (very first screen in a USSD flow) or navigate to the previous screen.

#### Exit Check
```php
// Should we EXIT early?
$isExitRequest = $ussdService->isExitRequest();
if ($isExitRequest === true) {
    return $instantUssd->exitUssd([])
                    ->send();
}
```
#### Home Page Check

```php
// Should we SHOW HOME Page?
$isFirstRequest       = $ussdService->isFirstRequest();
$userRequestsHomePage = $ussdService->isExplicitHomepageRequest();
if ($isFirstRequest || $userRequestsHomePage) {
    // set your home page
    $yourHomePage = "home_instant_ussd";
    return $instantUssd->showHomePage($ussdData, $yourHomePage)
                    ->send();
}
```
#### Go Back Check
```php
// Should we GO BACK?
$isGoBackRequest = $ussdService->isGoBackRequest();
if ($isGoBackRequest === true) {
    $resultGoBak = $instantUssd->goBack($ussdData);
    if ($resultGoBak instanceof Response) {
        return $resultGoBak->send();
    }
    // fallback to home page if previous menu missing
    return $instantUssd->showHomePage($ussdData, 'home_*')
                    ->send();
}
```
### Process Latest User Response

With the navigation checks complete, we should now handle the most recent user response.

#### Retrieve Last Served Menu and Its Config

`InstantUssd` keeps a history of the screens we've visited during the current session. Let's retrieve the menu we sent to our user and its config settings.

```php
// get last served menu_id from database
$lastServedMenuId = $instantUssd->retrieveLastServedMenuId($ussdData);
// check we got last_served_menu
if (empty($lastServedMenuId)) {
    // fallback to home page
    return $instantUssd->showHomePage($ussdData, 'home_*')
                    ->send();
}
// Get $lastServedMenuConfig. The config will used in validation trigger below
// Set $ussdData['menu_id'] to know the specific config to retreive
$ussdData['menu_id']  = $lastServedMenuId;
$lastServedMenuConfig = $instantUssd->retrieveMenuConfig($ussdData);
// check we have $lastServedMenuConfig
if (empty($lastServedMenuConfig)) {
    // fallback to home page
    return $instantUssd->showHomePage($ussdData, 'home_*')
                    ->send();
}
```
#### Validate Latest Response
```php
// VALIDATE incoming data
$validator = new UssdValidator($lastServedMenuId, $lastServedMenuConfig);
$isValid   = $validator->isValidResponse($ussdData);
if (!$isValid) {
    // handle invalid data
    $nextScreenId = $lastServedMenuId;
    // essentially we're re-rendering the menu with error message
    return $instantUssd->showNextScreen($ussdData, $nextScreenId)
                    ->send();
}
```
#### Capture Validated Data
```php
// send valid data FOR PROCESSING. Save to db, etc
// this step should give us a pointer to the next screen
$nextScreenId = $instantUssd->processIncomingData(
        $lastServedMenuId, $ussdData);
if (empty($nextScreenId)) {
    // we couldn't find the next screen
    return $instantUssd->showError($ussdData, "Error. "
                            . "Next screen could not be found.")
                    ->send();
}
```
#### Show Next Screen
```php
// we have the next screen; SHOW NEXT SCREEN
return $instantUssd->showNextScreen($ussdData, $nextScreenId)
                ->send();
```
### Sample Application using InstantUssd Library

Check out [InstantUssd App](https://github.com/davidbwire/instant-ussd-app).

## Documentation

Please refer to our extensive [Wiki documentation](https://github.com/bitmarshals/instant-ussd/wiki) for more information.
