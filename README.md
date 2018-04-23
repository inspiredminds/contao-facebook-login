[![](https://img.shields.io/maintenance/yes/2018.svg)](https://github.com/inspiredminds/contao-facebook-login)
[![](https://img.shields.io/packagist/v/inspiredminds/contao-facebook-login.svg)](https://packagist.org/packages/inspiredminds/contao-facebook-login)
[![](https://img.shields.io/packagist/dt/inspiredminds/contao-facebook-login.svg)](https://packagist.org/packages/inspiredminds/contao-facebook-login)

Contao Facebook Login
=====================

Contao 4 bundle for a Facebook login.

## Installation

Require the bundle via composer:
```
composer require inspiredminds/contao-facebook-login
```
If you use the Contao Standard Edition, you will have to add
```php
new FacebookLoginBundle\FacebookLoginBundle()
```
to your `AppKernel.php`. You will also need to add the bundle's routes to your `app/config/routing.yml`:
```yaml
FacebookLoginBundle:
    resource: "@FacebookLoginBundle/Resources/config/routing.yml"
```
Then execute the Contao Install Tool (regardless of which edition you use). 

## Usage instructions

### Facebook App

Before being able to use the Facebook login, you have to create a Facebook App for your website under [developers.facebook.com/apps](https://developers.facebook.com/apps). Make sure to fill out at least the following fields:

* _Settings_ » _Basic_ » _Add Platform_ » _Website_: enter the basic URL of your site, e.g. `http://example.org`.
* _Settings_ » _Basic_ » _App Domains_: fill in the domain of your site, e.g. `example.org`.

Then you need to configure the _Facebook Login_ on the left side under _PRODUCTS_. If said product is not there, you need to add it manually via _+ Add Product_ first. Make sure to fill out _Valid OAuth URIs_ with the the following URL of your site: `http://example.org/fblogincallback`. If you are using `https`, use that instead (or both). Set the rest of the settings as seen in the screenshot below:

![Facebook Login settings](https://github.com/inspiredminds/contao-facebook-login/raw/master/facebook-login-settings.png)

_Use Strict Mode for Redirect URIs_ can be enabled (new Facebook apps will have this enabled by default). Then make your App public under _App Review_.

### Contao configuration

After creating the Facebook App, you need to set the Facebook App ID and Facebook App Secret. You can define these either in the website root or in the system settings. Use the former if you are using a multidomain setup.

### Facebook Login module

Simply create a Facebook Login module in your theme and include it anywhere. It works and behaves the same way as the regular login module of Contao and also offers the same settings, plus the ability to define the member groups newly registered users will belong to and the ability to decide what personal data should be stored from Facebook.

You can also define additional [Facebook Login Permissions](https://developers.facebook.com/docs/facebook-login/permissions). These will be added to the default permissions. The module will also save a user access token in the session under the variable `facebook_login_access_token` for later use.

## Attributions

This bundle uses code provided and originally used by [Kamil Kuzminski](https://github.com/qzminski).
