Contao Facebook Login
=====================

Contao 4 bundle for a Facebook login.

## Installation

Require the bundle via composer:
```
composer require inspiredminds/contao-facebook-login
```
The execute the Contao Install Tool. If you use the Contao Standard Edition, you will have to add
```php
new FacebookLoginBundle\FacebookLoginBundle()
```
to your `AppKernel.php`.
