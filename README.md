Cloudflare DNS Updater
---
________
This application is designed to be run as a cron to automate updating dynamic IP addresses through Cloudflares API.

I built this script as the Dynamic DNS provider I had been using was closing down and needed a way to update my DNS. Since I had just moved behind Cloudflare and have the ability to use their API. I investigated scripts to see if some had been created. I managed to find some and so took inspiration from other simple scripts and decided to make it more robust.

Usage
---
________
This application expects 4 parameters. Running the application with no arguments will display some help text. These can be seen below:

The subdomain parameter is optional. Should it not be provided, the application will assume that the domain is the DNS record to be updated.

- Email - Email address registered with Cloudflare
- API key - Cloudflare API key
- Domain - The domain to be updated
- Sub domain - The sub domain to be updated (optional)

For example:

`php /usr/bin/php /var/www/dns-update.php <email> <apikey> <domain> [<subdomain>]`
