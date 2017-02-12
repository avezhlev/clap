ALTER TABLE `cdr` 
CHANGE COLUMN `pkid` `pkid` varchar(64) COLLATE utf8_unicode_ci NOT NULL;

ALTER TABLE `cmr` 
CHANGE COLUMN `pkid` `pkid` varchar(64) COLLATE utf8_unicode_ci NOT NULL;