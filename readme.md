![Banner](images/banner.jpg)

# vBulletin - Utilities

> Sample code to connect to a vBulletin powered forum

This script should be saved in the root folder of your vBulletin installation and will allow to display the list of tables, list of user groups and the list of users recently added and their biography (if filled in).

The script is fully working and if you don't update it, will display the list of recent users in a table but only the ones with a filled in biography. Most of time such users are spammers.

Don't hesitate to read and update the script so it'll fit to your own needs.

## Install

Get a raw copy of the script,  save it in the root folder of your vBulletin installation.

Edit the script and update the password. The default one is **MySecretPassword**. You can define a new one with this single php statement: `echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT);`

## Usage

Just access the script by URL and type your password.

## Author

Christophe Avonture

## License

[MIT](LICENSE)
