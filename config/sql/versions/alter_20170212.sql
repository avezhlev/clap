ALTER TABLE `cdr` 
CHANGE COLUMN `IncomingProtocolCallRef` `IncomingProtocolCallRef` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
CHANGE COLUMN `OutgoingProtocolCallRef` `OutgoingProtocolCallRef` varchar(64) COLLATE utf8_unicode_ci NOT NULL;
