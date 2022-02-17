# Drupal 9 Demo Project(s) from nikro.me

This project contains various Drupal 9 demo modules, for various blog articles from nikro.me.
It's based on the "Drupal 9 with Composer Docksal" project.

Demo Modules:

- **Nikro Image Effects** - demo module showing how one can use image effects dynamically.
- ... more will come :)

## Setup instructions

### Step #1: Docksal environment setup

**This is a one time setup - skip this if you already have a working Docksal environment.**

Follow [Docksal environment setup instructions](https://docs.docksal.io/getting-started/setup/)

### Step #2: Project setup

1. Clone this repo into your Projects directory

    ```
    git clone https://github.com/Nikro/blog-drupal-demos.git nikro-demos
    cd nikro-demos
    ```

2. Initialize the site

    This will initialize local settings and install the site via drush

    ```
    fin init
    ```

3. Point your browser to

    ```
    http://nikro-demos.docksal
    ```

When the automated install is complete the command line output will display the admin username and password.

### Step #3: Enable modules you're interested in:

Just go to http://nikro-demos.docksal/

## Security notice

This repo is intended for showcases & demos and includes a hardcoded value for `hash_salt` in `settings.php`.
If you are basing your project code base on this repo, make sure you regenerate and update the `hash_salt` value.
A new value can be generated with `drush ev '$hash = Drupal\Component\Utility\Crypt::randomBytesBase64(55); print $hash . "\n";'`
