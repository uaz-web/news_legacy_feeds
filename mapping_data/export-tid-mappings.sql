-- Generate new TID mappings list (lookup new TID from old TID)

(SELECT
  sourceid1, destid1
FROM
  migrate_map_news_news_categories)
UNION
(SELECT
  sourceid1, destid1
FROM
  migrate_map_news_news_tags)
ORDER BY
  sourceid1;


-- Generate old TID mappings list (lookup old TID from new TID)
(SELECT
  destid1, sourceid1
FROM
 migrate_map_news_news_categories)
UNION
(SELECT
  destid1, sourceid1
FROM
  migrate_map_news_news_tags)
ORDER BY
  destid1;
