<p align="center">
    <img src="https://github.com/devsquad-cockpit/laravel/blob/develop/cockpit-logo.png?raw=true" alt="Cockpit" title="Cockpit" width="300"/>
</p>

<p align="center" style="margin-top: 6px; margin-bottom: 10px;">
    <a href="https://devsquad.com">
        <img src="https://github.com/devsquad-cockpit/laravel/blob/develop/devsquad-logo.png?raw=true" alt="DevSquad" title="DevSquad" width="150"/>
    </a>
</p>

DebugMate is a beautiful error tracking package that will help your software team to track and fix errors.

## Table Of Compatibility
| Laravel Version   | DebugMate Version |
|-------------------|-------------------|
| ^10               | ^2.0              |
| ^11               | ^3.0              |

#### Now you can install the package:

```bash
composer require debugmate/laravel
```

#### Run the following command to install the package files:

```bash
php artisan debugmate:install
```

#### Configuring DebugMate connection
After the installation, you should configure the connection with DebugMate main application.
Open your `.env` file and check for this new env vars:

```env
DEBUGMATE_DOMAIN=
DEBUGMATE_ENABLED=
DEBUGMATE_TOKEN=
```
__`DEBUGMATE_DOMAIN`__: You must set your DebugMate domain on this var. This way, our package will know where it should send the error data.
If your DebugMate instance runs on a port different than the 80 or 443, you should add it too. E.g.: `http://debugmate.mydomain.com:9001`.

__`DEBUGMATE_ENABLED`__: With this var, you can control if DebugMate features will be available or not.

__`DEBUGMATE_TOKEN`__: On this var, you should set the project token. With this, you instruct DebugMate
in which project the errors will be attached.

## Reporting unhandled exceptions
You need to add the DebugMate as a log-channel by adding the following config to the channels section in config/logging.php:

```php
'channels' => [
    // ...
    'debugmate' => [
        'driver' => 'debugmate',
    ],
],
```
After that you need to fill it on `LOG_STACK` env:

```php
LOG_STACK=debugmate
```

## Testing if everything works

By the end you're being able to send a fake exception to test connection

```php
php artisan debugmate:test
```
