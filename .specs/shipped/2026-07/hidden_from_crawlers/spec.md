---
status: shipped
shipped: 2026-07-08
commit: 4d7245c
---

# Hidden from crawlers

The website can be hidden from crawlers, scrappers and search engines.
There is a configuration entry to enable or disable the "hidden mode". The default is "hidden".

* The robots.txt file is present and blocks all crawlers/bots if the feature is enabled.
* The meta tag `robots` is present and set to `noindex`.
* There is an admin configuration to whitelist robots by terms contained in the user agent. The robots.txt file can be regenerated from that configuration.


## Reference

You can investigate what crawlers and bad actors 

https://github.com/mitchellkrogza/apache-ultimate-bad-bot-blocker
