# Concierge plugin for Craft CMS 3.x

A plugin to bring simple user moderation routine to craft

** Modified by Graphical House to provide two routings when unsuspending accounts, based on which user group is assigned to a user:

sendUserUnsuspendedEmail = a message send to users assigned to the Contract Customer group
sendUserUnsuspendedNormalEmail = a message sent to users assigned to the Normal Customer group

## Requirements

This plugin requires Craft CMS 3.0.0-RC11 or later. (For Craft 2 visit the [Concierge Website](https://concierge.olivierbon.com).)

## Installation

Go to the Plugin Store in your project’s Control Panel and search for “Concierge”. Then click on the “Install” button in its modal window.

## Concierge Overview and Documentation

Please visit the [Concierge Website](https://concierge.olivierbon.com)

----

## Graphical House notes

To ensure that our fork of the Craft Concierge plugin is loaded (from our Github repo not theirs), the following is required in the main `composer.json` file for the Craft project:

1. `minimum-stability": "dev"` (So that dev releases are accepted)
2. `"olivierbon/craft-concierge/craft-concierge": "dev-master"` (The second half tells composer to load the "master" branch from Github)
3. A `"repositories"` line that points to `"GraphicalHouse/craft-concierge"`

So, with all of these in place:

```
{
  "minimum-stability": "dev",
  "require": {
    "aelvan/imager": "v2.1.10",
    "craftcms/cms": "3.2.9",
    etc...
    "olivierbon/craft-concierge/craft-concierge": "dev-master"
  },
  "repositories": [{
    "type": "vcs",
    "url": "https://github.com/GraphicalHouse/craft-concierge"
  }],
```  

Maybe you'll also need to add "preferred-install": "dist", to the config array in composer.json
"config": {
    "optimize-autoloader": true,
    "sort-packages": true,
    "preferred-install": "dist"
  },
