# Hellas Wiki Setup Guide

## Webhook Configuration

1. In GitHub, open **Settings → Webhooks** for the HellasForms repository.
2. Set the payload URL to `https://your-site.example.com/wp-json/hellaswiki/v1/github/webhook`.
3. Choose **application/json** content type and paste the secret defined in **Hellas Wiki → Settings**.
4. Select events: **Just the push event**.
5. Save. Each push that touches the monitored `data/pixelmon/species`, `data/hellasforms/moves`, `data/hellasforms/abilities`, or `data/hellasforms/items` folders will queue imports.

## Personal Access Token (PAT)

* Generate a classic PAT with `repo` scope.
* Store it in **Hellas Wiki → Settings → Personal Access Token**.
* The token is used for manual imports, the poller, and queue processing.

## Cron / Poller

* Ensure WordPress cron runs by visiting the site regularly or configuring a server-side cron to hit `wp-cron.php`.
* To use a system cron, create a job: `*/10 * * * * php /path/to/site/wp-cron.php >/dev/null 2>&1`.
* Toggle **Enable Poller** in settings to allow automated GitHub polling.

## Manual Import Examples

* Species: `https://raw.githubusercontent.com/HellasRegion/HellasForms/main/src/main/resources/data/pixelmon/species/hellasian_gyarados.json`
* Move: `https://raw.githubusercontent.com/HellasRegion/HellasForms/main/src/main/resources/data/hellasforms/moves/hellasian_wave.json`
* Ability: `https://raw.githubusercontent.com/HellasRegion/HellasForms/main/src/main/resources/data/hellasforms/abilities/hellasian_fury.json`
* Item: `https://raw.githubusercontent.com/HellasRegion/HellasForms/main/src/main/resources/data/hellasforms/items/hellasian_gyaradosite.json`

## Type Overview Page

1. Create a new page with the **Hellas Wiki Type Overview** template (slug `hellas-wiki/type-overview`).
2. Publish the page; it will automatically render the typing matrix and type cards.
