# Development Guide

## IDE Errors Explained

You may see "undefined" errors in your IDE for:
- `Illuminate\Support\ServiceProvider`
- `Stripe\Stripe`
- `config()`, `app()`, `route()` functions
- Other Laravel/Stripe classes

**These are NOT real errors!** They appear because:

1. The Laravel framework is not installed in this package directory
2. The Stripe SDK is not installed yet
3. This is a **Laravel package**, not a standalone application

## How to Fix IDE Errors

### Option 1: Install Dependencies (Recommended for Development)

```bash
cd /home/johirulalam/my-package/sayed-payment-laravel
composer install
```

This will install all dependencies defined in `composer.json`.

### Option 2: IDE Configuration

Add this to your workspace settings (VSCode example):

```json
{
    "php.suggest.basic": false,
    "intelephense.environment.includePaths": [
        "/path/to/your/laravel/project/vendor"
    ]
}
```

### Option 3: Use Laravel IDE Helper

When using this package in a Laravel project:

```bash
composer require --dev barryvdh/laravel-ide-helper
php artisan ide-helper:generate
```

## Testing the Package

### In a Real Laravel Project

1. **Create a Laravel project** (if you don't have one):
```bash
composer create-project laravel/laravel test-app
cd test-app
```

2. **Add this package as a local dependency**:

Edit `composer.json`:
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../sayed-payment-laravel"
        }
    ]
}
```

3. **Install the package**:
```bash
composer require sayed/payment-laravel
```

4. **Publish config**:
```bash
php artisan vendor:publish --tag=payment-config
```

5. **Configure `.env`**:
```env
STRIPE_SECRET_KEY=sk_test_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx
```

6. **Use it**:
```php
use Sayed\Payment\Facades\Payment;

$result = Payment::createAdapter('stripe')->checkout([
    'has_price_id' => false,
    'currency' => 'usd',
    'amount' => 5000,
    'product' => ['title' => 'Test Product'],
    'mode' => 'payment',
    'success_url' => 'http://localhost/success',
    'cancel_url' => 'http://localhost/cancel',
]);
```

## Why This Happens

This package is designed to be **installed in a Laravel application**. When installed properly:

✅ Laravel provides the `ServiceProvider` base class  
✅ Laravel provides helper functions (`config()`, `app()`, etc.)  
✅ Composer autoloads the Stripe SDK  
✅ All dependencies are resolved automatically

## Package Structure is Correct

Despite IDE warnings, the package structure is **100% correct**:

- ✅ All PHP files exist
- ✅ All classes are properly namespaced
- ✅ All inheritance is correct
- ✅ All imports are proper
- ✅ Code follows Laravel conventions

The "errors" you see are just your IDE not having access to Laravel's framework files.

## Quick Test

To quickly verify everything works:

```bash
# Install dependencies
composer install

# Check autoload
composer dump-autoload

# All errors should disappear!
```
