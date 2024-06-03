#  News Legacy Feeds

This repo provides legacy news feeds.


## Developing with Lando

This project comes with a lando file for easy review and development.


```
lando start
lando install
```

Other tooling.
```
lando phpcs

lando phpcbf

lando phpstan
```


See

- All Stories Feed https://news-legacy-feeds.lndo.site/feed/json/stories/all


Categories

- All Categories Feed https://news-legacy-feeds.lndo.site/feed/json/categories/all
- Filtered Categories Feed Example https://news-legacy-feeds.lndo.site/feed/json/categories/uanews_categories+uanews_tags

To test, have the site import from itself.

**Note:** This module includes configuration files in the `/config/dependencies/` folder. These files are intended to be automatically installed into your local site when running `lando start && lando install`. However, the configuration in the `/config/dependencies/` folder should not be installed on the news.arizona.edu site, as it already exists in the site's database.


```
lando drush en -y az_news_feeds
lando drush config:set az_news_feeds.settings uarizona_news_base_uri 'https://news-legacy-feeds.lndo.site' -y
lando drush config:set az_news_feeds.settings uarizona_news_vocabularies.news_story_categories 'Sections' -y
lando drush config:set az_news_feeds.settings uarizona_news_vocabularies.az_news_tags 'Tags' -y
```
