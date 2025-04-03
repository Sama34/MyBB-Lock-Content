<p align="center">
    <a href="" rel="noopener">
        <img width="700" height="400" src="https://github.com/user-attachments/assets/33d4b656-495a-4bed-92a0-f44e78d03861" alt="Project logo">
    </a>
</p>

<h3 align="center">MyBB Lock Content</h3>

<div align="center">

[![Status](https://img.shields.io/badge/status-active-success.svg)]()
[![GitHub Issues](https://img.shields.io/github/issues/OUGC-Network/MyBB-Lock-Content.svg)](./issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/OUGC-Network/MyBB-Lock-Content.svg)](./pulls)
[![License](https://img.shields.io/badge/license-GPL-blue)](/LICENSE)

</div>

---

<p align="center"> Allow users to hide content in their posts in exchange for replies or NewPoints currency.
    <br> 
</p>

## 📜 Table of Contents <a name = "table_of_contents"></a>

- [About](#about)
- [Getting Started](#getting_started)
    - [Dependencies](#dependencies)
    - [File Structure](#file_structure)
    - [Install](#install)
    - [Update](#update)
- [Settings](#settings)
- [Templates](#templates)
- [Usage](#usage)
- [Built Using](#built_using)
- [Authors](#authors)
- [Acknowledgments](#acknowledgement)
- [Support & Feedback](#support)

## 🚀 About <a name = "about"></a>

Allow users to hide content in their posts in exchange for replies or NewPoints currency.

[Go up to Table of Contents](#table_of_contents)

## 📍 Getting Started <a name = "getting_started"></a>

The following information will assist you into getting a copy of this plugin up and running on your forum.

### Dependencies <a name = "dependencies"></a>

A setup that meets the following requirements is necessary to use this plugin.

- [MyBB](https://mybb.com/) >= 1.8
- PHP >= 7
- [MyBB-PluginLibrary](https://github.com/frostschutz/MyBB-PluginLibrary) >= 13

### File structure <a name = "file_structure"></a>

  ```
   .
   ├── inc
   │ ├── languages
   │ │ ├── english
   │ │ │ ├── admin
   │ │ │ │ ├── lock.lang.php
   │ │ │ ├── lock.lang.php
   │ │ ├── espanol
   │ │ │ ├── admin
   │ │ │ │ ├── lock.lang.php
   │ │ │ ├── lock.lang.php
   │ ├── plugins
   │ │ ├── lock
   │ │ │ ├── cipher
   │ │ │ │ ├── blowfish.php
   │ │ │ ├── core
   │ │ │ │ ├── install.php
   │ │ │ │ ├── purchase.php
   │ │ │ │ ├── shortcode.php
   │ │ │ │ ├── uninstall.php
   │ │ │ ├── pcrypt.php
   │ │ │ ├── shortcodes.class.php
   │ │ ├── lock.php
   ```

### Installing <a name = "install"></a>

Follow the next steps in order to install a copy of this plugin on your forum.

1. Download the latest package from the [MyBB Extend](https://community.mybb.com/mods.php) site or
   from the [repository releases](https://github.com/OUGC-Network/MyBB-Lock-Content/releases/latest).
2. Upload the contents of the _Upload_ folder to your MyBB root directory.
3. Browse to _Configuration » Plugins_ and install this plugin by clicking _Install & Activate_.

### Updating <a name = "update"></a>

Follow the next steps in order to update your copy of this plugin.

1. Browse to _Configuration » Plugins_ and deactivate this plugin by clicking _Deactivate_.
2. Follow step 1 and 2 from the [Install](#install) section.
3. Browse to _Configuration » Plugins_ and activate this plugin by clicking _Activate_.

[Go up to Table of Contents](#table_of_contents)

## 🛠 Settings <a name = "settings"></a>

Below you can find a description of the plugin settings.

### Main Settings

- **Key** `text`
    - _A password to keep spooky people from editing values they shouldn't be._
- **Enable NewPoints Purchases** `yesNo`
    - _Allow users to sell locked content for newpoints credits._
- **Allow User Prices** `yesNo`
    - _Do you want to let users set the price of their content?_
- **Default Price** `numeric`
    - _The default price for hidden content. Set this to zero if you want hide tags to fall back to the "Reply to view"
      mode._
- **Tax** `numeric`
    - _Tax a percentage of the points every user spends on content (up to 100%)._
- **Exempt Groups** `select`
    - _Select the usergroups that can bypass the hide tags._
- **Disabled Forums** `select`
    - _Select the forums that you do not want the "pay to view" functionality to work in._
- **Tag Code** `select`
    - _You can either use a the wording '[hide]' or '[lock]'._

[Go up to Table of Contents](#table_of_contents)

## 📐 Templates <a name = "templates"></a>

The following is a list of templates available for this plugin.

- `lock_wrapper`
    - _front end_;
- `lock_form`
    - _front end_;

[Go up to Table of Contents](#table_of_contents)

## 📖 Usage <a name="usage"></a>

This plugin has no additional configurations; after activating make sure to modify the global settings in order to get
this plugin working.

[Go up to Table of Contents](#table_of_contents)

## ⛏ Built Using <a name = "built_using"></a>

- [MyBB](https://mybb.com/) - Web Framework
- [MyBB PluginLibrary](https://github.com/frostschutz/MyBB-PluginLibrary) - A collection of useful functions for MyBB
- [PHP](https://www.php.net/) - Server Environment

[Go up to Table of Contents](#table_of_contents)

## ✍️ Authors <a name = "authors"></a>

- [@Omar G](https://github.com/Sama34) - Idea & Initial work

[Go up to Table of Contents](#table_of_contents)

## 🎉 Acknowledgements <a name = "acknowledgement"></a>

- [The Documentation Compendium](https://github.com/kylelobo/The-Documentation-Compendium)

[Go up to Table of Contents](#table_of_contents)

## 🎈 Support & Feedback <a name="support"></a>

This is free development and any contribution is welcome. Get support or leave feedback at the
official [MyBB Community](https://community.mybb.com/thread-159249.html).

Thanks for downloading and using our plugins!

[Go up to Table of Contents](#table_of_contents)